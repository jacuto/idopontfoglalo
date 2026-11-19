<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\DB;
/** Optional accounting entries only; never claims a gateway payment or charges a card. */
final class AutomaticPayments
{
 public static function reconcile(array &$s):void {
  $defaults=PaymentDues::defaults($s);$enabled=!empty($defaults['automatic']);$bookings=array_column($s['bookings'],null,'id');$existing=[];
  foreach($s['payments'] as $i=>$p)if(($p['source']??'')==='automatic')$existing[$p['bookingId']]=$i;
  foreach(array_unique([...array_keys($bookings),...array_keys($existing)]) as $id){
   $b=$bookings[$id]??null;$index=$existing[$id]??null;$old=$index!==null?$s['payments'][$index]:null;
   if(!$old&&!$enabled)continue;
   if(!empty($old['automationStopped']))continue;
   $eligible=$b&&$b['status']==='confirmed'&&($b['attendance']??'')==='attended'&&Time::parse($b['start'])->lte(now());
   $other=0;foreach($s['payments'] as $p)if($p['bookingId']===$id&&($old['id']??null)!==$p['id']&&$p['status']==='paid')$other+=max(0,$p['amount']-($p['refundedAmount']??0));
   $amount=$eligible?max(0,Offers::total($b)-$other):0;
   if(!$old&&(!$amount||!$enabled))continue;
   $frozen=!empty($old['invoice'])||!empty($old['refundedAmount']);
   if($old)foreach($s['invoiceRequests']??[] as $invoice)if((($invoice['paymentId']??'')===$old['id']||($invoice['bookingId']??'')===$id)&&!in_array($invoice['status']??'', ['failed','void','cancelled'],true))$frozen=true;
   $values=['amount'=>$amount,'status'=>$amount?'paid':'void','paidAt'=>$b?Time::parse($b['start'])->toISOString():($old['paidAt']??now()->toISOString()),'method'=>$old['method']??$defaults['method'],'note'=>$old['note']??$defaults['note']];
   if($frozen){if((int)$old['amount']!==$amount||($old['status']??'')!==$values['status'])$s['payments'][$index]['reconciliationRequired']=true;else unset($s['payments'][$index]['reconciliationRequired']);continue;}
   if($old&&array_intersect_key($old,$values)===$values)continue;
   $record=$old??['id'=>'auto-'.(string)\Illuminate\Support\Str::uuid(),'bookingId'=>$id,'source'=>'automatic','version'=>0,'createdAt'=>now()->toISOString()];
   $record=array_replace($record,$values,['updatedAt'=>now()->toISOString(),'version'=>(int)$record['version']+1]);
   $record['history'][]=['action'=>$old?($amount?'reconcile':'void'):'create','by'=>'automatic','at'=>now()->toISOString(),'before'=>$old?array_intersect_key($old,$values):null,'after'=>$values];
   if($index===null)$s['payments'][]=$record;else $s['payments'][$index]=$record;
  }
 }
 public static function run():int{$count=0;foreach(DB::table('salon_state')->pluck('id') as $id)app(Tenant::class)->run((int)$id,function()use(&$count){$store=app(SalonStore::class);$s=$store->read();if(!empty($s['settings']['paymentDefaults']['automatic'])||collect($s['payments'])->contains(fn($p)=>($p['source']??'')==='automatic')){$store->mutate(fn(&$s)=>null);$count++;}});return $count;}
}
