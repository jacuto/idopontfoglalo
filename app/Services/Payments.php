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
            \App\Services\Offers::total($b) - $paid;
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
                    "/modositas?provider=" . app(Tenant::class)->id . "&token=" .
                    $b["token"] .
                    "&payment=success",
                "cancel_url" => $base . "/modositas?provider=" . app(Tenant::class)->id . "&token=" . $b["token"],
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
        if (!empty($s['subscription']['id'])) {
            // Reuse a real unpaid Stripe invoice instead of creating a second subscription.
            $existing=Http::timeout(15)->withToken($key)->get('https://api.stripe.com/v1/subscriptions/'.rawurlencode($s['subscription']['id']),['expand'=>['latest_invoice']]);
            if(!$existing->successful())throw new InvalidArgumentException('A meglévő Stripe-előfizetés most nem ellenőrizhető. Próbáld később, vagy használd az átutalást.');
            $subscription=$existing->json();
            if(!in_array($subscription['status']??'', ['canceled','incomplete_expired'],true)) {
                $invoice=$subscription['latest_invoice']??[];$url=is_array($invoice)?($invoice['hosted_invoice_url']??''):'';
                if(is_array($invoice)&&($invoice['status']??'')==='open'&&($invoice['amount_remaining']??0)>0&&is_string($url)&&parse_url($url,PHP_URL_SCHEME)==='https'&&parse_url($url,PHP_URL_HOST)==='invoice.stripe.com')return ['url'=>$url];
                throw new InvalidArgumentException('Már van élő Stripe-előfizetésed, és nincs most megnyitható fizetési számlád. Újabb előfizetést nem indítunk. Az esedékes díjakat az Előfizetés menüben látod.');
            }
        }
        if(!empty($s['subscription']['trialEndsAt'])) {
            $trialEnd=\Carbon\CarbonImmutable::parse($s['subscription']['trialEndsAt']);
            if($trialEnd->isFuture()&&$trialEnd->lt(now()->addHours(48)->addMinute()))throw new InvalidArgumentException('A próbaidő utolsó két napjában új Stripe-próba már nem indítható. A kártyás előfizetést a próbaidő végén indíthatod; addig díjmentesen használhatod a rendszert, vagy választhatod az átutalást.');
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
                    hash("sha256", config("app.url") . app(Tenant::class)->id . now()->format("Y-m-d")),
            ])
            ->asForm()
            ->post("https://api.stripe.com/v1/checkout/sessions", [
                "mode" => "subscription",
                "metadata" => ["provider_id" => (string) app(Tenant::class)->id],
                "subscription_data" => ["metadata" => ["provider_id" => (string) app(Tenant::class)->id], ...(!empty($s["subscription"]["trialEndsAt"]) && \Carbon\CarbonImmutable::parse($s["subscription"]["trialEndsAt"])->isFuture() ? ["trial_end" => \Carbon\CarbonImmutable::parse($s["subscription"]["trialEndsAt"])->timestamp] : [])],
                "line_items" => [["price" => $price, "quantity" => 1]],
                "billing_address_collection" => "required",
                "tax_id_collection" => ["enabled" => "true"],
                "success_url" =>
                    rtrim(config("app.url"), "/") . "/?provider=" . app(Tenant::class)->id . "&subscription=success",
                "cancel_url" =>
                    rtrim(config("app.url"), "/") . "/?provider=" . app(Tenant::class)->id . "&subscription=cancelled",
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
        // Keep only identifiers from optionally expanded Stripe references, never client secrets.
        foreach(['subscription','customer','payment_intent'] as $field) if(isset($object[$field])&&is_array($object[$field]))$object[$field]=$object[$field]['id']??null;
        if ($platform && isset($object["metadata"]["provider_id"])) {
            $tenant = filter_var($object["metadata"]["provider_id"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            if (!$tenant || !\Illuminate\Support\Facades\DB::table("salon_state")->where("id", $tenant)->exists()) return response()->json(["error" => "Ismeretlen szolgáltató."], 400);
            app(Tenant::class)->id = $tenant;
        }
        if ($platform && in_array($event['type'] ?? '', ['invoice.paid', 'invoice.payment_failed'], true)) {
            $subscription = $object['subscription'] ?? $object['parent']['subscription_details']['subscription'] ?? null;
            $found = false;
            foreach (\Illuminate\Support\Facades\DB::table('salon_state')->get() as $row) {
                $candidate = json_decode($row->data, true);
                if ($subscription && ($candidate['subscription']['id'] ?? null) === $subscription) { app(Tenant::class)->id = (int) $row->id; $found = true; break; }
            }
            // Stripe may deliver invoice.paid before checkout completion. Ask it to retry.
            if (!$found) return response()->json(['error' => 'Az előfizetés azonosítása még folyamatban. Újrapróbálás szükséges.'], 409);
        }
        $this->store->mutate(function (&$s) use ($event, $object, $platform) {
            $eventId = $event["id"] ?? null;
            if ($eventId && in_array($eventId, $s["stripeEvents"] ?? [], true)) return;
            if ($eventId) $s["stripeEvents"][] = $eventId;
            if ($event["type"] === "checkout.session.completed") {
                $checkout = $s["checkouts"][$object["id"]] ?? null;
                if (!$checkout) {
                    if (($platform && isset($object['metadata']['provider_id'])) || (!$platform && isset($object['metadata']['booking_id']))) abort(409,'A fizetési munkamenet mentése még folyamatban. Az eseményt újra kell küldeni.');
                    return;
                }
                if ($checkout["type"] === "subscription" && $platform && in_array($object["payment_status"] ?? "", ["paid","no_payment_required"],true)) {
                    $s["subscription"] = array_replace($s["subscription"], [
                        "id" => $object["subscription"],
                        "customer" => $object["customer"],
                        "status" => ($event["created"] ?? 0) >= ($s["subscription"]["lastEventAt"] ?? 0) ? (($object["payment_status"]??"")==="no_payment_required"?"trialing":"active") : ($s["subscription"]["status"] ?? "active"),
                        "lastEventAt" => max($event["created"] ?? 0, $s["subscription"]["lastEventAt"] ?? 0),
                    ]);
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
            if ($platform && ($event['type'] ?? '') === 'invoice.paid' && ($object['paid'] ?? false) === true && ($object['currency'] ?? '') === 'huf' && ($object['amount_paid'] ?? 0) === 400000) {
                $reference = 'stripe-invoice:' . $object['id'];
                $end = $object['lines']['data'][0]['period']['end'] ?? $object['period_end'] ?? null;
                if (!$end) throw new InvalidArgumentException('A Stripe nem adta meg az előfizetési időszak végét.');
                \Illuminate\Support\Facades\DB::table('platform_payments')->insertOrIgnore(['provider_id' => app(Tenant::class)->id, 'reference' => $reference, 'amount' => 4000, 'method' => 'card', 'paid_at' => \Carbon\CarbonImmutable::createFromTimestamp($object['status_transitions']['paid_at']??time())->setTimezone(config('app.timezone')), 'period_start'=>isset($object['lines']['data'][0]['period']['start'])?\Carbon\CarbonImmutable::createFromTimestamp($object['lines']['data'][0]['period']['start'])->setTimezone(config('app.timezone')):null, 'details'=>json_encode(['source'=>'stripe','stripeInvoice'=>$object['id'],'stripeSubscription'=>$object['subscription']??$object['parent']['subscription_details']['subscription']??null,'stripeCustomer'=>$object['customer']??null,'paymentIntent'=>$object['payment_intent']??null,'eventId'=>$event['id']??null,'currency'=>'HUF','amountPaid'=>4000]), 'period_end' => \Carbon\CarbonImmutable::createFromTimestamp($end)->setTimezone(config('app.timezone')), 'created_at' => now(), 'updated_at' => now()]);
                $payment = \Illuminate\Support\Facades\DB::table('platform_payments')->where('reference',$reference)->first();
                $periodStart = $object['lines']['data'][0]['period']['start'] ?? $object['period_start'] ?? null;
                Subscriptions::settle($s,$payment,null,$periodStart ? \Carbon\CarbonImmutable::createFromTimestamp($periodStart)->toISOString() : null);
                $paidUntil = \Carbon\CarbonImmutable::createFromTimestamp($end)->toISOString();
                if (empty($s['subscription']['paidUntil']) || $paidUntil > $s['subscription']['paidUntil']) $s['subscription']['paidUntil'] = $paidUntil;
                if (($event['created'] ?? 0) >= ($s['subscription']['lastEventAt'] ?? 0)) {
                    $s['subscription']['status'] = 'active'; $s['subscription']['lastEventAt'] = $event['created'] ?? 0;
                }
            }
            if ($platform && ($event['type'] ?? '') === 'invoice.payment_failed' && ($event['created'] ?? 0) >= ($s['subscription']['lastEventAt'] ?? 0)) { $s['subscription']['status'] = 'past_due'; $s['subscription']['lastEventAt'] = $event['created'] ?? 0; }
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
                if (($event["created"] ?? 0) >= ($s["subscription"]["lastEventAt"] ?? 0)) {
                    $s["subscription"]["status"] = $object["status"];
                    $s["subscription"]["lastEventAt"] = $event["created"] ?? time();
                }
            }
        });
        $this->store->mutate(function(&$s){ Subscriptions::refresh($s); });
        return response()->json(["received" => true]);
    }
}
