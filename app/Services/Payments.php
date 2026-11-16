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
    public static function cardReady(array $s):bool{$c=app(Connections::class)->all();return match($s['settings']['paymentProvider']??'stripe'){'simplepay'=>!empty($c['simplepay_merchant'])&&!empty($c['simplepay_secret']),'barion'=>!empty($c['barion_pos_key'])&&!empty($c['barion_payee']),default=>!empty($c['stripe_key'])&&!empty($c['stripe_webhook_secret'])};}
    public static function szepReady():bool{$c=app(Connections::class)->all();return !empty($c['simplepay_szep_merchant'])&&!empty($c['simplepay_szep_secret']);}
    public static function ready(array $s):bool{return BookingPrepayments::enabled($s)&&((BookingPrepayments::allows($s,'card')&&self::cardReady($s))||(BookingPrepayments::allows($s,'szep')&&self::szepReady()));}
    public function checkout(string $bookingId,string $mode="full",string $rail="card"):array {
        $s=$this->store->read();abort_unless(in_array($rail,['card','szep'],true),422);abort_unless(BookingPrepayments::allows($s,$rail),422,'Ezt a fizetési módot a szolgáltató nem engedélyezte az előrefizetéshez.');if($rail==='szep')return app(SimplePay::class)->checkout($bookingId,$mode,true);abort_unless(self::cardReady($s),422,'A szolgáltató bankkártyás kapcsolata még nincs teljesen beállítva.');if(($s['settings']['paymentProvider']??'stripe')==='simplepay')return app(SimplePay::class)->checkout($bookingId,$mode);if(($s['settings']['paymentProvider']??'stripe')==='barion')return app(Barion::class)->checkout($bookingId,$mode);
        $key=$this->connections->all()['stripe_key']??null;abort_unless($key,422,'A szolgáltató Stripe-kapcsolata nincs beállítva.');
        $order=$this->store->mutate(function(&$s)use($bookingId,$mode){$b=collect($s['bookings'])->firstWhere('id',$bookingId);abort_unless($b,404);abort_unless(in_array($b['status'],['pending','confirmed'],true),422,'Ez a foglalás nem fizethető.');$amount=BookingPrepayments::amount($s,$b,$mode);
            foreach($s['checkouts'] as $old)if(($old['type']??'')==='booking'&&$old['bookingId']===$bookingId&&($old['status']??'pending')!=='paid'&&(($old['status']??'')==='unknown'||\Carbon\CarbonImmutable::parse($old['createdAt'])->gt(now()->subMinutes(35)))){if(($old['gateway']??'stripe')==='stripe'&&$old['amount']===$amount&&($old['status']??'pending')==='pending'&&!empty($old['url']))return ['reuse'=>$old['url']];if(!in_array($old['status']??'pending',['paid','failed'],true))throw new InvalidArgumentException('Ehhez a foglaláshoz már indult fizetés. Előbb ellenőrizd vagy fejezd be a korábbi tranzakciót.');}
            $ref='stripe-start-'.Str::uuid();$s['checkouts'][$ref]=['type'=>'booking','gateway'=>'stripe','bookingId'=>$bookingId,'amount'=>$amount,'status'=>'creating','createdAt'=>now()->toISOString()];return ['ref'=>$ref,'amount'=>$amount,'booking'=>$b,'business'=>$s['settings']['name']];
        });if(isset($order['reuse']))return ['url'=>$order['reuse']];$b=$order['booking'];$base=rtrim(config('app.url'),'/').'/modositas?provider='.app(Tenant::class)->id.'&token='.$b['token'];
        try{$response=Http::timeout(20)->withToken($key)->withHeaders(['Idempotency-Key'=>$order['ref']])->asForm()->post('https://api.stripe.com/v1/checkout/sessions',['mode'=>'payment','expires_at'=>now()->addMinutes(30)->timestamp,'line_items'=>[['price_data'=>['currency'=>'huf','product_data'=>['name'=>($mode==='deposit'?'Előleg – ':'Szolgáltatások – ').$order['business']],'unit_amount'=>$order['amount']*100],'quantity'=>1]],'success_url'=>$base.'&payment=success','cancel_url'=>$base,'client_reference_id'=>$b['id'],'metadata'=>['booking_id'=>$b['id']],'billing_address_collection'=>'required',...!empty($b['email'])?['customer_email'=>$b['email']]:[]])->throw();$session=$response->json();abort_unless(!empty($session['id'])&&parse_url($session['url']??'',PHP_URL_HOST)==='checkout.stripe.com'&&parse_url($session['url']??'',PHP_URL_SCHEME)==='https',422,'Érvénytelen Stripe-válasz.');
            $this->store->mutate(function(&$s)use($session,$order){$s['checkouts'][$session['id']]=array_replace($s['checkouts'][$order['ref']],['status'=>'pending','url'=>$session['url']]);unset($s['checkouts'][$order['ref']]);});return ['url'=>$session['url']];
        }catch(\Throwable $e){SystemIssues::capture($e,'stripe',app(Tenant::class)->id);$this->store->mutate(function(&$s)use($order){if(isset($s['checkouts'][$order['ref']]))$s['checkouts'][$order['ref']]['status']='unknown';});throw new InvalidArgumentException('A fizetés indítása nem igazolható. Ellenőrizd a Stripe-fiókot, mielőtt új fizetést indítasz.');}
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
        if(PlatformAccess::mode($s)!=='active')throw new InvalidArgumentException('Ehhez a hozzáférési módhoz nem indítható fizetős előfizetés. Keresd az üzemeltetőt.');
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
                    hash("sha256", config("app.url") . app(Tenant::class)->id . now()->format("Y-m-d").($s["subscription"]["extraCalendars"]??0).($s["subscription"]["plan"]??"basic")),
            ])
            ->asForm()
            ->post("https://api.stripe.com/v1/checkout/sessions", [
                "mode" => "subscription",
                "metadata" => ["provider_id" => (string) app(Tenant::class)->id],
                "subscription_data" => ["metadata" => ["provider_id" => (string) app(Tenant::class)->id], ...(!empty($s["subscription"]["trialEndsAt"]) && \Carbon\CarbonImmutable::parse($s["subscription"]["trialEndsAt"])->isFuture() ? ["trial_end" => \Carbon\CarbonImmutable::parse($s["subscription"]["trialEndsAt"])->timestamp] : [])],
                "line_items" => [((($s["subscription"]["plan"]??"basic")==="pro")?["price_data"=>["currency"=>"huf","unit_amount"=>PlanPricing::base($s)*100,"product"=>$p["product"],"recurring"=>["interval"=>"month"]],"quantity"=>1]:["price"=>$price,"quantity"=>1]), ...(!empty($s["subscription"]["extraCalendars"])?[["price_data"=>["currency"=>"huf","unit_amount"=>200000,"product"=>$p["product"],"recurring"=>["interval"=>"month"]],"quantity"=>(int)$s["subscription"]["extraCalendars"]]]:[])],
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
        if($platform&&SmsTopups::webhook($event,$object))return response()->json(['received'=>true]);
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
                    if(!empty($s['subscription']['cancelAt'])&&\Carbon\CarbonImmutable::parse($s['subscription']['cancelAt'])->lte(now()))$s['subscription']['billingRestartAt']=now()->toISOString();
                    $s["subscription"] = array_replace($s["subscription"], [
                        "id" => $object["subscription"],
                        "customer" => $object["customer"], "cancelAt"=>null,"renewalEnabled"=>true,
                        "status" => ($event["created"] ?? 0) >= ($s["subscription"]["lastEventAt"] ?? 0) ? (($object["payment_status"]??"")==="no_payment_required"?"trialing":"active") : ($s["subscription"]["status"] ?? "active"),
                        "lastEventAt" => max($event["created"] ?? 0, $s["subscription"]["lastEventAt"] ?? 0),
                    ]);
                    return;
                }
                if (
                    $platform ||
                    $checkout["type"] !== "booking" || ($checkout["gateway"]??"stripe")!=="stripe" ||
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
                $s['checkouts'][$object['id']]['status']='paid';
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
            if ($platform && ($event['type'] ?? '') === 'invoice.paid' && ($object['paid'] ?? false) === true && ($object['currency'] ?? '') === 'huf' && ($object['amount_paid'] ?? 0) === Subscriptions::amountForPeriod($s,isset($object['lines']['data'][0]['period']['start'])?\Carbon\CarbonImmutable::createFromTimestamp($object['lines']['data'][0]['period']['start'])->toISOString():now()->toISOString())*100) {
                $reference = 'stripe-invoice:' . $object['id'];
                $end = $object['lines']['data'][0]['period']['end'] ?? $object['period_end'] ?? null;
                if (!$end) throw new InvalidArgumentException('A Stripe nem adta meg az előfizetési időszak végét.');
                \Illuminate\Support\Facades\DB::table('platform_payments')->insertOrIgnore(['provider_id' => app(Tenant::class)->id, 'reference' => $reference, 'amount' => (int)($object['amount_paid']/100), 'method' => 'card', 'paid_at' => \Carbon\CarbonImmutable::createFromTimestamp($object['status_transitions']['paid_at']??time())->setTimezone(config('app.timezone')), 'period_start'=>isset($object['lines']['data'][0]['period']['start'])?\Carbon\CarbonImmutable::createFromTimestamp($object['lines']['data'][0]['period']['start'])->setTimezone(config('app.timezone')):null, 'details'=>json_encode(['source'=>'stripe','stripeInvoice'=>$object['id'],'stripeSubscription'=>$object['subscription']??$object['parent']['subscription_details']['subscription']??null,'stripeCustomer'=>$object['customer']??null,'paymentIntent'=>$object['payment_intent']??null,'eventId'=>$event['id']??null,'currency'=>'HUF','amountPaid'=>(int)($object['amount_paid']/100)]), 'period_end' => \Carbon\CarbonImmutable::createFromTimestamp($end)->setTimezone(config('app.timezone')), 'created_at' => now(), 'updated_at' => now()]);
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
                    $cancel=$object['cancel_at']??$object['current_period_end']??$object['items']['data'][0]['current_period_end']??null;
                    if(!empty($object['cancel_at_period_end'])&&$cancel){$s['subscription']['cancelAt']=\Carbon\CarbonImmutable::createFromTimestamp($cancel)->toISOString();$s['subscription']['renewalEnabled']=false;}elseif(($object['status']??'')==='canceled'){$s['subscription']['cancelAt']=\Carbon\CarbonImmutable::createFromTimestamp($object['ended_at']??time())->toISOString();$s['subscription']['renewalEnabled']=false;}else{$s['subscription']['cancelAt']=null;$s['subscription']['renewalEnabled']=true;}
                    $s["subscription"]["lastEventAt"] = $event["created"] ?? time();
                }
            }
        });
        $this->store->mutate(function(&$s){ Subscriptions::refresh($s); });
        return response()->json(["received" => true]);
    }
}
