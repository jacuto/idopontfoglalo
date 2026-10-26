<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use InvalidArgumentException;
class Payments
{
    public function __construct(
        private Connections $connections,
        private SalonStore $store,
    ) {}
    public function checkout(string $bookingId): array
    {
        $s = $this->store->read();
        $b = collect($s["bookings"])->firstWhere("id", $bookingId);
        if (!$b) {
            abort(404);
        }
        if (!in_array($b["status"], ["pending", "confirmed"], true)) {
            throw new InvalidArgumentException("Ez a foglalás nem fizethető.");
        }
        $paid = collect($s["payments"])
            ->where("bookingId", $b["id"])
            ->where("status", "paid")
            ->sum(fn($p) => $p["amount"] - ($p["refundedAmount"] ?? 0));
        $amount =
            array_sum(array_column($b["serviceSnapshot"], "price")) - $paid;
        if ($amount <= 0) {
            throw new InvalidArgumentException(
                "Nincs fennmaradó fizetendő összeg.",
            );
        }
        $key = $this->connections->all()["stripe_key"];
        if (!$key) {
            throw new InvalidArgumentException(
                "Előbb add meg a Stripe titkos API-kulcsot.",
            );
        }
        $reference = hash(
            "sha256",
            $b["id"] . "|" . $amount . "|" . now()->format("Y-m-d"),
        );
        $base = rtrim(config("app.url"), "/");
        $response = Http::timeout(20)
            ->withToken($key)
            ->withHeaders(["Idempotency-Key" => $reference])
            ->asForm()
            ->post("https://api.stripe.com/v1/checkout/sessions", [
                "mode" => "payment",
                "line_items" => [
                    [
                        "price_data" => [
                            "currency" => "huf",
                            "product_data" => [
                                "name" =>
                                    "Szolgáltatások — " .
                                    $s["settings"]["name"],
                            ],
                            "unit_amount" => $amount * 100,
                        ],
                        "quantity" => 1,
                    ],
                ],
                "success_url" =>
                    $base .
                    "/modositas?token=" .
                    $b["token"] .
                    "&payment=success",
                "cancel_url" => $base . "/modositas?token=" . $b["token"],
                "client_reference_id" => $b["id"],
                "metadata" => ["booking_id" => $b["id"]],
                "billing_address_collection" => "required",
                ...!empty($b["email"]) ? ["customer_email" => $b["email"]] : [],
            ]);
        if (!$response->successful()) {
            throw new InvalidArgumentException(
                "A Stripe nem hozta létre a fizetési oldalt. Ellenőrizd a kulcsot és a Stripe-fiókot.",
            );
        }
        $session = $response->json();
        if (
            empty($session["id"]) ||
            !str_starts_with(
                $session["url"] ?? "",
                "https://checkout.stripe.com/",
            )
        ) {
            throw new InvalidArgumentException("Érvénytelen Stripe-válasz.");
        }
        $this->store->mutate(function (&$s) use ($session, $b, $amount) {
            $s["checkouts"][$session["id"]] = [
                "bookingId" => $b["id"],
                "amount" => $amount,
                "type" => "booking",
                "createdAt" => now()->toISOString(),
            ];
        });
        return ["url" => $session["url"]];
    }
    public function subscription(): array
    {
        $key = config("integrations.platform_stripe.key");
        $price = config("integrations.platform_stripe.price");
        if (!$key || !$price) {
            throw new InvalidArgumentException(
                "A központi előfizetés Stripe-hozzáférése még nincs beállítva.",
            );
        }
        $s = $this->store->read();
        if (
            in_array(
                $s["subscription"]["status"] ?? "",
                ["active", "trialing"],
                true,
            )
        ) {
            throw new InvalidArgumentException("Már van aktív előfizetés.");
        }
        $p = Http::timeout(15)
            ->withToken($key)
            ->get("https://api.stripe.com/v1/prices/" . rawurlencode($price))
            ->throw()
            ->json();
        if (
            ($p["unit_amount"] ?? 0) !== 400000 ||
            ($p["currency"] ?? "") !== "huf" ||
            ($p["recurring"]["interval"] ?? "") !== "month" ||
            ($p["recurring"]["interval_count"] ?? 0) !== 1
        ) {
            throw new InvalidArgumentException(
                "Az alapcsomag Stripe-ára 4 000 Ft / hó legyen.",
            );
        }
        $r = Http::timeout(20)
            ->withToken($key)
            ->withHeaders([
                "Idempotency-Key" =>
                    "salon-subscription-" .
                    hash("sha256", config("app.url") . now()->format("Y-m-d")),
            ])
            ->asForm()
            ->post("https://api.stripe.com/v1/checkout/sessions", [
                "mode" => "subscription",
                "line_items" => [["price" => $price, "quantity" => 1]],
                "billing_address_collection" => "required",
                "tax_id_collection" => ["enabled" => "true"],
                "success_url" =>
                    rtrim(config("app.url"), "/") . "/?subscription=success",
                "cancel_url" =>
                    rtrim(config("app.url"), "/") . "/?subscription=cancelled",
            ]);
        if (!$r->successful()) {
            throw new InvalidArgumentException(
                "Az előfizetés indítása nem sikerült.",
            );
        }
        $v = $r->json();
        $this->store->mutate(function (&$s) use ($v) {
            $s["checkouts"][$v["id"]] = ["type" => "subscription"];
        });
        return ["url" => $v["url"]];
    }
    public function webhook(Request $request, bool $platform = false)
    {
        $secret = $platform
            ? config("integrations.platform_stripe.webhook_secret")
            : $this->connections->all()["stripe_webhook_secret"];
        if (!$secret) {
            return response()->json(
                ["error" => "Webhook nincs beállítva."],
                503,
            );
        }
        $header = $request->header("Stripe-Signature", "");
        preg_match("/(?:^|,)t=(\d+)/", $header, $m);
        preg_match_all("/(?:^|,)v1=([a-f0-9]+)/", $header, $matches);
        $timestamp = (int) ($m[1] ?? 0);
        $expected = hash_hmac(
            "sha256",
            $timestamp . "." . $request->getContent(),
            $secret,
        );
        $valid = false;
        foreach ($matches[1] as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
            }
        }
        if (!$valid || abs(time() - $timestamp) > 300) {
            return response()->json(["error" => "Érvénytelen aláírás."], 400);
        }
        $event = $request->json()->all();
        $object = $event["data"]["object"] ?? [];
        $this->store->mutate(function (&$s) use ($event, $object, $platform) {
            if ($event["type"] === "checkout.session.completed") {
                $checkout = $s["checkouts"][$object["id"]] ?? null;
                if (!$checkout) {
                    return;
                }
                if ($checkout["type"] === "subscription" && $platform) {
                    $s["subscription"] = [
                        "id" => $object["subscription"],
                        "customer" => $object["customer"],
                        "status" => "active",
                    ];
                    return;
                }
                if (
                    $platform ||
                    $checkout["type"] !== "booking" ||
                    ($object["payment_status"] ?? "") !== "paid" ||
                    ($object["currency"] ?? "") !== "huf" ||
                    ($object["amount_total"] ?? 0) !== $checkout["amount"] * 100
                ) {
                    return;
                }
                foreach ($s["payments"] as $p) {
                    if (($p["stripeSession"] ?? "") === $object["id"]) {
                        return;
                    }
                }
                $details = $object["customer_details"] ?? [];
                $address = $details["address"] ?? [];
                $s["payments"][] = [
                    "id" => (string) Str::uuid(),
                    "bookingId" => $checkout["bookingId"],
                    "amount" => $checkout["amount"],
                    "method" => "card",
                    "paidAt" => now()->toISOString(),
                    "status" => "paid",
                    "source" => "stripe",
                    "stripeSession" => $object["id"],
                    "stripePaymentIntent" => $object["payment_intent"] ?? null,
                    "createdAt" => now()->toISOString(),
                    "buyer" => [
                        "name" => $details["name"] ?? "",
                        "email" => $details["email"] ?? "",
                        "country" => $address["country"] ?? "HU",
                        "zip" => $address["postal_code"] ?? "",
                        "city" => $address["city"] ?? "",
                        "address" => trim(
                            ($address["line1"] ?? "") .
                                " " .
                                ($address["line2"] ?? ""),
                        ),
                    ],
                ];
            }
            if (!$platform && $event["type"] === "charge.refunded") {
                foreach ($s["payments"] as &$p) {
                    if (
                        ($p["stripePaymentIntent"] ?? null) ===
                            ($object["payment_intent"] ?? "") &&
                        $p["source"] === "stripe"
                    ) {
                        $p["refundedAmount"] = min(
                            $p["amount"],
                            (int) round(
                                ($object["amount_refunded"] ?? 0) / 100,
                            ),
                        );
                        if ($p["refundedAmount"] === $p["amount"]) {
                            $p["status"] = "refunded";
                        }
                    }
                }
            }
            if (
                $platform &&
                in_array(
                    $event["type"],
                    [
                        "customer.subscription.updated",
                        "customer.subscription.deleted",
                    ],
                    true,
                ) &&
                ($s["subscription"]["id"] ?? null) === $object["id"]
            ) {
                $s["subscription"]["status"] = $object["status"];
            }
        });
        return response()->json(["received" => true]);
    }
}
