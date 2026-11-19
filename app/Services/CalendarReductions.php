<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\Http;
/** Lower the next renewal without refunding the current period or deleting booking history. */
final class CalendarReductions
{
 public static function apply(array &$s):void {
  foreach($s['subscription']['calendarReductions']??[] as $id=>$change){
   if($change['status']!=='scheduled'||Time::parse($change['effectiveAt'])->isFuture())continue;
   foreach($s['staff'] as &$person)if($person['id']===$change['staffId']){$person['archived']=true;$person['archivedAt']=$change['effectiveAt'];}unset($person);
   $s['subscription']['calendarLimit']=max(1,(int)$s['subscription']['calendarLimit']-1);
   $s['subscription']['extraCalendars']=max(0,(int)$s['subscription']['extraCalendars']-1);
   $s['subscription']['calendarReductions'][$id]['status']='complete';
   if(($s['settings']['primaryStaffId']??'')===$change['staffId'])$s['settings']['primaryStaffId']=collect($s['staff'])->first(fn($p)=>empty($p['archived']))['id']??'';
  }
 }
 public function cancel(string $id,string $staff):array {
  $store=app(SalonStore::class);
  $order=$store->mutate(function(&$s)use($id,$staff){abort_unless(PlatformAccess::mode($s)==='active',422,'Ezt a hozzáférést az üzemeltető kezeli.');
   if(isset($s['subscription']['calendarReductions'][$id])){$o=$s['subscription']['calendarReductions'][$id];abort_unless($o['staffId']===$staff,409);return $o;}
   abort_if(collect($s['subscription']['calendarReductions']??[])->contains(fn($r)=>in_array($r['status'],['pending','scheduled'],true)),409,'Egy naptárlemondás már folyamatban van. Az érvényesülése után kérhetsz újabbat.');
   foreach(['calendarOrders','planChanges'] as $key)abort_if(collect($s['subscription'][$key]??[])->contains(fn($r)=>$r['status']==='pending'),409,'Előbb fejezd be a folyamatban lévő csomag- vagy naptármódosítást.');
   $quantity=(int)($s['subscription']['extraCalendars']??0);abort_unless($quantity>0,422,'Nincs lemondható fizetős plusz naptár.');
   abort_unless(collect($s['staff'])->contains(fn($p)=>$p['id']===$staff),422,'Válassz naptárat.');
   if(count(Settings::activeStaff($s))<=1&&collect(Settings::activeStaff($s))->contains(fn($p)=>$p['id']===$staff))abort(422,'Az utolsó aktív naptár nem kapcsolható ki. Válassz egy korábban kikapcsolt plusz naptárat.');
   $o=['requestId'=>$id,'staffId'=>$staff,'quantity'=>$quantity-1,'status'=>'pending','effectiveAt'=>Subscriptions::nextPeriod($s),'stripeId'=>$s['subscription']['id']??null,'newMonthlyAmount'=>max(0,Subscriptions::monthlyAmount($s)-2000)];$s['subscription']['calendarReductions'][$id]=$o;return $o;
  });if($order['status']!=='pending')return $order;
  $effective=$order['effectiveAt'];
  if($order['stripeId']){
   $key=config('integrations.platform_stripe.key');abort_unless($key,422,'A központi Stripe-kapcsolat hiányzik.');$url='https://api.stripe.com/v1/subscriptions/'.rawurlencode($order['stripeId']);
   $remote=Http::timeout(20)->withToken($key)->get($url)->throw()->json();abort_unless(($remote['id']??null)===$order['stripeId']&&in_array($remote['status']??'', ['active','trialing'],true),422,'A Stripe-előfizetés most nem módosítható.');
   $items=$remote['items']['data']??[];$base=collect($items)->first(fn($i)=>($i['price']['currency']??'')==='huf'&&in_array($i['price']['unit_amount']??0,[400000,(int)config('plans.pro_price',6000)*100],true));
   $addons=array_values(array_filter($items,fn($i)=>($i['price']['currency']??'')==='huf'&&($i['price']['unit_amount']??0)===200000&&($i['price']['product']??null)===($base['price']['product']??null)));
   abort_unless($base&&count($addons)<=1,422,'A Stripe naptárbővítése nem azonosítható egyértelműen. Kérj segítséget az üzemeltetőtől.');$addon=$addons[0]??null;
   $end=$remote['current_period_end']??$base['current_period_end']??null;abort_unless($end&&Time::createFromTimestamp($end)->isFuture(),422,'A számlázási időszak vége nem azonosítható.');
   $effective=$store->mutate(function(&$s)use($id,$end){$s['subscription']['calendarReductions'][$id]['stripeEffectiveAt']??=Time::createFromTimestamp($end)->toISOString();return $s['subscription']['calendarReductions'][$id]['stripeEffectiveAt'];});
   abort_unless(Time::parse($effective)->isFuture(),409,'Az időszak közben lejárt. Kérd az üzemeltető segítségét a lemondás egyeztetéséhez.');
   if(!$addon)abort_unless($order['quantity']===0,422,'A Stripe naptárbővítése hiányzik.');
   elseif((int)$addon['quantity']!==$order['quantity']){
    abort_unless((int)$addon['quantity']===$order['quantity']+1,409,'A Stripe naptárkerete közben megváltozott.');
    $change=$order['quantity']?['id'=>$addon['id'],'quantity'=>$order['quantity']]:['id'=>$addon['id'],'deleted'=>'true'];
    $result=Http::timeout(25)->withToken($key)->withHeaders(['Idempotency-Key'=>'raero-calendar-cancel-'.app(Tenant::class)->id.'-'.$id])->asForm()->post($url,['items'=>[$change],'proration_behavior'=>'none'])->throw()->json();
    $remaining=collect($result['items']['data']??[])->firstWhere('id',$addon['id']);abort_unless(($result['id']??null)===$order['stripeId']&&(int)($remaining['quantity']??0)===$order['quantity'],422,'A Stripe nem igazolta a lemondást. Ugyanezt a kérést próbáld újra.');
   }
  }
  return $store->mutate(function(&$s)use($id,$order,$effective){$stored=$s['subscription']['calendarReductions'][$id];if($stored['status']!=='pending')return $stored;abort_unless(($s['subscription']['id']??null)===$order['stripeId'],409,'Az előfizetés közben megváltozott.');$stored['status']='scheduled';$stored['effectiveAt']=$effective;$s['subscription']['calendarReductions'][$id]=$stored;$s['subscription']['calendarAddons'][]=['quantity'=>-1,'startsAt'=>$effective,'requestId'=>$id];return $stored;});
 }
}
