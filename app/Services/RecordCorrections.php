<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use InvalidArgumentException;
/** Corrections never rewrite external payment proofs, invoices or booking price snapshots. */
final class RecordCorrections
{
 public static function payment(array &$s,string $id,array $v,bool $remove,string $actor):array{
  foreach($s['payments'] as &$p){if($p['id']!==$id)continue;
   abort_unless(in_array($p['source']??'', ['manual','sample'],true),422,'Az automatikusan igazolt bankkártyás befizetést a fizetési szolgáltatónál kell rendezni.');
   abort_unless(($p['status']??'')==='paid'&&empty($p['deletedAt'])&&empty($p['refundedAmount']),422,'Ez a tétel már érvénytelenített vagy visszatérített.');
   if(isset($v['version']))abort_unless((int)($p['version']??0)===(int)$v['version'],409,'A befizetés közben megváltozott. Nyisd meg újra.');
   foreach($s['invoiceRequests']??[] as $invoice)if((($invoice['paymentId']??'')===$id||($invoice['bookingId']??'')===$p['bookingId'])&&!in_array($invoice['status']??'', ['failed','void','cancelled'],true))throw new InvalidArgumentException('A befizetéshez számlázás tartozik vagy folyamatban van. Előbb rendezd a számla helyesbítését.');
   if(!empty($p['invoice']))throw new InvalidArgumentException('Számlázott befizetés: előbb rendezd a számla helyesbítését.');
   $before=array_intersect_key($p,array_flip(['amount','method','paidAt','note','status']));
   if($remove){$p['status']='void';$p['deletedAt']=now()->toISOString();}
   else{
    $booking=collect($s['bookings'])->firstWhere('id',$p['bookingId']);abort_unless($booking,404);
    $other=collect($s['payments'])->filter(fn($other)=>$other['id']!==$id&&$other['bookingId']===$p['bookingId']&&$other['status']==='paid')->sum(fn($other)=>$other['amount']-($other['refundedAmount']??0));
    if($v['amount']>Offers::total($booking)-$other)throw new InvalidArgumentException('Az összeg nagyobb a fennmaradó fizetendő összegnél.');
    foreach(['amount','method','paidAt','note'] as $field)if(array_key_exists($field,$v))$p[$field]=$v[$field];
   }
   $p['version']=(int)($p['version']??0)+1;$p['updatedAt']=now()->toISOString();$p['history'][]=['action'=>$remove?'void':'edit','at'=>$p['updatedAt'],'by'=>$actor,'before'=>$before,'after'=>array_intersect_key($p,array_flip(['amount','method','paidAt','note','status']))];return $p;
  }abort(404);
 }
 public static function issuedPass(array &$s,string $id,array $v,bool $remove,string $actor):array{
  foreach($s['clientPasses'] as &$pass){if($pass['id']!==$id)continue;
   abort_unless(empty($pass['revokedAt']),422,'Ez a bérlet már visszavont.');
   abort_unless((int)($pass['version']??0)===(int)$v['version'],409,'A bérlet közben megváltozott. Nyisd meg újra.');
   $active=array_values(array_filter($s['bookings'],fn($b)=>($b['passId']??'')===$id&&in_array($b['status'],['pending','confirmed'],true)&&Time::parse($b['start'])->isFuture()));
   if($remove&&$active)throw new InvalidArgumentException('A bérlethez '.count($active).' jövőbeli foglalás tartozik. Előbb rendezd vagy mondd le ezeket a foglalásokat; a rendszer nem törli őket automatikusan.');
   $before=array_intersect_key($pass,array_flip(['remaining','expiresAt','validUntil','validityMode','revokedAt']));
   $pass['initialVisits']??=$pass['remaining']+count(array_filter($pass['reservations']??[],fn($status)=>in_array($status,['reserved','consumed'],true)));
   if($remove)$pass['revokedAt']=now()->toISOString();
   else{
    $pass['remaining']=$v['remaining'];$expiry=empty($v['expiresAt'])?null:Time::parse($v['expiresAt'])->toISOString();
    $same=(!$expiry&&empty($pass['expiresAt']))||($expiry&&!empty($pass['expiresAt'])&&Time::parse($expiry)->equalTo(Time::parse($pass['expiresAt'])));
    if(!$same){abort_unless($expiry,422,'A lejárat nem törölhető; adj meg új dátumot.');$pass['expiresAt']=$expiry;$pass['validUntil']=Time::parse($expiry)->setTimezone('Europe/Budapest')->toDateString();$pass['validityMode']='assigned';$pass['startsAt']??=now()->toISOString();foreach($active as $b)PassValidity::check($pass,$b);}
   }
   $pass['version']=(int)($pass['version']??0)+1;$pass['updatedAt']=now()->toISOString();$pass['history'][]=['at'=>$pass['updatedAt'],'action'=>$remove?'revoke':'edit','by'=>$actor,'before'=>$before,'after'=>array_intersect_key($pass,array_flip(['remaining','expiresAt','validUntil','validityMode','revokedAt']))];return $pass;
  }abort(404);
 }
}
