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
                    rtrim($origin, "/") !== $request->getSchemeAndHttpHost() &&
                    !$request->attributes->get("embedAllowed", false)
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
                    [
                        "/api/public",
                        "/api/slots",
                        "/api/manage",
                        "/api/manage/checkout",
                    ],
                    true,
                ) ||
                ($path === "/api/bookings" && $method === "POST");
            $admin =
                !$request->attributes->get("embedAllowed", false) &&
                $request->session()->get("admin", false) === true;
            if (!$public && !$admin) {
                return response()->json(
                    ["error" => "Bejelentkezés szükséges."],
                    401,
                );
            }
            if ($path === "/api/manage/checkout" && $method === "POST") {
                $token = $request->input("token");
                if (
                    !is_string($token) ||
                    !preg_match('/^[a-f0-9]{48}$/', $token)
                ) {
                    abort(404);
                }
                $b = collect($this->store->read()["bookings"])->firstWhere(
                    "token",
                    $token,
                );
                if (!$b) {
                    abort(404);
                }
                if (
                    RateLimiter::tooManyAttempts(
                        "checkout:" . $request->ip(),
                        10,
                    )
                ) {
                    abort(429);
                }
                RateLimiter::hit("checkout:" . $request->ip(), 60);
                return response()->json(
                    app(\App\Services\Payments::class)->checkout($b["id"]),
                );
            }
            if ($path === "/api/public" && $method === "GET") {
                $s = $this->store->read();
                return response()->json([
                    "settings" => array_intersect_key(
                        $s["settings"],
                        array_flip([
                            "name",
                            "businessMode",
                            "primaryStaffId",
                            "googleAnalyticsId",
                            "googleTagManagerId",
                            "googleAdsId",
                            "googleAdsLabel",
                            "facebookPixelId",
                        ]),
                    ),
                    "services" => array_values(
                        array_filter(
                            $s["services"],
                            fn($v) => empty($v["archived"]) &&
                                count(
                                    array_intersect(
                                        $v["staff"],
                                        array_column(
                                            \App\Services\Settings::activeStaff(
                                                $s,
                                            ),
                                            "id",
                                        ),
                                    ),
                                ) > 0,
                        ),
                    ),
                    "staff" => array_map(
                        fn($p) => array_intersect_key(
                            $p,
                            array_flip(["id", "name", "role"]),
                        ),
                        \App\Services\Settings::activeStaff($s),
                    ),
                ]);
            }
            if (
                preg_match(
                    "#^/api/(clients|staff|imports|payments|connections|deliveries|invoices|checkout|subscription)(/|$)#",
                    $path,
                )
            ) {
                return app(WorkspaceController::class)->handle($request);
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
                    $b["guestId"] = $request->session()->get("guest_id");
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
                    app(\App\Services\Notifications::class)->enqueue(
                        $b,
                        $s,
                        "created",
                    );
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
                            app(\App\Services\Notifications::class)->enqueue(
                                $b,
                                $s,
                                in_array(
                                    $b["status"],
                                    ["cancelled", "rejected"],
                                    true,
                                )
                                    ? "cancelled"
                                    : "updated",
                            );
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
                                "paymentReady" => !empty(
                                    app(
                                        \App\Services\Connections::class,
                                    )->all()["stripe_key"]
                                ),
                                "balance" => max(
                                    0,
                                    array_sum(
                                        array_column(
                                            $b["serviceSnapshot"],
                                            "price",
                                        ),
                                    ) -
                                        collect($s["payments"])
                                            ->where("bookingId", $b["id"])
                                            ->where("status", "paid")
                                            ->sum(
                                                fn($p) => $p["amount"] -
                                                    ($p["refundedAmount"] ?? 0),
                                            ),
                                ),
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
                            app(\App\Services\Notifications::class)->enqueue(
                                $b,
                                $s,
                                $request->boolean("cancel")
                                    ? "cancelled"
                                    : "updated",
                            );
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
                    "settings.openingTime" => "sometimes|date_format:H:i",
                    "settings.closingTime" => "sometimes|date_format:H:i",
                    "settings.businessMode" => "sometimes|in:solo,team",
                    "settings.primaryStaffId" => "sometimes|string",
                    "settings.providerEmail" => "nullable|email|max:200",
                    "settings.notifyGuest" => "sometimes|boolean",
                    "settings.notifyProvider" => "sometimes|boolean",
                    "settings.reminderGuest" => "sometimes|boolean",
                    "settings.billingName" => "nullable|string|max:200",
                    "settings.billingTaxNumber" => "nullable|string|max:30",
                    "settings.billingCountry" => "sometimes|string|size:2",
                    "settings.billingZip" => "nullable|string|max:20",
                    "settings.billingCity" => "nullable|string|max:100",
                    "settings.billingAddress" => "nullable|string|max:250",
                    "settings.invoiceProvider" =>
                        "sometimes|in:none,billingo,szamlazz",
                    "settings.autoInvoice" => "sometimes|boolean",
                    "settings.invoiceVat" => "sometimes|in:AAM,27%,18%,5%,0%",
                    "settings.googleAnalyticsId" => [
                        "nullable",
                        "regex:/^G-[A-Z0-9]+$/",
                    ],
                    "settings.googleTagManagerId" => [
                        "nullable",
                        "regex:/^GTM-[A-Z0-9]+$/",
                    ],
                    "settings.googleAdsId" => [
                        "nullable",
                        "regex:/^AW-[0-9]+$/",
                    ],
                    "settings.googleAdsLabel" => "nullable|string|max:100",
                    "settings.facebookPixelId" => [
                        "nullable",
                        "regex:/^[0-9]{5,30}$/",
                    ],
                    "settings.embedOrigins" => "sometimes|array|max:30",
                    "settings.embedOrigins.*" =>
                        "required|url:http,https|max:250",

                    "settings.name" => "required_with:settings|string|max:150",
                    "settings.open" => "sometimes|numeric|between:0,23.99",
                    "settings.close" => "sometimes|numeric|between:0.01,24",
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
                    "staff.*.email" => "nullable|email|max:200",
                    "staff.*.phone" => "nullable|string|max:40",
                    "staff.*.archived" => "sometimes|boolean",
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
                        \App\Services\Settings::minutes(
                            $data["settings"]["openingTime"] ??
                                sprintf(
                                    "%02d:00",
                                    $data["settings"]["open"] ?? 8,
                                ),
                        ) >=
                            \App\Services\Settings::minutes(
                                $data["settings"]["closingTime"] ??
                                    sprintf(
                                        "%02d:00",
                                        $data["settings"]["close"] ?? 20,
                                    ),
                            )
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
                    $notificationChanged = false;
                    if (isset($data["settings"])) {
                        foreach (
                            [
                                "reminder",
                                "channel",
                                "notifyGuest",
                                "notifyProvider",
                                "providerEmail",
                                "reminderGuest",
                            ]
                            as $k
                        ) {
                            if (
                                array_key_exists($k, $data["settings"]) &&
                                $data["settings"][$k] !== $s["settings"][$k]
                            ) {
                                $notificationChanged = true;
                            }
                        };
                    }
                    foreach (
                        ["settings", "staff", "services", "clients"]
                        as $key
                    ) {
                        if (isset($data[$key])) {
                            $s[$key] =
                                $key === "settings"
                                    ? array_replace($s["settings"], $data[$key])
                                    : $data[$key];
                        }
                    }
                    if (
                        isset($data["settings"]) &&
                        $s["settings"]["businessMode"] === "solo" &&
                        !in_array(
                            $s["settings"]["primaryStaffId"],
                            array_column(
                                array_filter(
                                    $s["staff"],
                                    fn($p) => empty($p["archived"]),
                                ),
                                "id",
                            ),
                            true,
                        )
                    ) {
                        throw new InvalidArgumentException(
                            "Válassz aktív elsődleges munkatársat.",
                        );
                    }
                    if ($notificationChanged) {
                        $s["settings"]["notificationVersion"] =
                            ($s["settings"]["notificationVersion"] ?? 1) + 1;
                        foreach ($s["bookings"] as $b) {
                            if (
                                $b["status"] === "confirmed" &&
                                \Carbon\CarbonImmutable::parse(
                                    $b["start"],
                                )->isFuture()
                            ) {
                                app(
                                    \App\Services\Notifications::class,
                                )->enqueue($b, $s, "reminder");
                            }
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
            $minute = \App\Services\Settings::minutes(
                $s["settings"]["openingTime"],
            );
            $minute <
            \App\Services\Settings::minutes($s["settings"]["closingTime"]);
            $minute += 15
        ) {
            $start = $day->addMinutes($minute)->utc()->toISOString();
            foreach (\App\Services\Settings::activeStaff($s) as $p) {
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
