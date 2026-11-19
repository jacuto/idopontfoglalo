<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
final class SubscriptionInvoiceItems
{
 public static function snapshot(array $s,string $from,int $amount):array{
  $base=PlanPricing::base($s,$from);$count=0;foreach($s['subscription']['calendarAddons']??[] as $item)if(Time::parse($item['startsAt'])->lte(Time::parse($from)))$count+=(int)$item['quantity'];
  if($base+2000*$count!==$amount||$count<0)return [self::item('Ráérő előfizetés – egyeztetett havi díj',$amount,1,'hónap')];
  $items=[self::item('Ráérő '.($base===4000?'Alap':'Pro').' díjcsomag',$base,1,'hónap')];if($count)$items[]=self::item('Plusz naptár – havi díj',2000,$count,'db');return $items;
 }
 public static function forPayment(object $payment):array{
  $details=json_decode($payment->details??'{}',true)?:[];$items=$details['invoiceItems']??null;
  if(is_array($items)&&$items&&array_sum(array_map(fn($i)=>(int)($i['unit_price']??0)*(int)($i['quantity']??0),$items))===(int)$payment->amount)return $items;
  return [self::item('Ráérő előfizetés – korábbi befizetés',(int)$payment->amount,1,'hónap')];
 }
 private static function item(string $name,int $price,int $quantity,string $unit):array{return ['name'=>$name,'unit_price'=>$price,'unit_price_type'=>'gross','quantity'=>$quantity,'unit'=>$unit,'vat'=>'AAM'];}
}
