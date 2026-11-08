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
                        "/api/quote",
                        "/api/slots", "/api/slots/next",
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
            if ($admin && (int) $request->session()->get("provider_id", 1) !== app(\App\Services\Tenant::class)->id) { $admin = false; }
            if (($path === '/api/bookings' && $method === 'POST') || preg_match('#^/api/bookings/[^/]+$#',$path) || $path === '/api/slots' || $path === '/api/slots/next' || ($path === '/api/imports/bookings' && $method === 'POST')) {
                \App\Services\Subscriptions::guard($this->store->read(),$admin);
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
                    "hasCoupons" => !empty($s['settings']['couponsEnabled']) && collect($s['coupons'])->contains(fn($c)=>$c['active'] && now()->between(CarbonImmutable::parse($c['startsAt']),CarbonImmutable::parse($c['endsAt']))),
                    "hasPasses" => (!empty($s['settings']['passesEnabled']) && collect($s['passes'])->contains(fn($p)=>$p['active'])) || collect($s['clientPasses'])->contains(fn($p)=>$p['remaining']>0 && (empty($p['expiresAt'])||CarbonImmutable::parse($p['expiresAt'])->isFuture())),
                    "groupSessions"=>array_values(array_map(fn($g)=>["id"=>$g["id"],"serviceId"=>$g["serviceId"],"staff"=>$g["staff"],"start"=>$g["start"],"minimumParticipants"=>(int)($g["minimumParticipants"]??0),"minimumDeadline"=>\App\Services\GroupMinimum::deadline($g)->utc()->toISOString(),"remaining"=>$g["serviceSnapshot"]["capacity"]-\App\Services\GroupSessions::booked($g,$s)],array_filter($s["groupSessions"],fn($g)=>!\App\Services\GroupMinimum::unavailable($g,$s)&&CarbonImmutable::parse($g["start"])->isFuture()))),
                    "profile" => $s["profile"],
                    "offers" => array_values(array_filter(empty($s["settings"]["couponsEnabled"])?[]:$s["coupons"], fn($c) => $c["active"] && $c["public"] && now()->between(CarbonImmutable::parse($c["startsAt"]), CarbonImmutable::parse($c["endsAt"])))),
                    "passes" => array_values(array_filter(empty($s["settings"]["passesEnabled"])?[]:$s["passes"], fn($p) => $p["active"])),
                    "reviews" => array_map(fn($v) => array_intersect_key($v, array_flip(["score", "text", "createdAt"])), array_values(array_filter($s["reviews"], fn($v) => $v["direction"] === "guest"))),
                    "settings" => array_intersect_key(
                        $s["settings"],
                        array_flip([
                            "name",
                            "businessMode",
                            "primaryStaffId",
                        ]),
                    ),
                    "services" => array_values(
                        array_filter(
                            $s["services"],
                            fn($v) => empty($v["archived"]) &&
                                count(
                                    array_intersect(
                                        \App\Services\Settings::serviceStaff($s,$v),
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
                            array_flip(["id", "name", "role", "image", "description"]),
                        ),
                        \App\Services\Settings::activeStaff($s),
                    ),
                ]);
            }
            if ($path === "/api/quote" && $method === "POST") return app(FeaturesController::class)->quote($request);
            if (preg_match('#^/api/(profile|coupons|passes|client-passes)(/|$)#', $path) || $path === '/api/clients/search' || preg_match('#^/api/clients/[^/]+/detail$#', $path) || preg_match('#^/api/bookings/[^/]+/review$#', $path)) return app(FeaturesController::class)->handle($request);
            if (
                preg_match(
                    "#^/api/(clients|staff|imports|payments|connections|deliveries|invoices|checkout|subscription)(/|$)#",
                    $path,
                )
            ) {
                return app(WorkspaceController::class)->handle($request);
            }
            if ($path === "/api/state" && $method === "GET") {
                $state=$this->store->read();$state['paymentDues']=\App\Services\PaymentDues::rows($state);$state['clients']=array_values(array_filter($state['clients'],fn($c)=>\App\Services\Clients::visible($c)));
                return response()->json($state);
            }
            if ($path === "/api/slots/next" && $method === "GET") return response()->json($this->nextSlots($request));
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
                    "recurrence"=>"nullable|array:frequency,count","recurrence.frequency"=>"required_with:recurrence|in:daily,weekly,fortnightly,monthly","recurrence.count"=>"required_with:recurrence|integer|between:2,52",
                    "clientId" => "nullable|string|max:80",
                    "coupon" => "nullable|string|max:80",
                    "passId" => "nullable|string|max:80",
                    "name" => "nullable|string|max:150", "allowUnnamed"=>"sometimes|boolean", "rememberUnnamed"=>"sometimes|boolean",
                    "phone" => "nullable|string|max:40",
                    "email" => "nullable|email|max:200",
                    "start" => "required|date",
                    "staff" => "required|string",
                    "services" => "sometimes|array|min:1|max:20",
                    "services.*" => "required|string",
                ]);
                $data=\App\Services\Contact::apply($data);
                $b = $this->store->mutate(function (&$s) use ($data,$admin,$request) {
                    $rule=$data['recurrence']??null;unset($data['recurrence']);
                    if(!$rule)return $this->createBooking($s,$data,$admin,$request);
                    abort_unless($admin&&!$request->boolean('public'),403,'Ismétlődő időpontot a szolgáltató vehet fel.');
                    foreach($data['services']??[] as $id)if(($this->rules->service($s['services'],$id)['bookingMode']??'individual')==='group')throw new InvalidArgumentException('Csoportos órára külön meghirdetett alkalmat válassz.');
                    $series=(string)Str::uuid();$created=[];
                    foreach(\App\Services\RecurringBookings::starts($data['start'],$rule) as $i=>$at){try{$next=$this->createBooking($s,array_replace($data,['start'=>$at]),$admin,$request);}catch(InvalidArgumentException $e){throw new InvalidArgumentException('A sorozat '.CarbonImmutable::parse($at)->setTimezone('Europe/Budapest')->format('Y.m.d. H:i').' alkalmát nem lehet menteni: '.$e->getMessage());}$n=count($s['bookings'])-1;$s['bookings'][$n]['seriesId']=$series;$s['bookings'][$n]['seriesIndex']=$i+1;$created[]=$s['bookings'][$n];}
                    return array_replace($created[0],['seriesCount'=>count($created),'createdIds'=>array_column($created,'id')]);
                });
                return response()->json($b, 201);
            }
            if (
                preg_match('#^/api/bookings/([^/]+)$#', $path, $match) &&
                $method === "PATCH"
            ) {
                $data = $request->validate([
                    "status" =>
                        "sometimes|in:pending,confirmed,cancelled,rejected,no_show",
                    "attendance" => "sometimes|in:attended,no_show,unmarked",
                    "noShowFee"=>"sometimes|integer|min:0|max:100000000",
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
                            if(($data['status']??'')==='no_show')$data['attendance']='no_show';
                            if (isset($data['attendance'])) {
                                $noShow=$data['attendance']==='no_show';
                                $limit=CarbonImmutable::parse($b['start']);if(!$noShow)$limit=$limit->addMinutes(array_sum(array_column($b['serviceSnapshot'],'duration')));
                                if(!in_array($b['status'],['confirmed','no_show'],true)||$limit->isFuture())throw new InvalidArgumentException($noShow?'Nem jelent meg állapot csak megerősített, már elkezdődött időpontnál jelölhető.':'Megjelenést csak megerősített, befejeződött alkalomnál jelölhetsz.');
                                $next['attendance']=$data['attendance'];$next['attendanceAt']=now()->toISOString();
                                if($noShow){$next['status']='no_show';$next['noShowFee']=$data['noShowFee']??($b['noShowFee']??$s['settings']['noShowFee']??0);
                                    if($b['status']==='no_show'&&$next['noShowFee']!==($b['noShowFee']??0))throw new InvalidArgumentException('A lezárt meg nem jelenési díj utólag nem módosítható.');
                                }
                            } elseif(isset($data['noShowFee'])) throw new InvalidArgumentException('Meg nem jelenési díjat a Nem jelent meg állapot jelölésekor adhatsz meg.');
                            if (($data["status"] ?? null) === "confirmed" && $b["status"] === "pending" && CarbonImmutable::parse($next["start"])->isPast()) {
                                throw new InvalidArgumentException("Ez a függő időpont már elmúlt. Előbb adj meg új, jövőbeli időpontot, vagy válaszd az Elutasítás lehetőséget.");
                            }
                            if (isset($data["start"]) && (!empty($b["attendance"]) && $b["attendance"] !== "unmarked")) {
                                throw new InvalidArgumentException("Igazolt látogatás időpontja nem módosítható. Új alkalomhoz vegyél fel új időpontot.");
                            }
                            if (!isset($data["attendance"]) &&
                                !in_array(
                                    $next["status"],
                                    ["cancelled", "rejected"],
                                    true,
                                )
                            ) {
                                \App\Services\GroupSessions::prepare($next,$s);
                                $this->rules->validate($next, $s, $b["id"]);
                            }
                            if(in_array($b['status'],['cancelled','rejected'],true)&&in_array($next['status'],['pending','confirmed'],true)&&!empty($b['pricing']['code']))app(\App\Services\Offers::class)->quote($s,$next,$b['pricing']['code']);
                            app(\App\Services\Offers::class)->transitionPass($s, $b, $next);
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
                                    \App\Services\Offers::total($b) -
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
                if(!$request->boolean('cancel')) \App\Services\Subscriptions::guard($this->store->read(),false);
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
                            if (CarbonImmutable::parse($b["start"])->isPast()) {
                                throw new InvalidArgumentException("Az időpont már elkezdődött. Kérj segítséget a szolgáltatótól.");
                            }
                            if ($request->boolean("cancel")) {
                                $next = array_replace($b, ["status" => "cancelled"]);
                                if(in_array($b['status'],['cancelled','rejected'],true)&&in_array($next['status'],['pending','confirmed'],true)&&!empty($b['pricing']['code']))app(\App\Services\Offers::class)->quote($s,$next,$b['pricing']['code']);
                            app(\App\Services\Offers::class)->transitionPass($s, $b, $next);
                                $b = $next;
                            } else {
                                $next = array_merge($b, [
                                    "start" => $request->input("start"),
                                ]);
                                \App\Services\GroupSessions::prepare($next,$s);
                                $this->rules->validate($next, $s, $b["id"]);
                                if(isset($next["groupSessionId"])){$b["groupSessionId"]=$next["groupSessionId"];$b["serviceSnapshot"]=$next["serviceSnapshot"];}
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
                    "settings.reviewRequestsEnabled"=>"sometimes|boolean","settings.reviewRequestDelayHours"=>"sometimes|integer|between:0,720",
                    "settings.advancedBookingEnabled"=>"sometimes|boolean","settings.servicePhasesEnabled"=>"sometimes|boolean","settings.couponsEnabled"=>"sometimes|boolean","settings.passesEnabled"=>"sometimes|boolean","settings.guestInvitationPolicy"=>"sometimes|in:none,email,sms,email_first,sms_first",
                    "settings.paymentDefaults"=>"sometimes|array:enabled,method,note", "settings.paymentDefaults.enabled"=>"sometimes|boolean", "settings.paymentDefaults.method"=>"sometimes|in:cash,card,transfer", "settings.paymentDefaults.note"=>"nullable|string|max:300",
                    "settings.defaultServiceId"=>"nullable|string|max:80", "settings.skipUnnamedWarning"=>"sometimes|boolean",
                    "settings.slotStep"=>"sometimes|integer|in:5,10,15,20,30,60", "settings.noShowFee"=>"sometimes|integer|min:0|max:100000000",
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
                    "staff.*.id" => "required|string|alpha_dash:ascii|max:80|distinct",
                    "staff.*.name" => "required|string|max:150",
                    "staff.*.description"=>"nullable|string|max:5000",
                    "staff.*.role" => "required|string|max:80",
                    "staff.*.email" => "nullable|email|max:200",
                    "staff.*.phone" => "nullable|string|max:40",
                    "staff.*.archived" => "sometimes|boolean",
                    "services" => "sometimes|array|max:500",
                    "services.*.id" => "required|string|alpha_dash:ascii|max:80|distinct",
                    "services.*.name" => "required|string|max:150",
                    "services.*.description" => "nullable|string|max:3000",
                    "services.*.price" =>
                        "required|numeric|min:0|max:100000000",
                    "services.*.duration" => "required|integer|between:5,1440",
                    "services.*.staff" => "required|array|min:1",
                    "services.*.staff.*" => "required|string",
                    "services.*.automatic" => "sometimes|boolean", "services.*.bookingMode"=>"sometimes|in:individual,parallel,group", "services.*.capacity"=>"sometimes|integer|between:1,100",
                    "services.*.phases" => "sometimes|array|size:3",
                    "services.*.phases.*" => "required|integer|min:0",
                    "clients" => "sometimes|array|max:10000",
                    "clients.*.id" => "required|string|alpha_dash:ascii|max:80|distinct",
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
                    if(isset($data['services']))\App\Services\ProfessionOptions::validateCatalog($s,$data['services'],array_replace($s['settings'],$data['settings']??[]));
                    if(isset($data['services']))foreach($data['services'] as $service){if(($service['bookingMode']??'individual')!=='group'&&collect($s['groupSessions'])->contains(fn($g)=>$g['serviceId']===$service['id']&&empty($g['cancelled'])&&CarbonImmutable::parse($g['start'])->isFuture()))throw new InvalidArgumentException('Előbb mondd le a szolgáltatás meghirdetett csoportos óráit.');if(($service['bookingMode']??'individual')==='group'&&!empty($service['phases']))throw new InvalidArgumentException('Csoportos órához ne adj meg hatóidős szakaszokat.');}
                    if(!empty($data['settings']['defaultServiceId'])){ $catalog=$data['services']??$s['services'];if(!collect($catalog)->contains(fn($v)=>$v['id']===$data['settings']['defaultServiceId']&&empty($v['archived'])&&($v['bookingMode']??'individual')!=='group'))throw new InvalidArgumentException('Alapértelmezettnek aktív, nem csoportos szolgáltatást válassz.');}
                    \App\Services\GroupSessions::checkCatalog(array_replace($s,['settings'=>array_replace($s['settings'],$data['settings']??[])]),$data['services']??$s['services'],$data['staff']??$s['staff']);
                    foreach(['staff','services'] as $kind)if(isset($data[$kind])){foreach($data[$kind] as &$entity){$old=collect($s[$kind])->firstWhere('id',$entity['id'])??[];if($kind==='staff')$entity=\App\Services\Contact::apply($entity,$old);if(isset($entity['description']))$entity['description']=\App\Services\SafeHtml::clean($entity['description']);if(!empty($old['image']))$entity['image']=$old['image'];}unset($entity);}
                    if (isset($data['staff'])) \App\Services\Settings::checkCalendars($s,$data['staff']);
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
                    if (isset($data["clients"])) {
                        $oldClients = collect($s["clients"])->keyBy("id");
                        foreach ($data["clients"] as &$client) {
                            $previous=$oldClients->get($client['id'],[]);if(!$previous&&empty(trim($client['phone']??''))&&empty(trim($client['email']??'')))throw new InvalidArgumentException('Legalább egy telefonszámot vagy emailcímet adj meg.');$client=\App\Services\Contact::apply($client,$previous);\App\Services\Clients::editable($previous,$client);
                            if(!$previous || ($previous['email']??'')!==($client['email']??'') || ($previous['phone']??'')!==($client['phone']??''))\App\Services\Clients::preventDuplicate(array_merge($s['clients'],$data['clients']),$client);
                            $client=array_replace($previous,array_intersect_key($client,array_flip(['id','name','phone','email'])));
                        }
                        foreach ($s["clients"] as $oldClient) if (!in_array($oldClient["id"], array_column($data["clients"], "id"), true)) $data["clients"][]=$oldClient;
                        unset($client);
                        foreach ($s["bookings"] as $booking) {
                            if (!empty($booking["clientId"]) && !in_array($booking["clientId"], array_column($data["clients"], "id"), true)) {
                                throw new InvalidArgumentException("Foglaláshoz kapcsolt vendég nem törölhető. Az adatait a vendégadatlapján szerkesztheted.");
                            }
                        }
                    }
                    if(isset($data['settings'])){$data['settings']['reviewRequestsEnabledAt']=$s['settings']['reviewRequestsEnabledAt']??null;if(!empty($data['settings']['reviewRequestsEnabled'])&&empty($s['settings']['reviewRequestsEnabled']))$data['settings']['reviewRequestsEnabledAt']=now()->toISOString();}
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
        $tenant = app(\App\Services\Tenant::class)->id;
        $hash = $tenant === 1 ? config("raero.admin_password_hash") : \Illuminate\Support\Facades\DB::table("providers")->where("id", $tenant)->value("password_hash");
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
        $request->session()->put("provider_id", $tenant);
        return response()->json(["ok" => true]);
    }
    private function createBooking(array &$s,array $data,bool $admin,Request $request):array
    {
                    $b = array_merge($data, [
                        "phone" => $data["phone"] ?? "",
                        "email" => $data["email"] ?? "",
                        "public" => !$admin || $request->boolean("public"),
                    ]);
                    $b["guestId"] = $request->session()->get("guest_id");$b["locale"]=in_array($request->header("X-App-Language"),["hu","en","de"],true)?$request->header("X-App-Language"):"hu";
                    \App\Services\Blacklist::guard($s,$b);
                    if(empty($b['services'])&&!$b['public']){$default=collect($s['services'])->first(fn($v)=>$v['id']===($s['settings']['defaultServiceId']??null)&&empty($v['archived'])&&($v['bookingMode']??'individual')!=='group'&&in_array($b['staff'],\App\Services\Settings::serviceStaff($s,$v),true))??collect($s['services'])->first(fn($v)=>empty($v['archived'])&&($v['bookingMode']??'individual')!=='group'&&in_array($b['staff'],\App\Services\Settings::serviceStaff($s,$v),true));if($default)$b['services']=[$default['id']];}
                    if(empty($b['services']))throw new InvalidArgumentException('Válassz szolgáltatást.');
                    if(empty(trim($b['name']??''))&&empty($data['clientId'])){
                        if($b['public'])throw new InvalidArgumentException('Add meg a vendég nevét.');
                        if(empty($data['allowUnnamed'])&&empty($s['settings']['skipUnnamedWarning']))throw new InvalidArgumentException('Nem adtad meg a vendég nevét. Erősítsd meg a név nélküli mentést.');
                        $b['name']='Név nélküli vendég';$b['anonymous']=true;
                        if(!empty($data['rememberUnnamed']))$s['settings']['skipUnnamedWarning']=true;
                    }
                    unset($b['allowUnnamed'],$b['rememberUnnamed']);
                    \App\Services\GroupSessions::prepare($b,$s);
                    $previousClientIds=array_column($s['clients'],'id');
                    \App\Services\Clients::attach($s, $b, $data["clientId"] ?? null, !$b["public"]);
                    \App\Services\Subscriptions::guard($s,!$b["public"]);
                    $this->rules->validate($b, $s);
                    $b["id"] = (string) Str::uuid();
                    $b["token"] = bin2hex(random_bytes(24));
                    $b["start"] = CarbonImmutable::parse($b["start"])
                        ->utc()
                        ->toISOString();
                    $b["createdAt"] = $b["updatedAt"] = CarbonImmutable::now()
                        ->utc()
                        ->toISOString();
                    $b["serviceSnapshot"] = $b["serviceSnapshot"]??array_map(
                        fn($id) => array_replace($this->rules->service($s["services"], $id),["staff"=>\App\Services\Settings::serviceStaff($s,$this->rules->service($s["services"],$id))]),
                        $b["services"],
                    );
                    $b["status"] = $b["public"]
                        ? $this->rules->status($b["services"], $s)
                        : "confirmed";
                    $b["pricing"] = app(\App\Services\Offers::class)->quote($s, $b, $data["coupon"] ?? null);
                    app(\App\Services\Offers::class)->reservePass($s, $b, $data["passId"] ?? null, !$b["public"]);
                    $s["bookings"][] = $b;
                    if(!$b['public']&&!empty($b['clientId'])&&!in_array($b['clientId'],$previousClientIds,true)){ $client=collect($s['clients'])->firstWhere('id',$b['clientId']);if($client)\App\Services\ClientInvitations::queue($s,$client);}
                    app(\App\Services\Notifications::class)->enqueue(
                        $b,
                        $s,
                        "created",
                    );
                    return $b;
    }
    private function nextSlots(Request $request):array {
        $s=$this->store->read();$ids=array_filter(explode(',',(string)$request->query('services','')));if(!$ids||count($ids)>20)return ['date'=>null,'slots'=>[]];
        $active=array_filter($s['services'],fn($v)=>empty($v['archived']));if(array_diff($ids,array_column($active,'id')))return ['date'=>null,'slots'=>[]];$person=$request->query('staff');$eligible=array_filter(\App\Services\Settings::activeStaff($s),fn($p)=>(!$person||$person==='any'||$person===$p['id'])&&!array_filter($ids,fn($id)=>!in_array($p['id'],\App\Services\Settings::serviceStaff($s,$this->rules->service($s['services'],$id)),true)));if(!$eligible)return ['date'=>null,'slots'=>[]];
        $today=CarbonImmutable::now('Europe/Budapest')->startOfDay();$group=collect($s['services'])->contains(fn($v)=>in_array($v['id'],$ids,true)&&($v['bookingMode']??'individual')==='group');
        $dates=$group?array_values(array_unique(array_map(fn($g)=>CarbonImmutable::parse($g['start'])->setTimezone('Europe/Budapest')->format('Y-m-d'),array_filter($s['groupSessions'],fn($g)=>!\App\Services\GroupMinimum::unavailable($g,$s)&&in_array($g['serviceId'],$ids,true)&&CarbonImmutable::parse($g['start'])->isFuture())))):array_map(fn($d)=>$today->addDays($d)->format('Y-m-d'),range(0,365));sort($dates);
        $s['_googleBusy']=app(\App\Services\GoogleCalendar::class)->busy($s,(int)$request->session()->get('guest_id',0));
        foreach($dates as $date){[$open,$close]=\App\Services\Availability::hours($s,$date);if($open===null)continue;if(!$group){$free=array_filter($eligible,fn($p)=>!array_filter(\App\Services\Availability::blocked($s,$date,$p['id']),fn($range)=>$range[0]<=$open&&$range[1]>=$close));if(!$free)continue;}$probe=$request->duplicate(array_replace($request->query->all(),['date'=>$date]));$slots=$this->slots($probe,$s);if($slots)return ['date'=>$date,'slots'=>$slots];}
        return ['date'=>null,'slots'=>[],'searchedDays'=>$group?null:366];
    }
    private function slots(Request $request,?array $cached=null): array
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
        $s = $cached??$this->store->read();
        $dayStart=$day->getTimestamp();$dayEnd=$day->addDay()->getTimestamp();
        $s['bookings']=array_values(array_filter($s['bookings'],function($b)use($dayStart,$dayEnd,$s){$start=CarbonImmutable::parse($b['start'])->getTimestamp();$snapshot=$b['serviceSnapshot']??array_values(array_filter($s['services'],fn($service)=>in_array($service['id'],$b['services'],true)));$duration=array_sum(array_column($snapshot,'duration'))*60;return !in_array($b['status'],['cancelled','rejected'],true)&&$start<$dayEnd&&$start+$duration>$dayStart;}));
        $s['_googleBusy']=app(\App\Services\GoogleCalendar::class)->busy($s,(int)$request->session()->get('guest_id',0));
        $slots = [];
        [$open, $close] = \App\Services\Availability::hours($s, $date);
        if ($open === null) return [];
        if(collect($s['services'])->contains(fn($v)=>in_array($v['id'],$ids,true)&&($v['bookingMode']??'individual')==='group')){
            if(count($ids)!==1)return [];
            foreach($s['groupSessions'] as $g){if(\App\Services\GroupMinimum::unavailable($g,$s)||$g['serviceId']!==array_values($ids)[0]||($person&&$person!=='any'&&$person!==$g['staff'])||CarbonImmutable::parse($g['start'])->setTimezone('Europe/Budapest')->format('Y-m-d')!==$date)continue;
                try{$b=['name'=>'slot','phone'=>'slot','start'=>$g['start'],'staff'=>$g['staff'],'services'=>array_values($ids),'public'=>!$request->session()->get('admin'),'guestId'=>$request->session()->get('guest_id')];\App\Services\GroupSessions::prepare($b,$s);$this->rules->validate($b,$s);$slots[]=['start'=>$g['start'],'staff'=>$g['staff'],'time'=>CarbonImmutable::parse($g['start'])->setTimezone('Europe/Budapest')->format('H:i'),'groupSessionId'=>$g['id'],'remaining'=>$g['serviceSnapshot']['capacity']-\App\Services\GroupSessions::booked($g,$s)];}catch(InvalidArgumentException){}
            }return $slots;
        }
        $step=(int)($s['settings']['slotStep']??10);
        $first=$step===60?(int)(ceil($open/60)*60):$open;
        for ($minute = $first; $minute < $close; $minute += $step) {
            $start = $day->addMinutes($minute)->utc()->toISOString();
            foreach (\App\Services\Settings::activeStaff($s) as $p) {
                if ($person && $person !== "any" && $p["id"] !== $person) {
                    continue;
                }
                try {
                    $this->rules->validate(
                        [
                            "name" => "slot",
                            "public"=>$request->boolean('public')||!$request->session()->get('admin'),"guestId"=>(int)$request->session()->get('guest_id',0),
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
