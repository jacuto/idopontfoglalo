<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Monthly charges are stable snapshots; payment events settle them under the salon lock. */
final class Subscriptions
{
    public static function monthlyAmount(array $state, ?string $at=null):int{
        $count=0;foreach($state['subscription']['calendarAddons']??[] as $addon)if($at===null||Time::parse($addon['startsAt'])->lte(Time::parse($at)))$count+=(int)$addon['quantity'];return PlanPricing::base($state,$at)+2000*$count;
    }
    public static function amountForPeriod(array $state,string $at):int{foreach($state['subscription']['charges']??[] as $e)if($e['kind']==='monthly'&&Time::parse($e['from'])->equalTo(Time::parse($at)))return (int)$e['amount'];return self::monthlyAmount($state,$at);}
    public static function nextPeriod(array $state):string{
        $sub=$state['subscription'];if(empty($sub['id'])&&!empty($sub['paidUntil'])&&Time::parse($sub['paidUntil'])->isFuture())return Time::parse($sub['paidUntil'])->toISOString();if(!empty($sub['trialEndsAt'])&&Time::parse($sub['trialEndsAt'])->isFuture())return Time::parse($sub['trialEndsAt'])->toISOString();foreach($sub['charges']??[] as $entry)if($entry['kind']==='monthly'&&Time::parse($entry['from'])->lte(now())&&Time::parse($entry['to'])->isFuture())return $entry['to'];return now()->addMonthNoOverflow()->toISOString();
    }
    public static function refresh(array &$state): void
    {
        $sub = &$state['subscription'];
        $sub['calendarBlocked'] = false;
        if (empty($sub['trialEndsAt'])) return; // No invented arrears for unconverted legacy records.
        if (!array_key_exists('legacyPaidUntil',$sub)) $sub['legacyPaidUntil']=$sub['paidUntil']??null;
        $end = Time::parse($sub['trialEndsAt']);
        $start = Time::parse($sub['trialStartedAt'] ?? $end->subMonthsNoOverflow(2));
        $entries = collect($sub['charges'] ?? [])->keyBy('id')->all();
        for ($i = 0; $i < 2; $i++) {
            $from = $start->addMonthsNoOverflow($i);
            $to = $i === 1 ? $end : $start->addMonthNoOverflow();
            $id = 'trial-' . $i;
            $entries[$id] ??= ['id'=>$id, 'kind'=>'trial', 'amount'=>0, 'currency'=>'HUF', 'status'=>'trial', 'from'=>$from->toISOString(), 'to'=>$to->toISOString(), 'dueAt'=>null, 'paymentId'=>null, 'noInvoice'=>true];
        }
        // Generate elapsed monthly charges; repeated reads and cron runs cannot duplicate a period.
        for ($i = 0; $i < 1200; $i++) {
            $from = $end->addMonthsNoOverflow($i);
            if ($from->isFuture()) break;
            $to = $end->addMonthsNoOverflow($i + 1);
            $id = 'month-' . $from->format('Y-m-d-His');
            $entries[$id] ??= ['id'=>$id, 'kind'=>'monthly', 'amount'=>self::monthlyAmount($state,$from->toISOString()), 'currency'=>'HUF', 'status'=>'pending', 'from'=>$from->toISOString(), 'to'=>$to->toISOString(), 'dueAt'=>$from->addDays(21)->toISOString(), 'paymentId'=>null, 'noInvoice'=>false];
            // Preserve previously credited subscriptions when enabling the charge ledger.
            if ($entries[$id]['status'] === 'pending' && !empty($sub['legacyPaidUntil']) && Time::parse($sub['legacyPaidUntil'])->gte($to)) {
                $entries[$id]['status'] = 'paid';
                $entries[$id]['legacyCredit'] = true;
            }
        }
        foreach($entries as &$entry) foreach($sub['coverage']??[] as $credit) {
            if($entry['kind']==='monthly' && Time::parse($credit['from'])->lte(Time::parse($entry['from'])) && Time::parse($credit['to'])->gte(Time::parse($entry['to']))) $entry=array_replace($entry,['status'=>'paid','paymentId'=>$credit['paymentId'],'paidAt'=>$credit['paidAt'],'method'=>$credit['method'],'reference'=>$credit['reference']]);
        }
        unset($entry);
        $sub['charges'] = array_values($entries);
        foreach ($sub['charges'] as &$entry) {
            if ($entry['kind'] === 'monthly' && $entry['status'] !== 'paid') {
                $overdue = Time::parse($entry['dueAt'])->isPast();
                $entry['status'] = $overdue ? 'overdue' : 'pending';
                if ($overdue) $sub['calendarBlocked'] = true;
            }
        }
        unset($entry);
        if ($sub['calendarBlocked']) $sub['status'] = 'blocked';
        elseif ($end->isFuture()) $sub['status'] = 'trialing';
        elseif (collect($sub['charges'])->contains(fn($e)=>in_array($e['status'],['pending','overdue'],true))) $sub['status']='past_due';
        elseif (!empty($sub['paidUntil']) && Time::parse($sub['paidUntil'])->isFuture()) $sub['status']='active';
    }

    public static function guard(array $state, bool $provider = true): void
    {
        if (!empty($state['subscription']['calendarBlocked'])) abort(423, $provider ? 'A naptár zárolva van, mert egy havi díj 21 napos fizetési határideje lejárt. Nyisd meg az Előfizetés menüt, és rendezd a függő tételt.' : 'A szolgáltató online foglalása jelenleg szünetel. Időpontért keresd közvetlenül a szolgáltatót.');
    }

    public static function settle(array &$state, object $payment, ?string $chargeId = null, ?string $periodStart = null): void
    {
        self::refresh($state);
        $charges = &$state['subscription']['charges'];
        if (!isset($charges)) $charges = [];
        // Never map two different bank/Stripe payments onto the same paid charge.
        foreach ($charges as $entry) if (($entry['paymentId'] ?? null) == $payment->id) return;
        $index = null;
        foreach ($charges as $i => $entry) {
            if ($entry['kind'] !== 'monthly') continue;
            $match = $chargeId ? $entry['id'] === $chargeId : ($periodStart ? Time::parse($entry['from'])->equalTo(Time::parse($periodStart)) : in_array($entry['status'], ['pending','overdue'], true));
            if ($match) { $index=$i; break; }
        }
        if($index===null && $periodStart && !$chargeId) foreach($charges as $i=>$entry)if($entry['kind']==='monthly'&&in_array($entry['status'],['pending','overdue'],true)){$index=$i;break;}
        if ($index !== null) {
            if((int)$payment->amount!==(int)$charges[$index]['amount'])throw new InvalidArgumentException('A befizetés összege nem egyezik a havi díjtétellel. Ellenőrizd az előfizetést.');
            if ($charges[$index]['status'] === 'paid' && empty($charges[$index]['legacyCredit'])) return;
            $charges[$index] = array_replace($charges[$index], ['status'=>'paid','paymentId'=>(int)$payment->id,'paidAt'=>Time::parse($payment->paid_at)->toISOString(),'method'=>$payment->method,'reference'=>$payment->reference,'legacyCredit'=>false]);
        } elseif ($chargeId) throw new InvalidArgumentException('A havi díjtétel nem található.');
        $from=$index!==null?$charges[$index]['from']:($periodStart??Time::parse($payment->period_end)->subMonthNoOverflow()->toISOString());
        $state['subscription']['coverage'][(string)$payment->id]=['from'=>$from,'to'=>$index!==null?$charges[$index]['to']:Time::parse($payment->period_end)->toISOString(),'paymentId'=>(int)$payment->id,'paidAt'=>Time::parse($payment->paid_at)->toISOString(),'method'=>$payment->method,'reference'=>$payment->reference];
        // Legacy/prepaid payment: retain full transaction even without an elapsed charge.
        $until=Time::parse($payment->period_end)->toISOString();
        if (empty($state['subscription']['paidUntil']) || Time::parse($until)->gt(Time::parse($state['subscription']['paidUntil']))) $state['subscription']['paidUntil']=$until;
        self::refresh($state);
    }

    public function transfer(int $providerId, string $reference, string $paidAt, ?string $chargeId = null): array
    {
        if (str_starts_with($reference,'stripe-')) throw new InvalidArgumentException('Banki tranzakcióhoz saját banki azonosítót adj meg.');
        return app(Tenant::class)->run($providerId, fn()=>app(SalonStore::class)->mutate(function (&$s) use($providerId,$reference,$paidAt,$chargeId) {
            $old=DB::table('platform_payments')->where('reference',$reference)->first();
            if ($old) {
                abort_unless((int)$old->provider_id===$providerId,422,'Ez a banki azonosító másik szolgáltatóhoz tartozik.');
                if($chargeId){$previousCharge=collect($s['subscription']['charges']??[])->firstWhere('id',$chargeId);if(!$previousCharge||($previousCharge['paymentId']??null)!=(int)$old->id)throw new InvalidArgumentException('Ez a banki tranzakció már másik havi díjtételt rendezett. Új befizetéshez új banki azonosító szükséges.');}
                return ['ok'=>true,'alreadyCredited'=>true];
            }
            self::refresh($s);
            $entry = $chargeId ? collect($s['subscription']['charges']??[])->firstWhere('id',$chargeId) : collect($s['subscription']['charges']??[])->first(fn($e)=>in_array($e['status'],['pending','overdue'],true));
            if ($chargeId && (!$entry || $entry['kind']!=='monthly' || $entry['status']==='paid')) throw new InvalidArgumentException('Csak még rendezetlen, fizetős havi díj hagyható jóvá.');
            $base=Time::parse($s['subscription']['paidUntil']??$s['subscription']['trialEndsAt']??now());
            if ($base->isPast()) $base=Time::now();
            $end=$entry?Time::parse($entry['to']):$base->addMonthNoOverflow();
            $inserted=DB::table('platform_payments')->insertOrIgnore(['provider_id'=>$providerId,'reference'=>$reference,'amount'=>$entry['amount']??self::monthlyAmount($s),'method'=>'transfer','paid_at'=>Time::parse($paidAt)->setTimezone(config('app.timezone')),'period_start'=>($entry?Time::parse($entry['from']):$end->subMonthNoOverflow())->setTimezone(config('app.timezone')),'period_end'=>$end->setTimezone(config('app.timezone')),'details'=>json_encode(['source'=>'manual_transfer','approvedBy'=>'platform_admin','approvedAt'=>now()->toISOString(),'chargeId'=>$entry['id']??null]),'created_at'=>now(),'updated_at'=>now()]);
            if (!$inserted) throw new InvalidArgumentException('Ezt a banki tranzakciót már rögzítették. Frissítsd a listát.');
            $payment=DB::table('platform_payments')->where('reference',$reference)->first();
            self::settle($s,$payment,$entry['id']??null);
            return ['ok'=>true,'alreadyCredited'=>false,'paymentId'=>$payment->id];
        }));
    }
}
