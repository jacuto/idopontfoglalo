<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Provider-owned hosted payments; the browser and unsigned callbacks never settle money. */
final class Barion
{
 private static function validId(mixed $id):bool{return is_string($id)&&(bool)preg_match('/^(?:[a-f0-9]{32}|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/i',$id);}
 private static function sameId(string $a,string $b):bool{return strcasecmp(str_replace('-','',$a),str_replace('-','',$b))===0;}
 private static function host(string $environment):string{return $environment==='live'?'api.barion.com':'api.test.barion.com';}
 public function checkout(string $bookingId,string $mode='full'):array{
  $store=app(SalonStore::class);$keys=app(Connections::class)->all();abort_unless(!empty($keys['barion_pos_key'])&&!empty($keys['barion_payee']),422,'Előbb állítsd be a saját Barion POSKey kulcsodat és a Barion-fiókod e-mail-címét.');
  $order=$store->mutate(function(&$s)use($bookingId,$mode,$keys){$b=collect($s['bookings'])->firstWhere('id',$bookingId);abort_unless($b,404);abort_unless(in_array($b['status'],['pending','confirmed'],true),422,'Ez a foglalás nem fizethető.');$amount=BookingPrepayments::amount($s,$b,$mode);
   foreach($s['checkouts'] as $old)if(($old['type']??'')==='booking'&&$old['bookingId']===$bookingId&&!in_array($old['status']??'pending',['paid','failed'],true)&&(($old['status']??'')==='unknown'||\Carbon\CarbonImmutable::parse($old['createdAt'])->gt(now()->subMinutes(35)))){if(($old['gateway']??'')==='barion'&&$old['amount']===$amount&&$old['status']==='pending'&&!empty($old['url']))return ['reuse'=>$old['url']];throw new \InvalidArgumentException('Ehhez a foglaláshoz már indult fizetés. Előbb ellenőrizd a korábbi tranzakciót.');}
   $ref=(string)Str::uuid();$s['checkouts'][$ref]=['type'=>'booking','gateway'=>'barion','bookingId'=>$bookingId,'amount'=>$amount,'status'=>'creating','payee'=>$keys['barion_payee'],'environment'=>$keys['barion_environment']??'sandbox','createdAt'=>now()->toISOString()];return ['ref'=>$ref,'amount'=>$amount,'booking'=>$b,'name'=>$s['settings']['name']];
  });if(isset($order['reuse']))return ['url'=>$order['reuse']];$b=$order['booking'];$base=rtrim(config('app.url'),'/');$env=$keys['barion_environment']??'sandbox';
  $payload=['POSKey'=>$keys['barion_pos_key'],'PaymentType'=>'Immediate','GuestCheckOut'=>true,'FundingSources'=>['All'],'PaymentRequestId'=>$order['ref'],'Locale'=>'hu-HU','Currency'=>'HUF','RedirectUrl'=>$base.'/modositas?provider='.app(Tenant::class)->id.'&token='.$b['token'].'&payment=processing','CallbackUrl'=>$base.'/api/barion/callback?provider='.app(Tenant::class)->id,'Transactions'=>[['POSTransactionId'=>$order['ref'].'-1','Payee'=>$keys['barion_payee'],'Total'=>$order['amount'],'Items'=>[['Name'=>mb_substr(($mode==='deposit'?'Előleg – ':'Foglalás – ').$order['name'],0,200),'Description'=>'Időpontfoglalás','Quantity'=>1,'Unit'=>'alkalom','UnitPrice'=>$order['amount'],'ItemTotal'=>$order['amount'],'SKU'=>$b['id']]]]]];
  try{$r=Http::timeout(25)->connectTimeout(10)->post('https://'.self::host($env).'/v2/Payment/Start',$payload);$v=$r->json();$url=$v['GatewayUrl']??'';$host=$env==='live'?'secure.barion.com':'secure.test.barion.com';if(!$r->successful()||!empty($v['Errors'])||!self::validId($v['PaymentId']??'')||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!==$host)throw new \RuntimeException('Nem igazolt Barion-válasz.');
   $store->mutate(function(&$s)use($order,$v,$url){$s['checkouts'][$order['ref']]=array_replace($s['checkouts'][$order['ref']],['paymentId'=>$v['PaymentId'],'url'=>$url,'status'=>'pending']);});return ['url'=>$url];
  }catch(\Throwable $e){SystemIssues::capture($e,'barion',app(Tenant::class)->id);$store->mutate(function(&$s)use($order){$s['checkouts'][$order['ref']]['status']='unknown';});throw new \InvalidArgumentException('A Barion-fizetés indítása nem igazolható. Új fizetés előtt ellenőrizd a Barion-fiókot.');}
 }
 public function callback(Request $r){
  $paymentId=$r->input('PaymentId');abort_unless(self::validId($paymentId),422);$store=app(SalonStore::class);$checkout=collect($store->read()['checkouts'])->first(fn($v)=>($v['gateway']??'')==='barion'&&self::sameId($v['paymentId']??'',$paymentId));abort_unless($checkout,409,'A Barion-fizetés még nincs azonosítva.');if(($checkout['status']??'')==='paid')return response()->json(['received'=>true]);$keys=app(Connections::class)->all();abort_unless(!empty($keys['barion_pos_key']),503);
  // Always obtain financial state using the merchant's secret. No callback status/amount is trusted.
  try{$response=Http::timeout(20)->connectTimeout(10)->withHeaders(['x-pos-key'=>$keys['barion_pos_key']])->get('https://'.self::host($checkout['environment']).'/v4/payment/'.rawurlencode($paymentId).'/paymentstate');if(!$response->successful())throw new \RuntimeException();$v=$response->json();}catch(\Throwable $e){SystemIssues::capture($e,'barion',app(Tenant::class)->id);return response()->json(['error'=>'A Barion-ellenőrzés most nem sikerült. Ismételd meg az értesítést.'],503);}
  $s=$store->read();$ref=$v['PaymentRequestId']??'';$old=$s['checkouts'][$ref]??null;
  abort_unless($old&&($old['gateway']??'')==='barion'&&self::sameId($old['paymentId']??'',$paymentId)&&self::sameId($v['PaymentId']??'',$paymentId),422,'Eltérő Barion-tranzakció.');
  if(($v['Status']??'')==='Succeeded'){
   abort_unless(($v['Currency']??'')==='HUF'&&(float)($v['Total']??-1)===(float)$old['amount'],422,'Eltérő Barion-összeg.');
   $store->mutate(function(&$s)use($ref,$paymentId){$old=&$s['checkouts'][$ref];if($old['status']==='paid')return;abort_unless(collect($s['bookings'])->contains('id',$old['bookingId']),409);$s['payments'][]=['id'=>(string)Str::uuid(),'bookingId'=>$old['bookingId'],'amount'=>$old['amount'],'method'=>'card','status'=>'paid','paidAt'=>now()->toISOString(),'createdAt'=>now()->toISOString(),'source'=>'barion','barionPaymentId'=>$paymentId,'buyer'=>[]];$old['status']='paid';});
  }elseif(in_array($v['Status']??'',['Canceled','Expired','Failed'],true))$store->mutate(function(&$s)use($ref){if($s['checkouts'][$ref]['status']!=='paid')$s['checkouts'][$ref]['status']='failed';});
  return response()->json(['received'=>true]);
 }
}
