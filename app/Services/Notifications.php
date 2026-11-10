<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class Notifications
{
    public function __construct(private Connections $connections) {}
    public function enqueue(array $booking, array $state, string $event): void
    {
        $settings = $state["settings"]; $critical=$event==='group_missed';
        $booking["notificationVersion"] = $settings["notificationVersion"] ?? 1;
        $staff = collect($state["staff"])->firstWhere("id", $booking["staff"]);
        if (
            $event !== "reminder" &&
            ($critical||!empty($settings["notifyGuest"])) &&
            !empty($booking["email"]) && ($critical||in_array($settings["channel"], ["email", "mindkettő"], true))
        ) {
            $this->add(
                $booking,
                $event,
                "email",
                $booking["email"],
                "guest",
                now(),
            );
        }
        if (
            $event !== "reminder" &&
            ($critical||!empty($settings["notifyGuest"])) &&
            !empty($booking["phone"]) &&
            ($critical&&empty($booking["email"])||in_array($settings["channel"], ["sms", "mindkettő"], true))
        ) {
            $this->add(
                $booking,
                $event,
                "sms",
                $booking["phone"],
                "guest",
                now(),
            );
        }
        $provider = $settings["providerEmail"] ?: $staff["email"] ?? "";
        if (
            $event !== "reminder" &&
            !$critical&&!empty($settings["notifyProvider"]) &&
            $provider
        ) {
            $this->add($booking, $event, "email", $provider, "provider", now());
        }
        if (
            PaidFeatures::enabled($state) && !empty($settings["reminderGuest"]) &&
            in_array($booking["status"], ["pending", "confirmed"], true)
        ) {
            $at = CarbonImmutable::parse($booking["start"])->subHours(
                $settings["reminder"],
            );
            if ($at->isFuture()) {
                if (
                    in_array(
                        $settings["channel"],
                        ["email", "mindkettő"],
                        true,
                    ) &&
                    !empty($booking["email"])
                ) {
                    $this->add(
                        $booking,
                        "reminder",
                        "email",
                        $booking["email"],
                        "guest",
                        $at,
                    );
                }
                if (
                    in_array(
                        $settings["channel"],
                        ["sms", "mindkettő"],
                        true,
                    ) &&
                    !empty($booking["phone"])
                ) {
                    $this->add(
                        $booking,
                        "reminder",
                        "sms",
                        $booking["phone"],
                        "guest",
                        $at,
                    );
                }
            }
        }
    }
    public function groupNotice(array $g,array $s):void {
        $staff=collect($s['staff'])->firstWhere('id',$g['staff']);$recipient=$s['settings']['providerEmail']?:($staff['email']??'');if(!$recipient)return;
        $payload=['kind'=>'group_notice','groupId'=>$g['id'],'version'=>$g['minimumCheckedAt'],'recipient'=>$recipient];DB::table('deliveries')->insertOrIgnore(['provider_id'=>app(Tenant::class)->id,'dedup'=>hash('sha256',json_encode($payload)),'payload'=>Crypt::encryptString(json_encode($payload)),'available_at'=>now(),'status'=>'pending','attempts'=>0,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function add(
        array $b,
        string $event,
        string $channel,
        string $recipient,
        string $audience,
        $at,
    ): void {
        $payload = [
            "providerId" => app(Tenant::class)->id,
            "bookingId" => $b["id"],
            "version" => $b["updatedAt"],
            "settingsVersion" => $b["notificationVersion"] ?? 1,
            "event" => $event,
            "channel" => $channel,
            "recipient" => $recipient,
            "audience" => $audience,
        ];
        DB::table("deliveries")->insertOrIgnore([
            "provider_id" => app(Tenant::class)->id,
            "dedup" => hash("sha256", json_encode($payload)),
            "payload" => Crypt::encryptString(json_encode($payload)),
            "available_at" => $at,
            "status" => "pending",
            "attempts" => 0,
            "created_at" => now(),
            "updated_at" => now(),
        ]);
    }
    public function run(int $limit = 50, ?int $providerId = null): array
    {
        $counts = ["sent" => 0, "skipped" => 0, "blocked" => 0, "failed" => 0];
        DB::table("deliveries")
            ->when($providerId !== null, fn($q) => $q->where("provider_id", $providerId))
            ->where("status", "processing")
            ->where("locked_at", "<", now()->subMinutes(10))
            ->update(["status" => "pending", "locked_at" => null]);
        for ($i = 0; $i < $limit; $i++) {
            $job = DB::transaction(function () use ($providerId) {
                $j = DB::table("deliveries")
                    ->when($providerId !== null, fn($q) => $q->where("provider_id", $providerId))
                    ->whereIn("status", ["pending", "blocked"])
                    ->where("available_at", "<=", now())
                    ->orderBy("id")
                    ->lockForUpdate()
                    ->first();
                if ($j) {
                    DB::table("deliveries")
                        ->where("id", $j->id)
                        ->update([
                            "status" => "processing",
                            "locked_at" => now(),
                            "updated_at" => now(),
                        ]);
                }
                return $j;
            });
            if (!$job) {
                break;
            }
            try {
                $p = json_decode(Crypt::decryptString($job->payload), true);
                $previousTenant = app(Tenant::class)->id;
                app(Tenant::class)->id = (int) $job->provider_id;
                if (($p['kind'] ?? '') === 'platform_message') {
                    if (!app(Newsletters::class)->ready()) { $this->finish($job->id, 'blocked', ['error' => 'Hiányzó központi SMTP-kapcsolat.', 'available_at' => now()->addMinutes(5)]); $counts['blocked']++; continue; }
                    $url = rtrim(config('app.url'), '/') . ($p['platformRecipient'] ? '/foadmin' : ($p['recipientType'] === 'guest' ? '/uzenetek' : '/')) . '?provider=' . ($p['recipientType'] === 'provider' ? $p['recipientId'] : 1) . '&platformMessages=1';
                    $template=app(MailTemplates::class)->render('platform_message',['business'=>'Ráérő','name'=>'','details'=>'Új belső üzeneted érkezett.','manage_url'=>$url,'account_url'=>$url,'provider_url'=>$url]);
                    if(!$template['enabled']){$this->finish($job->id,'skipped');$counts['skipped']++;continue;}
                    app(Newsletters::class)->send($p['recipient'],$template['subject'],$template['html'],true);
                    $this->finish($job->id, 'sent', ['attempts' => $job->attempts + 1]); $counts['sent']++; continue;
                }
                if(($p['kind']??'')==='provider_reset'){$status=ProviderPasswords::deliver($p['tokenId']);$this->finish($job->id,$status,$status==='blocked'?['error'=>'Hiányzó központi SMTP-kapcsolat.','available_at'=>now()->addMinutes(5)]:[]);$counts[$status]++;continue;}
                if(($p['kind']??'')==='guest_auth'){$status=GuestPasswords::deliver($p['tokenId']);$this->finish($job->id,$status,$status==='blocked'?['error'=>'Hiányzó központi SMTP-kapcsolat.','available_at'=>now()->addMinutes(5)]:[]);$counts[$status]++;continue;}
                $s = app(SalonStore::class)->read();
                if(($p['kind']??'')==='review_request'){$status=ReviewRequests::deliver($p['bookingId'],$s,!empty($p['automatic']));$this->finish($job->id,$status,$status==='blocked'?['error'=>'Hiányzó központi SMTP-kapcsolat.','available_at'=>now()->addMinutes(5)]:[]);$counts[$status]++;continue;}
                if(($p['kind']??'')==='guest_invite'){$status=ClientInvitations::deliver($p['invitationId'],$s);$this->finish($job->id,$status,$status==='blocked'?['error'=>'Hiányzó központi küldőkapcsolat.','available_at'=>now()->addMinutes(5)]:[]);$counts[$status]++;continue;}
                if(($p['kind']??'')==='group_notice'){
                    $g=collect($s['groupSessions'])->firstWhere('id',$p['groupId']);if(!$g||empty($g['cancelled'])){$this->finish($job->id,'skipped');$counts['skipped']++;continue;}
                    if(!$this->connections->mailReady()){$this->finish($job->id,'blocked',['error'=>'Hiányzó központi SMTP-kapcsolat.','available_at'=>now()->addMinutes(5)]);$counts['blocked']++;continue;}
                    $text=$g['serviceSnapshot']['name'].' · '.CarbonImmutable::parse($g['start'])->setTimezone('Europe/Budapest')->format('Y.m.d. H:i').' – elmarad. '.(($g['cancellationReason']??'')==='minimum'?'A minimumlétszám nem teljesült.':'Szolgáltatói lemondás.');
                    $sent=$this->sendMail($this->connections->all(),$p['recipient'],'Csoportos óra elmarad',$text,'group_missed',rtrim(config('app.url'),'/').'/?provider='.app(Tenant::class)->id);$this->finish($job->id,$sent?'sent':'skipped');$counts[$sent?'sent':'skipped']++;continue;
                }
                if (($p['kind'] ?? '') === 'message') {
                    $message = collect($s['messages'])->firstWhere('id', $p['messageId']);
                    if (!$message) { $this->finish($job->id, 'skipped'); $counts['skipped']++; continue; }
                    if (!$this->connections->mailReady()) { $this->finish($job->id, 'blocked', ['error' => 'Hiányzó küldőkapcsolat.', 'available_at' => now()->addMinutes(5)]); $counts['blocked']++; continue; }
                    $delivered=$this->sendMail($this->connections->all(), $p['recipient'], 'Új belső üzenet — Ráérő',
                        'Új üzeneted érkezett. Beszélgetés: ' . rtrim(config('app.url'), '/') . ($p['guest'] ? '/uzenetek' : '/') . '?provider=' . app(Tenant::class)->id . '&messages=1');
                    if(!$delivered){$this->finish($job->id,'skipped');$counts['skipped']++;continue;}
                    $this->finish($job->id, 'sent', ['attempts' => $job->attempts + 1]); $counts['sent']++; continue;
                }
                $b = collect($s["bookings"])->firstWhere("id", $p["bookingId"]);
                $enabled =
                    $p["event"] === "reminder"
                        ? $s["settings"]["reminderGuest"] ?? false
                        : $s["settings"][
                                $p["audience"] === "guest"
                                    ? "notifyGuest"
                                    : "notifyProvider"
                            ] ?? false;
                if($p['event']==='group_missed')$enabled=true;if($p['event']==='reminder'&&!PaidFeatures::enabled($s))$enabled=false;
                if (
                    !$b ||
                    ($p["event"] === "reminder" &&
                        ($p["settingsVersion"] ?? 1) !==
                            ($s["settings"]["notificationVersion"] ?? 1)) ||
                    $b["updatedAt"] !== $p["version"] ||
                    !$enabled ||
                    ($p["event"] === "reminder" && $b["status"] !== "confirmed")
                ) {
                    $this->finish($job->id, "skipped");
                    $counts["skipped"]++;
                    continue;
                }
                $c = $this->connections->all();
                if (
                    ($p["channel"] === "email" &&
                        !$this->connections->mailReady()) ||
                    ($p["channel"] === "sms" &&
                        (empty($c["twilio_sid"]) ||
                            empty($c["twilio_token"]) ||
                            empty($c["twilio_from"])))
                ) {
                    $this->finish($job->id, "blocked", [
                        "error" => "Hiányzó küldőkapcsolat.",
                        "available_at" => now()->addMinutes(5),
                    ]);
                    $counts["blocked"]++;
                    continue;
                }
                $labels = [
                    "created" => "Új foglalás",
                    "updated" => "Foglalás módosítva",
                    "cancelled" => "Foglalás lemondva", "group_missed"=>"Csoportos óra elmarad",
                    "reminder" => "Közelgő időpont",
                ];
                $statuses = [
                    "pending" => "Jóváhagyásra vár",
                    "confirmed" => "Megerősítve",
                    "cancelled" => "Lemondva",
                    "rejected" => "Elutasítva",
                    "no_show" => "Nem jelent meg",
                ];
                $subject =
                    ($labels[$p["event"]] ?? "Foglalás frissítve") .
                    " — " .
                    $s["settings"]["name"];
                $text =
                    $s["settings"]["name"] .
                    "\n" .
                    $b["name"] .
                    "\n" .
                    CarbonImmutable::parse($b["start"])
                        ->setTimezone("Europe/Budapest")
                        ->format("Y.m.d. H:i") .
                    "\n" .
                    implode(
                        " + ",
                        array_column($b["serviceSnapshot"], "name"),
                    ) .
                    "\n" .
                    ($statuses[$b["status"]] ?? $b["status"]);
                if($p['event']==='group_missed')$text.=($b['cancellationReason']??'')==='group_minimum'?"\nA csoportos óra elmarad, mert a minimumlétszám a határidőig nem teljesült.":"\nA szolgáltató a csoportos órát elmaradtnak jelölte.";
                if ($p["audience"] === "guest") {
                    $text .=
                        "\nFoglalás kezelése: " .
                        rtrim(config("app.url"), "/") .
                        "/modositas?provider=" . app(Tenant::class)->id . "&token=" .
                        $b["token"] .
                        "\nSaját vendégfiók: " .
                        rtrim(config("app.url"), "/") .
                        "/fiokom";
                }
                if ($p["audience"] === "provider") {
                    $text .=
                        "\nSzolgáltatói munkaterület: " .
                        rtrim(config("app.url"), "/") .
                        "/";
                }
                if ($p["channel"] === "email") {
                    config([
                        "mail.default" => "smtp",
                        "mail.mailers.smtp.host" => $c["smtp_host"],
                        "mail.mailers.smtp.port" =>
                            (int) ($c["smtp_port"] ?: 587),
                        "mail.mailers.smtp.username" =>
                            $c["smtp_username"] ?: null,
                        "mail.mailers.smtp.password" =>
                            $c["smtp_password"] ?: null,
                        "mail.mailers.smtp.scheme" =>
                            (int) $c["smtp_port"] === 465 ? "smtps" : "smtp",
                        "mail.from.address" => $c["mail_from"],
                        "mail.from.name" =>
                            $c["mail_name"] ?: $s["settings"]["name"],
                    ]);
                    Mail::purge("smtp");
                    $manage=rtrim(config('app.url'),'/').($p['audience']==='guest'?'/modositas?provider='.app(Tenant::class)->id.'&token='.$b['token']:'/?provider='.app(Tenant::class)->id);
                    $template=app(MailTemplates::class)->render($b['status']==='no_show'?'no_show':$p['event'],['business'=>$s['settings']['name'],'name'=>$b['name'],'details'=>$text,'manage_url'=>$manage,'account_url'=>rtrim(config('app.url'),'/').'/fiokom','provider_url'=>rtrim(config('app.url'),'/').'/foglalas?provider='.app(Tenant::class)->id]);
                    if(!$template['enabled']){$this->finish($job->id,'skipped');$counts['skipped']++;continue;}
                    Mail::to($p['recipient'])->send(new \App\Mail\NotificationMail($template['subject'],$template['html'],filter_var($s['settings']['providerEmail']??'',FILTER_VALIDATE_EMAIL)?$s['settings']['providerEmail']:null,$s['settings']['name']));
                } else {
                    $p["recipient"]=Contact::e164($p["recipient"]);
                    if (!preg_match('/^\+[1-9]\d{7,14}$/', $p["recipient"])) {
                        throw new \RuntimeException(
                            "Az SMS-hez nemzetközi telefonszám szükséges.",
                        );
                    }
                    Http::timeout(20)
                        ->withBasicAuth($c["twilio_sid"], $c["twilio_token"])
                        ->asForm()
                        ->post(
                            "https://api.twilio.com/2010-04-01/Accounts/" .
                                rawurlencode($c["twilio_sid"]) .
                                "/Messages.json",
                            [
                                str_starts_with($c["twilio_from"], "MG")
                                    ? "MessagingServiceSid"
                                    : "From" => $c["twilio_from"],
                                "To" => $p["recipient"],
                                "Body" => $text,
                            ],
                        )
                        ->throw();
                }
                $this->finish($job->id, "sent", [
                    "error" => null,
                    "attempts" => $job->attempts + 1,
                ]);
                $counts["sent"]++;
            } catch (\Throwable $e) {
                // Do not store SMTP/API responses: they can include credentials or personal data.
                $attempts = $job->attempts + 1;
                $this->finish($job->id, $attempts >= 5 ? "failed" : "pending", [
                    "attempts" => $attempts,
                    "error" =>
                        "A küldés nem sikerült. Ellenőrizd a kapcsolatot.",
                    "available_at" => now()->addMinutes(5 * $attempts),
                ]);
                $counts["failed"]++;
            } finally {
                if (isset($previousTenant)) app(Tenant::class)->id = $previousTenant;
            }
        }
        return $counts;
    }
    private function sendMail(array $c, string $recipient, string $subject, string $text,string $event='message',?string $manage=null): bool
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => $c['smtp_host'], 'mail.mailers.smtp.port' => (int) ($c['smtp_port'] ?: 587),
            'mail.mailers.smtp.username' => $c['smtp_username'] ?: null, 'mail.mailers.smtp.password' => $c['smtp_password'] ?: null,
            'mail.mailers.smtp.scheme' => (int) $c['smtp_port'] === 465 ? 'smtps' : 'smtp', 'mail.from.address' => $c['mail_from'], 'mail.from.name' => $c['mail_name'] ?: 'Ráérő']);
        Mail::purge('smtp');
        $state=app(SalonStore::class)->read();$template=app(MailTemplates::class)->render($event,['business'=>$state['settings']['name'],'name'=>'','details'=>$text,'manage_url'=>$manage??rtrim(config('app.url'),'/').'/uzenetek','account_url'=>rtrim(config('app.url'),'/').'/fiokom','provider_url'=>rtrim(config('app.url'),'/').'/foglalas?provider='.app(Tenant::class)->id]);
        if(!$template['enabled'])return false;Mail::to($recipient)->send(new \App\Mail\NotificationMail($template['subject'],$template['html'],$state['settings']['providerEmail']??null,$state['settings']['name']));return true;
    }
    private function finish(int $id, string $status, array $extra = []): void
    {
        DB::table("deliveries")->where("id", $id)->update(
            array_merge(
                [
                    "status" => $status,
                    "locked_at" => null,
                    "updated_at" => now(),
                ],
                $extra,
            ),
        );
    }
}
