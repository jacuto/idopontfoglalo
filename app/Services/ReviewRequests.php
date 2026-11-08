<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\{DB,Crypt};
final class ReviewRequests{
 public static function eligible(array $s,array $b):bool{return $b['status']==='confirmed'&&($b['attendance']??'')==='attended'&&Time::parse($b['start'])->addMinutes(array_sum(array_column($b['serviceSnapshot']??[],'duration')))->lte(Time::now())&&!collect($s['reviews'])->contains(fn($v)=>$v['bookingId']===$b['id']&&$v['direction']==='guest');}
 public static function queue(array $s,array $b,bool $automatic=false):bool{
  if(!self::eligible($s,$b)||empty($b['email']))return false;$payload=['kind'=>'review_request','bookingId'=>$b['id'],'automatic'=>$automatic];return (bool)DB::table('deliveries')->insertOrIgnore(['provider_id'=>app(Tenant::class)->id,'dedup'=>hash('sha256','review-request:'.app(Tenant::class)->id.':'.$b['id']),'payload'=>Crypt::encryptString(json_encode($payload)),'status'=>'pending','attempts'=>0,'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function deliver(string $id,array $s,bool $automatic):string{
  $b=collect($s['bookings'])->firstWhere('id',$id);if(!$b||!self::eligible($s,$b)||empty($b['email'])||($automatic&&empty($s['settings']['reviewRequestsEnabled'])))return 'skipped';$url=rtrim(config('app.url'),'/').'/modositas?provider='.app(Tenant::class)->id.'&token='.urlencode($b['token']).'&review=1';
  return SystemMail::send($b['email'],'review_request',['business'=>$s['settings']['name'],'name'=>$b['name'],'details'=>'Köszönjük a látogatásodat! Oszd meg a tapasztalatodat, és segíts a következő vendégeknek a választásban.','manage_url'=>$url,'account_url'=>$url,'provider_url'=>rtrim(config('app.url'),'/').'/foglalas?provider='.app(Tenant::class)->id],(!empty($b['guestId'])?DB::table('guest_credentials')->where('guest_id',$b['guestId'])->value('locale'):null)??($b['locale']??'hu'));
 }
 public static function run():int{$n=0;foreach(DB::table('salon_state')->pluck('id') as $id)app(Tenant::class)->run((int)$id,function()use(&$n){app(SalonStore::class)->mutate(function(&$s)use(&$n){if(empty($s['settings']['reviewRequestsEnabled'])||empty($s['settings']['reviewRequestsEnabledAt']))return;foreach($s['bookings'] as $b){$end=Time::parse($b['start'])->addMinutes(array_sum(array_column($b['serviceSnapshot']??[],'duration')));if($end->gte(Time::parse($s['settings']['reviewRequestsEnabledAt']))&&$end->addHours((int)$s['settings']['reviewRequestDelayHours'])->lte(Time::now())&&self::queue($s,$b,true))$n++;}});});return $n;}
}
