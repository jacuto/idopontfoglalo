<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\{DB,Http};
use InvalidArgumentException;

/** Operator access decisions are independent of Stripe and never erase payment history. */
final class PlatformAccess {
 public static function mode(array $s):string{return $s['subscription']['accessMode']??'active';}
 public static function exempt(array $sub,Time $from,Time $to):bool {
  foreach($sub['accessPeriods']??[] as $p)if(Time::parse($p['from'])->lt($to)&&(empty($p['to'])||Time::parse($p['to'])->gt($from)))return true;
  return false;
 }
 public function change(int $providerId,string $mode,?int $calendarLimit=null):array {
  abort_unless(DB::table('salon_state')->where('id',$providerId)->exists(),404);
  return app(Tenant::class)->run($providerId,fn()=>app(SalonStore::class)->mutate(function(&$s)use($mode,$calendarLimit){
   $sub=&$s['subscription'];$previous=self::mode($s);if($previous===$mode){if($calendarLimit!==null)$sub['calendarLimit']=$calendarLimit;return ['ok'=>true,'mode'=>$mode];}
   // Free access must not conceal an existing, automatically charging Stripe subscription.
   // Read only: the operator explicitly asked to keep Stripe enabled.
   if($mode==='free'&&!empty($sub['id'])){
    $key=config('integrations.platform_stripe.key');if(!$key)throw new InvalidArgumentException('A meglévő Stripe-előfizetés állapota nem ellenőrizhető. Előbb rendezd a Stripe-ban; a rendszer nem állítja le automatikusan.');
    $r=Http::timeout(15)->withToken($key)->get('https://api.stripe.com/v1/subscriptions/'.rawurlencode($sub['id']));
    if(!$r->successful())throw new InvalidArgumentException('A Stripe-előfizetés most nem ellenőrizhető. A hozzáférés nem változott.');
    $remote=$r->json();
    if(!in_array($remote['status']??'', ['canceled','incomplete_expired'],true)&&($remote['pause_collection']['behavior']??'')!=='void')throw new InvalidArgumentException('Még élő, automatikusan terhelő Stripe-előfizetés tartozik a fiókhoz. Az ingyenesség előtt ezt külön rendezd a Stripe-ban. A program nem módosítja a Stripe-előfizetést.');
   }
   Subscriptions::refresh($s);$now=now()->toISOString();
   if($previous!=='active'){foreach($sub['accessPeriods']??[] as $i=>$p)if(empty($p['to']))$sub['accessPeriods'][$i]['to']=$now;}
   if($mode!=='active')$sub['accessPeriods'][]=['mode'=>$mode,'from'=>$now,'to'=>null];
   if($calendarLimit!==null)$sub['calendarLimit']=$calendarLimit;
   $sub['accessMode']=$mode;$sub['accessHistory'][]=['from'=>$previous,'to'=>$mode,'at'=>$now,'by'=>'platform_admin'];
   Subscriptions::refresh($s);return ['ok'=>true,'mode'=>$mode];
  }));
 }
}
