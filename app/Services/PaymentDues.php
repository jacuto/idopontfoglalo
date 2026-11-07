<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
final class PaymentDues
{
 public static function defaults(array $s):array{return array_replace(['enabled'=>true,'method'=>'cash','note'=>''],$s['settings']['paymentDefaults']??[]);}
 public static function rows(array $s):array{
  if(!self::defaults($s)['enabled'])return [];$paid=[];foreach($s['payments'] as $p)if($p['status']==='paid')$paid[$p['bookingId']]=($paid[$p['bookingId']]??0)+$p['amount']-($p['refundedAmount']??0);
  $rows=[];foreach($s['bookings'] as $b){if(!in_array($b['status'],['confirmed','no_show'],true)||Time::parse($b['start'])->isFuture())continue;$remaining=max(0,Offers::total($b)-($paid[$b['id']]??0));if(!$remaining)continue;$rows[]=['bookingId'=>$b['id'],'start'=>$b['start'],'name'=>$b['name'],'amount'=>$remaining,'status'=>'due'];}usort($rows,fn($a,$b)=>strcmp($b['start'],$a['start']));return $rows;
 }
}
