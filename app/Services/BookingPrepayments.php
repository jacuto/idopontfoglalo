<?php
namespace App\Services;

/** Server-side policy and immutable requirement for a guest booking. All amounts are whole HUF. */
final class BookingPrepayments
{
 public static function enabled(array $s):bool{return !empty($s['settings']['prepaymentEnabled'])&&app(PlatformConnections::class)->enabled('prepayment');}
 public static function operational(array $s):bool{return self::enabled($s)&&self::ready($s);}
 public static function allows(array $s,string $method):bool{return in_array($method,$s['settings']['prepaymentMethods']??['card','transfer'],true);}
 public static function bankReady(array $s):bool{return trim($s['settings']['prepaymentAccount']??'')!==''&&trim($s['settings']['prepaymentAccountName']??'')!=='';}
 public static function ready(array $s):bool{return (self::allows($s,'card')&&Payments::cardReady($s))||(self::allows($s,'szep')&&Payments::szepReady())||(self::allows($s,'transfer')&&self::bankReady($s));}
 public static function policy(array $settings,array $service):array{
  $mode=$service['prepaymentPolicy']??'inherit';
  if($mode==='inherit')return ['mode'=>$settings['prepaymentMode']??'optional','type'=>$settings['depositType']??'percent','value'=>(int)($settings['depositValue']??30)];
  return ['mode'=>$mode,'type'=>$service['depositType']??'percent','value'=>(int)($service['depositValue']??30)];
 }
 public static function target(array $s,array $b):int{
  $items=$b['serviceSnapshot']??[];$original=array_sum(array_column($items,'price'));$total=(int)Offers::total($b);if($original<=0||$total<=0)return 0;
  // Allocate the discounted total exactly across services, without rounding the basket upward.
  $allocated=0;$cumulative=0;$target=0;
  foreach($items as $item){$cumulative+=(int)$item['price'];$next=(int)floor($total*$cumulative/$original);$price=$next-$allocated;$allocated=$next;$p=self::policy($s['settings'],$item);
   if($p['mode']==='full')$target+=$price;
   elseif($p['mode']==='deposit')$target+=min($price,$p['type']==='fixed'?$p['value']:(int)ceil($price*min(100,$p['value'])/100));
  }return min($total,max(0,$target));
 }
 public static function attach(array $s,array &$b):void{
  if(empty($b['public'])||!self::operational($s))return;$target=self::target($s,$b);if(!$target)return;
  abort_unless(self::ready($s),422,'A szolgáltató előrefizetést kér, de nincs működő fizetési kapcsolat vagy teljes átutalási számlaadat beállítva. Kérj segítséget a szolgáltatótól.');
  $b['paymentRequirement']=['amount'=>$target,'targetStatus'=>$b['status']];$b['awaitingPayment']=true;$b['status']='pending';
 }
 public static function info(array $s,array $b):array{
  $settings=$s['settings'];$total=(int)Offers::total($b);$paid=(int)collect($s['payments'])->where('bookingId',$b['id'])->where('status','paid')->sum(fn($p)=>$p['amount']-($p['refundedAmount']??0));
  $target=(int)($b['paymentRequirement']['amount']??self::target($s,$b));$enabled=self::operational($s);$due=max(0,$target-$paid);
  return ['enabled'=>$enabled,'mode'=>$target>0&&$target<$total?'deposit':'full','required'=>!empty($b['paymentRequirement'])&&$enabled,'requiredAmount'=>$target,'requiredDue'=>$enabled?$due:0,'awaitingPayment'=>!empty($b['awaitingPayment'])&&$enabled,'balance'=>max(0,$total-$paid),'deposit'=>$due,'paid'=>$paid,'bankName'=>$settings['prepaymentBank']??'','accountName'=>$settings['prepaymentAccountName']??'','account'=>self::allows($s,'transfer')&&self::bankReady($s)?$settings['prepaymentAccount']:'','reference'=>'RAERO-'.app(Tenant::class)->id.'-'.substr(str_replace('-','',$b['id']),0,12),'terms'=>$settings['prepaymentTerms']??'','cardReady'=>$enabled&&self::allows($s,'card')&&Payments::cardReady($s),'szepReady'=>$enabled&&self::allows($s,'szep')&&Payments::szepReady()];
 }
 public static function amount(array $s,array $b,string $mode='full'):int{
  abort_unless(in_array($mode,['full','deposit'],true),422);$info=self::info($s,$b);abort_unless($info['enabled'],422,'Az előrefizetés nincs bekapcsolva.');
  if($mode==='deposit')abort_unless($info['mode']==='deposit',422,'Nincs beállítva részösszegű előleg.');$amount=$mode==='deposit'?$info['deposit']:$info['balance'];abort_unless($amount>0,422,'Nincs fennmaradó fizetendő összeg.');return $amount;
 }
 public static function validateConfig(array $s):void{
  $settings=$s['settings'];self::validateDeposit($settings);
  foreach($s['services'] as $service)self::validateDeposit($service);

 }
 private static function validateDeposit(array $v):void{if(($v['depositType']??'percent')==='percent'&&(int)($v['depositValue']??30)>100)throw new \InvalidArgumentException('A százalékos előleg legfeljebb 100% lehet.');}
 public static function reconcile(array &$s):void{
  foreach($s['bookings'] as &$b){if(empty($b['awaitingPayment'])||!in_array($b['status'],['pending','confirmed'],true))continue;$info=self::info($s,$b);if($info['enabled']&&$info['requiredDue']>0)continue;
   $b['awaitingPayment']=false;$b['status']=$b['paymentRequirement']['targetStatus']??'pending';$b['updatedAt']=now()->toISOString();app(Notifications::class)->enqueue($b,$s,'updated');
  }unset($b);
 }
}
