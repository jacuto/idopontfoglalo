<?php
namespace App\Http\Controllers;
use App\Services\BookingRules;
use App\Services\SalonStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SalonController
{
    public function __construct(
        private SalonStore $store,
        private BookingRules $rules,
    ) {}

    public function handle(Request $request)
    {
        try {
            $path = "/" . $request->path();
            $method = $request->method();
            if (in_array($method, ["POST", "PUT", "PATCH"], true)) {
                $origin = $request->header("Origin");
                if (
                    $origin &&
                    rtrim($origin, "/") !== $request->getSchemeAndHttpHost()
                ) {
                    return response()->json(
                        ["error" => "Tiltott kérésforrás."],
                        403,
                    );
                }
                if (!$request->isJson()) {
                    return response()->json(
                        ["error" => "JSON kérés szükséges."],
                        415,
                    );
                }
                if (strlen($request->getContent()) > 1000000) {
                    return response()->json(
                        ["error" => "Túl nagy kérés."],
                        413,
                    );
                }
            }
            if ($path === "/api/csrf" && $method === "GET") {
                return response()->json(["token" => csrf_token()]);
            }
            if ($path === "/api/login" && $method === "POST") {
                return $this->login($request);
            }
            if ($path === "/api/logout" && $method === "POST") {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->json(["ok" => true]);
            }
            $public =
                in_array(
                    $path,
                    ["/api/public", "/api/slots", "/api/manage"],
                    true,
                ) ||
                ($path === "/api/bookings" && $method === "POST");
            $admin = $request->session()->get("admin", false) === true;
            if (!$public && !$admin) {
                return response()->json(
                    ["error" => "Bejelentkezés szükséges."],
                    401,
                );
            }
            if ($path === "/api/public" && $method === "GET") {
                $s = $this->store->read();
                return response()->json([
                    "settings" => ["name" => $s["settings"]["name"]],
                    "services" => $s["services"],
                    "staff" => $s["staff"],
                ]);
            }
            if ($path === "/api/state" && $method === "GET") {
                return response()->json($this->store->read());
            }
            if ($path === "/api/slots" && $method === "GET") {
                return response()->json($this->slots($request));
            }
            if ($path === "/api/bookings" && $method === "POST") {
                if (
                    $admin &&
                    !$request->boolean("public") &&
                    !hash_equals(
                        csrf_token(),
                        (string) $request->header("X-CSRF-TOKEN"),
                    )
                ) {
                    return response()->json(
                        [
                            "error" =>
                                "Érvénytelen munkamenet. Frissítsd az oldalt.",
                        ],
                        419,
                    );
                }
                $key = "booking:" . $request->ip();
                if (RateLimiter::tooManyAttempts($key, 30)) {
                    return response()->json(
                        ["error" => "Túl sok foglalási kérés. Próbáld később."],
                        429,
                    );
                }
                RateLimiter::hit($key, 60);
                $data = $request->validate([
                    "name" => "required|string|max:150",
                    "phone" => "nullable|string|max:40",
                    "email" => "nullable|email|max:200",
                    "start" => "required|date",
                    "staff" => "required|string",
                    "services" => "required|array|min:1|max:20",
                    "services.*" => "required|string",
                ]);
                $b = $this->store->mutate(function (&$s) use (
                    $data,
                    $admin,
                    $request,
                ) {
                    $b = array_merge($data, [
                        "phone" => $data["phone"] ?? "",
                        "email" => $data["email"] ?? "",
                        "public" => !$admin || $request->boolean("public"),
                    ]);
                    $this->rules->validate($b, $s);
                    $b["id"] = (string) Str::uuid();
                    $b["token"] = bin2hex(random_bytes(24));
                    $b["start"] = CarbonImmutable::parse($b["start"])
                        ->utc()
                        ->toISOString();
                    $b["createdAt"] = $b["updatedAt"] = CarbonImmutable::now()
                        ->utc()
                        ->toISOString();
                    $b["serviceSnapshot"] = array_map(
                        fn($id) => $this->rules->service($s["services"], $id),
                        $b["services"],
                    );
                    $b["status"] = $b["public"]
                        ? $this->rules->status($b["services"], $s)
                        : "confirmed";
                    $s["bookings"][] = $b;
                    $exists = false;
                    foreach ($s["clients"] as $c) {
                        if (
                            $b["phone"]
                                ? $c["phone"] === $b["phone"]
                                : $c["email"] === $b["email"]
                        ) {
                            $exists = true;
                        }
                    }
                    if (!$exists) {
                        $s["clients"][] = [
                            "id" => (string) Str::uuid(),
                            "name" => $b["name"],
                            "phone" => $b["phone"],
                            "email" => $b["email"],
                        ];
                    }
                    return $b;
                });
                return response()->json($b, 201);
            }
            if (
                preg_match('#^/api/bookings/([^/]+)$#', $path, $match) &&
                $method === "PATCH"
            ) {
                $data = $request->validate([
                    "status" =>
                        "sometimes|in:pending,confirmed,cancelled,rejected",
                    "start" => "sometimes|required|date",
                ]);
                $result = $this->store->mutate(function (&$s) use (
                    $match,
                    $data,
                ) {
                    foreach ($s["bookings"] as &$b) {
                        if ($b["id"] === $match[1]) {
                            $next = array_merge($b, $data, [
                                "updatedAt" => CarbonImmutable::now()
                                    ->utc()
                                    ->toISOString(),
                            ]);
                            if (
                                !in_array(
                                    $next["status"],
                                    ["cancelled", "rejected"],
                                    true,
                                )
                            ) {
                                $this->rules->validate($next, $s, $b["id"]);
                            }
                            $b = $next;
                            return $b;
                        }
                    }
                    abort(404, "Nincs ilyen foglalás.");
                });
                return response()->json($result);
            }
            if (
                $path === "/api/manage" &&
                in_array($method, ["GET", "PATCH"], true)
            ) {
                $token = $request->input("token");
                if (
                    !is_string($token) ||
                    !preg_match('/^[a-f0-9]{48}$/', $token)
                ) {
                    return response()->json(
                        ["error" => "Érvénytelen hivatkozás."],
                        404,
                    );
                }
                if ($method === "GET") {
                    $s = $this->store->read();
                    foreach ($s["bookings"] as $b) {
                        if (hash_equals($b["token"], $token)) {
                            return response()->json([
                                "booking" => $b,
                                "services" =>
                                    $b["serviceSnapshot"] ?? $s["services"],
                                "staff" => $s["staff"],
                            ]);
                        }
                    }
                    return response()->json(
                        ["error" => "Érvénytelen hivatkozás."],
                        404,
                    );
                }
                $result = $this->store->mutate(function (&$s) use (
                    $token,
                    $request,
                ) {
                    foreach ($s["bookings"] as &$b) {
                        if (hash_equals($b["token"], $token)) {
                            if (
                                in_array(
                                    $b["status"],
                                    ["cancelled", "rejected"],
                                    true,
                                )
                            ) {
                                throw new InvalidArgumentException(
                                    "Ez a foglalás már nem módosítható.",
                                );
                            }
                            if ($request->boolean("cancel")) {
                                $b["status"] = "cancelled";
                            } else {
                                $next = array_merge($b, [
                                    "start" => $request->input("start"),
                                ]);
                                $this->rules->validate($next, $s, $b["id"]);
                                $b["start"] = CarbonImmutable::parse(
                                    $next["start"],
                                )
                                    ->utc()
                                    ->toISOString();
                                $b["status"] = $this->rules->status(
                                    $b["services"],
                                    $s,
                                );
                            }
                            $b["updatedAt"] = CarbonImmutable::now()
                                ->utc()
                                ->toISOString();
                            return $b;
                        }
                    }
                    abort(404, "Érvénytelen hivatkozás.");
                });
                return response()->json($result);
            }
            if ($path === "/api/config" && $method === "PUT") {
                $data = $request->validate([
                    "settings" => "sometimes|array",
                    "settings.name" => "required_with:settings|string|max:150",
                    "settings.open" =>
                        "required_with:settings|integer|between:0,23",
                    "settings.close" =>
                        "required_with:settings|integer|between:1,24",
                    "settings.confirmation" => "required_with:settings|boolean",
                    "settings.profession" =>
                        "required_with:settings|string|max:80",
                    "settings.channel" =>
                        "required_with:settings|in:email,sms,mindkettő",
                    "settings.reminder" =>
                        "required_with:settings|integer|between:1,8760",
                    "staff" => "sometimes|array|min:1|max:100",
                    "staff.*.id" => "required|string|max:80|distinct",
                    "staff.*.name" => "required|string|max:150",
                    "staff.*.role" => "required|string|max:80",
                    "services" => "sometimes|array|max:500",
                    "services.*.id" => "required|string|max:80|distinct",
                    "services.*.name" => "required|string|max:150",
                    "services.*.price" =>
                        "required|numeric|min:0|max:100000000",
                    "services.*.duration" => "required|integer|between:5,1440",
                    "services.*.staff" => "required|array|min:1",
                    "services.*.staff.*" => "required|string",
                    "services.*.automatic" => "sometimes|boolean",
                    "services.*.phases" => "sometimes|array|size:3",
                    "services.*.phases.*" => "required|integer|min:0",
                    "clients" => "sometimes|array|max:10000",
                    "clients.*.id" => "required|string|max:80|distinct",
                    "clients.*.name" => "required|string|max:150",
                    "clients.*.phone" => "nullable|string|max:40",
                    "clients.*.email" => "nullable|email|max:200",
                ]);
                $this->store->mutate(function (&$s) use ($data) {
                    if (
                        isset($data["settings"]) &&
                        $data["settings"]["open"] >= $data["settings"]["close"]
                    ) {
                        throw new InvalidArgumentException(
                            "A zárás legyen a nyitás után.",
                        );
                    }
                    foreach ($data["services"] ?? [] as $v) {
                        if (
                            array_diff(
                                $v["staff"],
                                array_column(
                                    $data["staff"] ?? $s["staff"],
                                    "id",
                                ),
                            )
                        ) {
                            throw new InvalidArgumentException(
                                "Ismeretlen munkatárs.",
                            );
                        }
                        if (
                            isset($v["phases"]) &&
                            array_sum($v["phases"]) !== $v["duration"]
                        ) {
                            throw new InvalidArgumentException(
                                "A szakaszok összege egyezzen az időtartammal.",
                            );
                        }
                    }
                    // Keep catalog records referenced by bookings; use a separate archive feature to remove them later.
                    foreach (["staff", "services"] as $key) {
                        if (isset($data[$key])) {
                            $needed = [];
                            foreach ($s["bookings"] as $b) {
                                $needed = array_merge(
                                    $needed,
                                    $key === "staff"
                                        ? [$b["staff"]]
                                        : $b["services"],
                                );
                            }
                            if (
                                array_diff(
                                    $needed,
                                    array_column($data[$key], "id"),
                                )
                            ) {
                                throw new InvalidArgumentException(
                                    "Foglalt munkatárs vagy szolgáltatás nem törölhető.",
                                );
                            }
                        }
                    }
                    foreach (
                        ["settings", "staff", "services", "clients"]
                        as $key
                    ) {
                        if (isset($data[$key])) {
                            $s[$key] = $data[$key];
                        }
                    }
                    foreach ($s["clients"] as &$c) {
                        $c["phone"] = $c["phone"] ?? "";
                        $c["email"] = $c["email"] ?? "";
                    }
                });
                return response()->json(["ok" => true]);
            }
            return response()->json(["error" => "Ismeretlen végpont."], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(["error" => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(
                [
                    "error" => collect($e->errors())->flatten()->first(),
                    "errors" => $e->errors(),
                ],
                422,
            );
        }
    }
    private function login(Request $request)
    {
        $key = "login:" . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(
                [
                    "error" =>
                        "Túl sok próbálkozás. Egy perc múlva próbáld újra.",
                ],
                429,
            );
        }
        $hash = config("raero.admin_password_hash");
        if (!$hash) {
            return response()->json(
                [
                    "error" =>
                        "Előbb futtasd a php artisan raero:install parancsot.",
                ],
                503,
            );
        }
        if (!Hash::check((string) $request->input("password", ""), $hash)) {
            RateLimiter::hit($key, 60);
            return response()->json(["error" => "Hibás jelszó."], 401);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put("admin", true);
        return response()->json(["ok" => true]);
    }
    private function slots(Request $request): array
    {
        $date = $request->query("date");
        $ids = array_filter(
            explode(",", (string) $request->query("services", "")),
        );
        $person = $request->query("staff");
        if (
            !is_string($date) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
            !$ids ||
            count($ids) > 20
        ) {
            return [];
        }
        try {
            $day = CarbonImmutable::createFromFormat(
                "!Y-m-d",
                $date,
                "Europe/Budapest",
            );
            if ($day->format("Y-m-d") !== $date) {
                return [];
            }
        } catch (\Exception) {
            return [];
        }
        $s = $this->store->read();
        $slots = [];
        for (
            $minute = $s["settings"]["open"] * 60;
            $minute < $s["settings"]["close"] * 60;
            $minute += 15
        ) {
            $start = $day->addMinutes($minute)->utc()->toISOString();
            foreach ($s["staff"] as $p) {
                if ($person && $person !== "any" && $p["id"] !== $person) {
                    continue;
                }
                try {
                    $this->rules->validate(
                        [
                            "name" => "slot",
                            "phone" => "slot",
                            "services" => array_values($ids),
                            "staff" => $p["id"],
                            "start" => $start,
                        ],
                        $s,
                    );
                    $slots[] = [
                        "start" => $start,
                        "staff" => $p["id"],
                        "time" => sprintf(
                            "%02d:%02d",
                            intdiv($minute, 60),
                            $minute % 60,
                        ),
                    ];
                    break;
                } catch (InvalidArgumentException) {
                }
            }
        }
        return $slots;
    }
}
