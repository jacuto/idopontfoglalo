<?php
namespace App\Http\Controllers;
use App\Services\{
    SalonStore,
    BookingRules,
    Connections,
    Notifications,
    Settings,
    Payments,
    Invoices,
};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
class WorkspaceController
{
    public function __construct(
        private SalonStore $store,
        private BookingRules $rules,
    ) {}
    public function handle(Request $request)
    {
        $path = "/" . $request->path();
        $method = $request->method();
        if ($path === "/api/connections" && $method === "GET") {
            return response()->json(app(Connections::class)->status());
        }
        if ($path === "/api/connections" && $method === "PUT") {
            $data = $request->validate([
                "smtp_host" => "nullable|string|max:200",
                "smtp_port" => "nullable|integer|between:1,65535",
                "smtp_username" => "nullable|string|max:250",
                "smtp_password" => "nullable|string|max:1000",
                "mail_from" => "nullable|email|max:200",
                "mail_name" => "nullable|string|max:150",
                "billingo_key" => "nullable|string|max:1000",
                "billingo_block" => "nullable|integer|min:1",
                "szamlazz_key" => "nullable|string|max:1000",
                "stripe_key" => "nullable|string|max:1000",
                "stripe_webhook_secret" => "nullable|string|max:1000",
                "stripe_price_id" => "nullable|string|max:200",
                "twilio_sid" => "nullable|alpha_num|max:100",
                "twilio_token" => "nullable|string|max:200",
                "twilio_from" => "nullable|string|max:100",
                "google_client_id" => "nullable|string|max:300",
                "google_client_secret" => "nullable|string|max:500",
                "facebook_client_id" => "nullable|numeric",
                "facebook_client_secret" => "nullable|string|max:500",
                "admin_google_emails" => "nullable|string|max:2000",
                "admin_facebook_ids" => "nullable|string|max:2000",
            ]);
            app(Connections::class)->save($data);
            DB::table("deliveries")
                ->where("status", "blocked")
                ->update(["status" => "pending", "available_at" => now()]);
            return response()->json(["ok" => true]);
        }
        if ($path === "/api/deliveries" && $method === "GET") {
            return response()->json(
                DB::table("deliveries")
                    ->select(
                        "id",
                        "status",
                        "attempts",
                        "available_at",
                        "error",
                        "updated_at",
                    )
                    ->latest("id")
                    ->limit(100)
                    ->get(),
            );
        }
        if ($path === "/api/deliveries/run" && $method === "POST") {
            return response()->json(app(Notifications::class)->run(10));
        }
        if (
            preg_match('#^/api/deliveries/(\d+)/retry$#', $path, $m) &&
            $method === "POST"
        ) {
            DB::table("deliveries")
                ->where("id", $m[1])
                ->whereIn("status", ["failed", "blocked"])
                ->update([
                    "status" => "pending",
                    "attempts" => 0,
                    "available_at" => now(),
                ]);
            return response()->json(["ok" => true]);
        }
        if (
            preg_match(
                '#^/api/(clients|staff)/([a-zA-Z0-9_-]+)$#',
                $path,
                $m,
            ) &&
            in_array($method, ["PATCH", "DELETE"], true)
        ) {
            $kind = $m[1];
            $id = $m[2];
            $data =
                $method === "PATCH"
                    ? $request->validate([
                        "name" => "required|string|max:150",
                        "phone" => "nullable|string|max:40",
                        "email" => "nullable|email|max:200",
                        "role" =>
                            ($kind === "staff" ? "required" : "sometimes") .
                            "|string|max:80",
                    ])
                    : [];
            $result = $this->store->mutate(function (&$s) use (
                $kind,
                $id,
                $method,
                $data,
            ) {
                foreach ($s[$kind] as &$row) {
                    if ($row["id"] === $id) {
                        if ($method === "PATCH") {
                            $row = array_replace($row, $data);
                            $row["phone"] = $row["phone"] ?? "";
                            $row["email"] = $row["email"] ?? "";
                            return $row;
                        }
                        if ($kind !== "staff") {
                            abort(405);
                        }
                        if (
                            count(
                                array_filter(
                                    $s["staff"],
                                    fn($p) => empty($p["archived"]),
                                ),
                            ) <= 1
                        ) {
                            throw new InvalidArgumentException(
                                "Az utolsó aktív munkatárs nem törölhető.",
                            );
                        }
                        foreach ($s["bookings"] as $b) {
                            if (
                                $b["staff"] === $id &&
                                in_array(
                                    $b["status"],
                                    ["pending", "confirmed"],
                                    true,
                                ) &&
                                CarbonImmutable::parse($b["start"])->isFuture()
                            ) {
                                throw new InvalidArgumentException(
                                    "Előbb helyezd át vagy mondd le a munkatárs jövőbeli foglalásait.",
                                );
                            }
                        }
                        if (
                            $s["settings"]["businessMode"] === "solo" &&
                            $s["settings"]["primaryStaffId"] === $id
                        ) {
                            throw new InvalidArgumentException(
                                "Előbb válassz másik elsődleges munkatársat.",
                            );
                        }
                        $row["archived"] = true;
                        foreach ($s["services"] as &$v) {
                            $active = array_filter(
                                $s["staff"],
                                fn($p) => empty($p["archived"]) &&
                                    in_array($p["id"], $v["staff"], true),
                            );
                            if (!$active) {
                                $v["archived"] = true;
                            }
                        }
                        return ["ok" => true];
                    }
                }
                abort(404, "Nincs ilyen tétel.");
            });
            return response()->json($result);
        }
        if (
            preg_match(
                '#^/api/imports/(clients|staff|bookings)$#',
                $path,
                $m,
            ) &&
            $method === "POST"
        ) {
            $data = $request->validate([
                "records" => "required|array|min:1|max:2000",
                "records.*" => "required|array",
            ]);
            $kind = $m[1];
            $result = $this->store->mutate(function (&$s) use ($kind, $data) {
                $added = 0;
                $updated = 0;
                foreach ($data["records"] as $index => $record) {
                    $id = $record["id"] ?? "" ?: (string) Str::uuid();
                    if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id)) {
                        throw new InvalidArgumentException(
                            "Érvénytelen azonosító a(z) " .
                                ($index + 2) .
                                ". sorban.",
                        );
                    }
                    if ($kind === "bookings") {
                        $record = validator($record, [
                            "name" => "required|string|max:150",
                            "phone" => "nullable|string|max:40",
                            "email" => "nullable|email|max:200",
                            "start" => "required|date",
                            "staff" => "required|string",
                            "services" => "required|array|min:1|max:20",
                            "services.*" => "required|string",
                            "serviceSnapshot" => "sometimes|array|min:1|max:20",
                            "serviceSnapshot.*.id" => "required|string",
                            "serviceSnapshot.*.name" =>
                                "required|string|max:150",
                            "serviceSnapshot.*.price" =>
                                "required|numeric|min:0|max:100000000",
                            "serviceSnapshot.*.duration" =>
                                "required|integer|between:5,1440",
                            "serviceSnapshot.*.staff" => "sometimes|array",
                            "serviceSnapshot.*.phases" =>
                                "sometimes|array|size:3",
                            "serviceSnapshot.*.phases.*" =>
                                "required|integer|min:0",
                            "status" =>
                                "sometimes|in:pending,confirmed,cancelled,rejected",
                        ])->validate();
                        $old = collect($s["bookings"])->firstWhere("id", $id);
                        $record = array_merge($record, [
                            "id" => $id,
                            "phone" => $record["phone"] ?? "",
                            "email" => $record["email"] ?? "",
                            "status" => $record["status"] ?? "confirmed",
                            "token" =>
                                $old["token"] ?? bin2hex(random_bytes(24)),
                            "createdAt" =>
                                $old["createdAt"] ?? now()->toISOString(),
                            "updatedAt" => now()->toISOString(),
                        ]);
                        if (
                            !in_array(
                                $record["status"],
                                ["cancelled", "rejected"],
                                true,
                            )
                        ) {
                            $this->rules->validate($record, $s, $id, true);
                        } else {
                            foreach ($record["services"] as $v) {
                                $this->rules->service($s["services"], $v);
                            }
                        }
                        $record["serviceSnapshot"] =
                            $record["serviceSnapshot"] ??
                            array_map(
                                fn($v) => $this->rules->service(
                                    $s["services"],
                                    $v,
                                ),
                                $record["services"],
                            );
                        if (
                            array_column($record["serviceSnapshot"], "id") !==
                            $record["services"]
                        ) {
                            throw new InvalidArgumentException(
                                "A szolgáltatáspillanatkép azonosítói nem egyeznek.",
                            );
                        }
                        foreach ($record["serviceSnapshot"] as $snap) {
                            if (
                                isset($snap["phases"]) &&
                                array_sum($snap["phases"]) !== $snap["duration"]
                            ) {
                                throw new InvalidArgumentException(
                                    "Érvénytelen szakaszidők az importban.",
                                );
                            }
                        }
                    } else {
                        $record = validator($record, [
                            "name" => "required|string|max:150",
                            "phone" => "nullable|string|max:40",
                            "email" => "nullable|email|max:200",
                            "role" =>
                                ($kind === "staff" ? "required" : "sometimes") .
                                "|string|max:80",
                        ])->validate();
                        $record = array_merge($record, [
                            "id" => $id,
                            "phone" => $record["phone"] ?? "",
                            "email" => $record["email"] ?? "",
                        ]);
                    }
                    $match = null;
                    foreach ($s[$kind] as $i => $old) {
                        if (
                            $old["id"] === $id ||
                            ($kind === "clients" &&
                                (($record["phone"] !== "" &&
                                    $old["phone"] === $record["phone"]) ||
                                    ($record["email"] !== "" &&
                                        $old["email"] === $record["email"]))) ||
                            ($kind === "staff" &&
                                mb_strtolower($old["name"]) ===
                                    mb_strtolower($record["name"]))
                        ) {
                            $match = $i;
                            break;
                        }
                    }
                    if ($match === null) {
                        $s[$kind][] = $record;
                        $added++;
                    } else {
                        $record["id"] = $s[$kind][$match]["id"];
                        $s[$kind][$match] = array_replace(
                            $s[$kind][$match],
                            $record,
                        );
                        $updated++;
                    }
                }
                return ["added" => $added, "updated" => $updated];
            });
            return response()->json($result);
        }
        if ($path === "/api/payments" && $method === "POST") {
            $v = $request->validate([
                "bookingId" => "required|string",
                "amount" => "required|integer|min:1|max:100000000",
                "method" => "required|in:cash,card,transfer",
                "paidAt" => "required|date",
                "note" => "nullable|string|max:300",
            ]);
            $p = $this->store->mutate(function (&$s) use ($v) {
                $b = collect($s["bookings"])->firstWhere("id", $v["bookingId"]);
                if (!$b) {
                    abort(404, "Nincs ilyen foglalás.");
                }
                $total = array_sum(
                    array_column($b["serviceSnapshot"], "price"),
                );
                $paid = collect($s["payments"])
                    ->where("bookingId", $b["id"])
                    ->where("status", "paid")
                    ->sum(fn($p) => $p["amount"] - ($p["refundedAmount"] ?? 0));
                if ($v["amount"] > $total - $paid) {
                    throw new InvalidArgumentException(
                        "Az összeg nagyobb a fennmaradó fizetendő összegnél.",
                    );
                }
                $p = array_merge($v, [
                    "id" => (string) Str::uuid(),
                    "status" => "paid",
                    "source" => "manual",
                    "createdAt" => now()->toISOString(),
                ]);
                $s["payments"][] = $p;
                return $p;
            });
            return response()->json($p, 201);
        }
        if (
            preg_match('#^/api/payments/([a-zA-Z0-9_-]+)/void$#', $path, $m) &&
            $method === "POST"
        ) {
            return response()->json(
                $this->store->mutate(function (&$s) use ($m) {
                    foreach ($s["payments"] as &$p) {
                        if ($p["id"] === $m[1]) {
                            if ($p["source"] !== "manual") {
                                throw new InvalidArgumentException(
                                    "Stripe-befizetést a Stripe felületén kell visszatéríteni.",
                                );
                            }
                            $p["status"] = "void";
                            return ["ok" => true];
                        }
                    }
                    abort(404);
                }),
            );
        }
        if ($path === "/api/checkout" && $method === "POST") {
            return response()->json(
                app(Payments::class)->checkout($request->input("bookingId")),
            );
        }
        if ($path === "/api/subscription/checkout" && $method === "POST") {
            return response()->json(app(Payments::class)->subscription());
        }
        if (
            preg_match(
                '#^/api/invoices/([a-zA-Z0-9_-]+)/reconcile$#',
                $path,
                $m,
            ) &&
            $method === "POST"
        ) {
            $v = $request->validate([
                "decision" => "required|in:issued,not_created",
                "confirmed" => "required|accepted",
                "number" =>
                    "required_if:decision,issued|nullable|string|max:100",
                "reference" => "nullable|string|max:100",
            ]);
            $this->store->mutate(function (&$s) use ($m, $v) {
                foreach ($s["invoiceRequests"] as &$i) {
                    if ($i["id"] === $m[1]) {
                        if ($i["status"] !== "unknown") {
                            throw new InvalidArgumentException(
                                "Csak bizonytalan eredményű számla egyeztethető.",
                            );
                        }
                        $i["status"] =
                            $v["decision"] === "issued" ? "issued" : "failed";
                        $i["number"] = $v["number"] ?? null;
                        $i["reference"] = $v["reference"] ?? null;
                        $i["reconciledAt"] = now()->toISOString();
                        $i["reconciledBy"] = "admin";
                        return;
                    }
                }
                abort(404);
            });
            return response()->json(["ok" => true]);
        }
        if ($path === "/api/invoices" && $method === "POST") {
            return response()->json(
                app(Invoices::class)->issue(
                    $request->validate([
                        "paymentId" => "required|string",
                        "buyer" => "required|array",
                        "buyer.name" => "required|string|max:200",
                        "buyer.email" => "nullable|email",
                        "buyer.country" => "required|string|size:2",
                        "buyer.zip" => "required|string|max:20",
                        "buyer.city" => "required|string|max:100",
                        "buyer.address" => "required|string|max:250",
                        "buyer.taxNumber" => "nullable|string|max:30",
                    ]),
                ),
            );
        }
        abort(404);
    }
}
