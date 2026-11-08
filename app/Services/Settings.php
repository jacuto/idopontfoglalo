<?php
namespace App\Services;
final class Settings
{
    public static function defaults(): array
    {
        return [
            "businessMode" => "team",
            "primaryStaffId" => "anna",
            "advancedBookingEnabled"=>false,"servicePhasesEnabled"=>false,"couponsEnabled"=>true,"passesEnabled"=>true,"guestInvitationPolicy"=>"none",
            "paymentDefaults"=>["enabled"=>true,"method"=>"cash","note"=>""],
            "slotStep"=>10,"noShowFee"=>0,"defaultServiceId"=>null,"skipUnnamedWarning"=>false,
            "openingTime" => "08:00",
            "closingTime" => "20:00",
            "providerEmail" => "",
            "notificationVersion" => 1,
            "notifyGuest" => false,
            "notifyProvider" => false,
            "reminderGuest" => false,
            "billingName" => "",
            "billingTaxNumber" => "",
            "billingCountry" => "HU",
            "billingZip" => "",
            "billingCity" => "",
            "billingAddress" => "",
            "invoiceProvider" => "none",
            "autoInvoice" => false,
            "invoiceVat" => "AAM",
            "embedOrigins" => [],
            "googleAnalyticsId" => "",
            "googleTagManagerId" => "",
            "googleAdsId" => "",
            "googleAdsLabel" => "",
            "facebookPixelId" => "",
        ];
    }
    public static function upgrade(array $state): array
    {
        $old = $state["settings"] ?? [];
        $defaults = self::defaults();
        $defaults["openingTime"] = sprintf(
            "%02d:%02d",
            floor($old["open"] ?? 8),
            round(fmod((float) ($old["open"] ?? 8), 1) * 60),
        );
        $defaults["closingTime"] = sprintf(
            "%02d:%02d",
            floor($old["close"] ?? 20),
            round(fmod((float) ($old["close"] ?? 20), 1) * 60),
        );
        $defaults["primaryStaffId"] = $state["staff"][0]["id"] ?? "";
        $state["settings"] = array_replace($defaults, $old);
        $state += [
            "availability" => Availability::defaults(), "groupSessions"=>[],
            "payments" => [],
            "checkouts" => [],
            "invoiceRequests" => [],
            "subscription" => [],
            "reviews" => [],
            "coupons" => [],
            "passes" => [],
            "clientPasses" => [],
            "messages" => [],
            "profile" => [],
        ];
        if (($state['subscription']['status'] ?? '') === 'trialing' && !empty($state['subscription']['trialEndsAt']) && \Carbon\CarbonImmutable::parse($state['subscription']['trialEndsAt'])->isPast()) $state['subscription']['status'] = 'past_due';
        if (($state['subscription']['status'] ?? '') === 'active' && !empty($state['subscription']['paidUntil']) && empty($state['subscription']['id']) && \Carbon\CarbonImmutable::parse($state['subscription']['paidUntil'])->isPast()) $state['subscription']['status'] = 'past_due';
        $guestIds=array_values(array_filter(array_column($state['clients']??[],'guestId')));
        if($guestIds){
            $accounts=\Illuminate\Support\Facades\DB::table('guest_accounts')->whereIn('id',$guestIds)->get()->keyBy('id');
            foreach($state['clients'] as &$client)if(Clients::visible($client)&&!empty($client['guestId'])&&($account=$accounts->get($client['guestId']))){
                if(!empty($account->profile_name)){$client['name']=$account->profile_name;$client['nameManaged']=true;}
                if($account->phone!==null)$client['phone']=$account->phone;
                if($account->contact_email!==null||$account->email!==null)$client['email']=$account->contact_email??$account->email;
            }
            unset($client);
        }
        Subscriptions::refresh($state);
        // Legacy record association is local to the salon; it grants no account access.
        foreach ($state["bookings"] as &$booking) {
            if(($booking['attendance']??'')==='no_show'&&$booking['status']==='confirmed')$booking['status']='no_show';
            if (!isset($booking["clientId"])) {
                $matches = array_values(array_filter($state["clients"], fn($c) =>
                    ($c["name"] ?? "") === ($booking["name"] ?? "") &&
                    Clients::phone($c["phone"] ?? "") === Clients::phone($booking["phone"] ?? "") &&
                    ($c["email"] ?? "") === ($booking["email"] ?? "")));
                if (count($matches) === 1) $booking["clientId"] = $matches[0]["id"];
            }
        }
        unset($booking);
        return $state;
    }
    public static function checkCalendars(array $state, array $people): void
    {
        if (!isset($state['subscription']['calendarLimit'])) return; // Preserve legacy entitlement until upgrade.
        $limit=(int)$state['subscription']['calendarLimit'];
        if (!in_array($state['subscription']['status']??'', ['active','trialing'], true)) $limit=1;
        $current=count(array_filter($state['staff'], fn($p)=>empty($p['archived'])));
        $next=count(array_filter($people, fn($p)=>empty($p['archived'])));
        if ($next > max($current,$limit)) throw new \InvalidArgumentException('A csomagod naptárkerete betelt. Több munkatárshoz kérj nagyobb keretet az üzemeltetőtől az Előfizetés menüben.');
    }
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map("intval", explode(":", $time));
        return $h * 60 + $m;
    }
    /** Effective assignment for new bookings; original team assignments and historical snapshots stay intact. */
    public static function serviceStaff(array $state,array $service):array
    {
        return ($state['settings']['businessMode']??'team')==='solo'
            ? [($state['settings']['primaryStaffId']??'')]
            : ($service['staff']??[]);
    }
    public static function activeStaff(array $state): array
    {
        $people = array_values(
            array_filter($state["staff"], fn($p) => empty($p["archived"])),
        );
        if (($state["settings"]["businessMode"] ?? "team") === "solo") {
            $people = array_values(
                array_filter(
                    $people,
                    fn($p) => $p["id"] === $state["settings"]["primaryStaffId"],
                ),
            );
        }
        return $people;
    }
}
