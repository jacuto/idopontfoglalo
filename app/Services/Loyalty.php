<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Str;
use InvalidArgumentException;
final class Loyalty
{
 public static function init(array &$s):void{$s['loyalty']??=[];$s['loyalty']+=['rule'=>['mode'=>'visits','pointsPerVisit'=>1,'spendUnit'=>1000],'rewards'=>[],'ledger'=>[],'earnings'=>[],'redemptions'=>[],'periods'=>[],'wasEnabled'=>false];}
 public static function balance(array $s,string $client):int{
  $people=array_column($s['clients'],null,'id');$guest=$people[$client]['guestId']??null;$ids=[$client];if($guest)foreach($people as $c)if((int)($c['guestId']??0)===(int)$guest)$ids[]=$c['id'];
  return (int)array_sum(array_map(fn($l)=>in_array($l['clientId'],$ids,true)?$l['delta']:0,$s['loyalty']['ledger']??[]));
 }
 private static function entry(array &$s,string $client,string $booking,int $delta,string $type):void{if($delta)$s['loyalty']['ledger'][]=['id'=>(string)Str::uuid(),'clientId'=>$client,'bookingId'=>$booking,'delta'=>$delta,'type'=>$type,'at'=>now()->toISOString()];}
 public static function reserve(array &$s,array &$b,?string $reward,bool $provider):void{
  if(!$reward)return;self::init($s);
  if(empty($s['settings']['loyaltyEnabled']))throw new InvalidArgumentException('A pontgyűjtés jelenleg ki van kapcsolva.');
  $client=collect($s['clients'])->firstWhere('id',$b['clientId']??'');abort_unless($client,404);
  if(!$provider){$guest=(int)($b['guestId']??0);$account=$guest?\Illuminate\Support\Facades\DB::table('guest_accounts')->find($guest):null;abort_unless($account&&!empty($account->email_verified)&&(int)($client['guestId']??0)===$guest,403,'Pontbeváltáshoz lépj be az összekapcsolt, ellenőrzött vendégfiókodba.');}
  if(!empty($b['pricing']['couponId'])||!empty($b['passId']))throw new InvalidArgumentException('Pontjutalom kuponnal vagy bérlettel nem vonható össze.');
  $r=collect($s['loyalty']['rewards'])->first(fn($v)=>$v['id']===$reward&&!empty($v['active']));if(!$r)throw new InvalidArgumentException('Ez a pontjutalom már nem elérhető.');
  if(!empty($r['services'])&&array_diff($b['services'],$r['services']))throw new InvalidArgumentException('Ez a pontjutalom nem érvényes minden kiválasztott szolgáltatásra.');
  $original=(int)$b['pricing']['original'];if($original<=0)throw new InvalidArgumentException('Ingyenes foglalásnál nincs szükség pontbeváltásra.');
  if(self::balance($s,$client['id'])<$r['points'])throw new InvalidArgumentException('Nincs elegendő beváltható pontod.');
  $snapshot=['clientId'=>$client['id'],'rewardId'=>$r['id'],'name'=>$r['name'],'points'=>(int)$r['points'],'amount'=>(int)$r['amount'],'services'=>$r['services']??[],'active'=>true];
  $s['loyalty']['redemptions'][$b['id']]=$snapshot;self::entry($s,$client['id'],$b['id'],-$snapshot['points'],'redeem');self::price($b,$snapshot);
 }
 private static function price(array &$b,array $r):void{
  $original=(int)array_sum(array_column($b['serviceSnapshot'],'price'));$discount=min($original,$r['amount']);
  $b['pricing']=['original'=>$original,'discount'=>$discount,'payable'=>$original-$discount,'loyaltyRewardId'=>$r['rewardId'],'loyaltyPoints'=>$r['points'],'loyaltyRewardName'=>$r['name']];
 }
 public static function syncRedemptions(array &$s):void{
  self::init($s);$indexes=[];foreach($s['bookings'] as $index=>$booking)$indexes[$booking['id']]=$index;
  foreach($s['loyalty']['redemptions'] as $id=>&$r){$i=$indexes[$id]??null;$b=$i!==null?$s['bookings'][$i]:null;$valid=$b&&!in_array($b['status'],['cancelled','rejected','no_show'],true);$original=$b?(int)array_sum(array_column($b['serviceSnapshot'],'price')):0;$active=$valid&&$original>0;if($valid&&$original<=0)$s['bookings'][$i]['pricing']=['original'=>0,'discount'=>0,'payable'=>0];
   if(!$active&&!empty($r['active'])){self::entry($s,$r['clientId'],$id,$r['points'],'redeem_release');$r['active']=false;}
   if($active&&empty($r['active'])){if(empty($s['settings']['loyaltyEnabled']))throw new InvalidArgumentException('Pontjutalmas foglalás visszaállításához kapcsold be a pontgyűjtést.');if(self::balance($s,$r['clientId'])<$r['points'])throw new InvalidArgumentException('A foglalás visszaállításához már nincs elegendő pont.');self::entry($s,$r['clientId'],$id,-$r['points'],'redeem_restore');$r['active']=true;}
   if($active){if(!empty($r['services'])&&array_diff($b['services'],$r['services']))throw new InvalidArgumentException('A lefoglalt pontjutalom nem érvényes az új szolgáltatásokra.');if(($b['clientId']??'')!==$r['clientId'])throw new InvalidArgumentException('Pontjutalmas foglalás vendége nem cserélhető.');if(!empty($b['pricing']['couponId'])||!empty($b['passId']))throw new InvalidArgumentException('Pontjutalom kuponnal vagy bérlettel nem vonható össze.');self::price($s['bookings'][$i],$r);}
  }unset($r);
 }
 public static function reconcile(array &$s):void{
  self::init($s);$l=&$s['loyalty'];$enabled=!empty($s['settings']['loyaltyEnabled']);
  if($enabled&&!$l['wasEnabled'])$l['periods'][]=['from'=>now()->toISOString(),'until'=>null];
  if(!$enabled&&$l['wasEnabled']&&$l['periods'])$l['periods'][array_key_last($l['periods'])]['until']=now()->toISOString();$l['wasEnabled']=$enabled;
  $bookings=array_column($s['bookings'],null,'id');
  foreach(array_unique([...array_keys($bookings),...array_keys($l['earnings'])]) as $id){$b=$bookings[$id]??null;$old=$l['earnings'][$id]??null;
   if(!$old&&!$enabled)continue;$valid=$b&&$b['status']==='confirmed'&&($b['attendance']??'')==='attended'&&Time::parse($b['start'])->lte(now())&&empty($b['passId'])&&empty($l['redemptions'][$id])&&!empty($b['clientId']);
   $inPeriod=false;if($b)foreach($l['periods'] as $p)if($b['start']>=$p['from']&&(!$p['until']||$b['start']<$p['until']))$inPeriod=true;
   $target=0;$rule=$old['rule']??$l['rule'];$paid=0;$total=$b?Offers::total($b):0;
   if($valid&&($old||$inPeriod)&&$total>0){foreach($s['payments'] as $payment)if(($payment['bookingId']??'')===$id&&($payment['status']??'')==='paid'&&empty($payment['reconciliationRequired']))$paid+=max(0,(int)$payment['amount']-(int)($payment['refundedAmount']??0));
    if($paid>=$total||($old&&$rule['mode']==='spend'))$target=$rule['mode']==='visits'?($paid>=$total?(int)$rule['pointsPerVisit']:0):intdiv(min($paid,$total),(int)$rule['spendUnit']);
   }
   if(!$old&&$target===0)continue;$client=$old['clientId']??$b['clientId'];if($old&&$b&&($b['clientId']??'')!==$client)$target=0;
   $delta=$target-(int)($old['points']??0);self::entry($s,$client,$id,$delta,$old?'earn_adjust':'earn');$l['earnings'][$id]=['clientId'=>$client,'points'=>$target,'rule'=>$rule];
  }
 }
 public static function view(array $s,string $client,?int $historyLimit=50):array{self::init($s);$balance=self::balance($s,$client);$people=array_column($s['clients'],null,'id');$guest=$people[$client]['guestId']??null;$history=array_values(array_filter($s['loyalty']['ledger'],fn($r)=>$r['clientId']===$client||($guest&&(int)($people[$r['clientId']]['guestId']??0)===(int)$guest)));return ['enabled'=>!empty($s['settings']['loyaltyEnabled']),'balance'=>$balance,'available'=>max(0,$balance),'rewards'=>array_values(array_filter($s['loyalty']['rewards'],fn($r)=>!empty($r['active']))),'history'=>$historyLimit===null?$history:array_slice($history,-$historyLimit)];}
}
