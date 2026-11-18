<?php
namespace App\Services;
final class CatalogInsights
{
 public static function passType(array $s,array $pass):?string{
  if(!empty($pass['passTypeId']))return $pass['passTypeId'];
  $matches=array_values(array_filter($s['passes'],fn($type)=>$type['name']===$pass['name']&&(int)$type['price']===(int)$pass['price']));return count($matches)===1?$matches[0]['id']:null;
 }
 public static function build(array $s):array{
  $coupons=[];$passes=[];
  foreach($s['coupons'] as $c)$coupons[$c['id']]=['id'=>$c['id'],'name'=>$c['name'],'uses'=>0,'guests'=>0,'original'=>0,'discount'=>0,'payable'=>0,'paid'=>0,'_guests'=>[]];
  foreach($s['passes'] as $type)$passes[$type['id']]=['id'=>$type['id'],'name'=>$type['name'],'issued'=>0,'guests'=>0,'value'=>0,'remaining'=>0,'reserved'=>0,'consumed'=>0,'usedGuests'=>0,'_usedGuests'=>[],'original'=>0,'allocated'=>0,'discount'=>0,'_guests'=>[]];
  $byPass=[];$payments=[];
  foreach($s['payments'] as $p)if($p['status']==='paid')$payments[$p['bookingId']]=($payments[$p['bookingId']]??0)+$p['amount']-($p['refundedAmount']??0);
  foreach($s['bookings'] as $b){if(in_array($b['status'],['cancelled','rejected'],true))continue;$id=$b['pricing']['couponId']??null;
   if($id&&isset($coupons[$id])){$row=&$coupons[$id];$row['uses']++;$row['_guests'][$b['clientId']??('guest-'.($b['guestId']??$b['id']))]=true;$row['original']+=(int)($b['pricing']['original']??0);$row['discount']+=(int)($b['pricing']['discount']??0);$row['payable']+=(int)($b['pricing']['payable']??0);$row['paid']+=$payments[$b['id']]??0;unset($row);}
   if(!empty($b['passId']))$byPass[$b['passId']][]=$b;
  }
  $issued=[];
  foreach($s['clientPasses'] as $pass){$type=self::passType($s,$pass);$reservations=$pass['reservations']??[];$reserved=count(array_filter($reservations,fn($status)=>$status==='reserved'));$consumed=count(array_filter($reservations,fn($status)=>$status==='consumed'));$initial=(int)($pass['initialVisits']??($pass['remaining']+$reserved+$consumed));$original=0;$allocated=0;
   foreach($byPass[$pass['id']]??[] as $b)if(($reservations[$b['id']]??'')==='consumed'){$original+=(int)($b['pricing']['original']??array_sum(array_column($b['serviceSnapshot']??[],'price')));$allocated+=$initial>0?$pass['price']/$initial:0;}
   $status=!empty($pass['revokedAt'])?'revoked':(!empty($pass['expiresAt'])&&\Carbon\CarbonImmutable::parse($pass['expiresAt'])->isPast()?'expired':($pass['remaining']<=0?'exhausted':'active'));
   $future=array_values(array_map(fn($b)=>['id'=>$b['id'],'start'=>$b['start'],'name'=>$b['name']],array_filter($byPass[$pass['id']]??[],fn($b)=>in_array($b['status'],['pending','confirmed'],true)&&\Carbon\CarbonImmutable::parse($b['start'])->isFuture())));
   $issued[]=array_merge($pass,['passTypeId'=>$type,'status'=>$status,'reserved'=>$reserved,'consumed'=>$consumed,'initialVisits'=>$initial,'futureBookings'=>$future]);
   if($type&&isset($passes[$type])){$row=&$passes[$type];if(empty($pass['revokedAt'])){$row['issued']++;$row['_guests'][$pass['clientId']]=true;$row['value']+=(int)$pass['price'];$row['remaining']+=$pass['remaining'];$row['reserved']+=$reserved;}if($consumed)$row['_usedGuests'][$pass['clientId']]=true;$row['consumed']+=$consumed;$row['original']+=$original;$row['allocated']+=$allocated;$row['discount']+=max(0,$original-$allocated);unset($row);}
  }
  foreach([&$coupons,&$passes] as &$group)foreach($group as &$row){$row['guests']=count($row['_guests']);unset($row['_guests']);if(isset($row['_usedGuests'])){$row['usedGuests']=count($row['_usedGuests']);unset($row['_usedGuests']);}$row['discountPercent']=$row['original']>0?round($row['discount']/$row['original']*100,1):0;}unset($group,$row);
  return ['coupons'=>array_values($coupons),'passes'=>array_values($passes),'issued'=>$issued];
 }
}
