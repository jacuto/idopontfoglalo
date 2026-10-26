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
        $settings = $state["settings"];
        $booking["notificationVersion"] = $settings["notificationVersion"] ?? 1;
        $staff = collect($state["staff"])->firstWhere("id", $booking["staff"]);
        if (
            $event !== "reminder" &&
            !empty($settings["notifyGuest"]) &&
            !empty($booking["email"])
        ) {
            $this->add(
                $booking,
                $event,
                "email",
                $booking["email"],
                "guest",
                now(),
            );
        } elseif (
            $event !== "reminder" &&
            !empty($settings["notifyGuest"]) &&
            !empty($booking["phone"]) &&
            in_array($settings["channel"], ["sms", "mindkettő"], true)
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
            !empty($settings["notifyProvider"]) &&
            $provider
        ) {
            $this->add($booking, $event, "email", $provider, "provider", now());
        }
        if (
            !empty($settings["reminderGuest"]) &&
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
    private function add(
        array $b,
        string $event,
        string $channel,
        string $recipient,
        string $audience,
        $at,
    ): void {
        $payload = [
            "bookingId" => $b["id"],
            "version" => $b["updatedAt"],
            "settingsVersion" => $b["notificationVersion"] ?? 1,
            "event" => $event,
            "channel" => $channel,
            "recipient" => $recipient,
            "audience" => $audience,
        ];
        DB::table("deliveries")->insertOrIgnore([
            "dedup" => hash("sha256", json_encode($payload)),
            "payload" => Crypt::encryptString(json_encode($payload)),
            "available_at" => $at,
            "status" => "pending",
            "attempts" => 0,
            "created_at" => now(),
            "updated_at" => now(),
        ]);
    }
    public function run(int $limit = 50): array
    {
        $counts = ["sent" => 0, "skipped" => 0, "blocked" => 0, "failed" => 0];
        DB::table("deliveries")
            ->where("status", "processing")
            ->where("locked_at", "<", now()->subMinutes(10))
            ->update(["status" => "pending", "locked_at" => null]);
        for ($i = 0; $i < $limit; $i++) {
            $job = DB::transaction(function () {
                $j = DB::table("deliveries")
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
                $s = app(SalonStore::class)->read();
                $b = collect($s["bookings"])->firstWhere("id", $p["bookingId"]);
                $enabled =
                    $p["event"] === "reminder"
                        ? $s["settings"]["reminderGuest"] ?? false
                        : $s["settings"][
                                $p["audience"] === "guest"
                                    ? "notifyGuest"
                                    : "notifyProvider"
                            ] ?? false;
                if (
                    !$b ||
                    ($p["settingsVersion"] ?? 1) !==
                        ($s["settings"]["notificationVersion"] ?? 1) ||
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
                    "cancelled" => "Foglalás lemondva",
                    "reminder" => "Közelgő időpont",
                ];
                $statuses = [
                    "pending" => "Jóváhagyásra vár",
                    "confirmed" => "Megerősítve",
                    "cancelled" => "Lemondva",
                    "rejected" => "Elutasítva",
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
                if ($p["audience"] === "guest") {
                    $text .=
                        "\nFoglalás kezelése: " .
                        rtrim(config("app.url"), "/") .
                        "/modositas?token=" .
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
                    Mail::raw(
                        $text,
                        fn($m) => $m->to($p["recipient"])->subject($subject),
                    );
                } else {
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
            }
        }
        return $counts;
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
