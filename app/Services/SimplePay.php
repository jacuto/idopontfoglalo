<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

/** Hosted HUF card payments. Only signed server notifications can settle a booking. */
final class SimplePay
{
 public static function signature(string $body,string $key):string{return base64_encode(hash_hmac('sha384',$body,$key,true));}
 private function call(string $action,array $body,array $keys):array{
  $json=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$host=($keys['simplepay_environment']??'sandbox')==='live'?'secure.simplepay.hu':'sandbox.simplepay.hu';
  try{$r=Http::timeout(25)->connectTimeout(10)->withHeaders(['Signature'=>self::signature($json,$keys['simplepay_secret'])])->withBody($json,'application/json')->post('https://'.$host.'/payment/v2/'.$action);}catch(\Throwable){throw new \RuntimeException('A SimplePay-kérés eredménye nem igazolható.');}
  if(!$r->successful()||!hash_equals(self::signature($r->body(),$keys['simplepay_secret']),(string)$r->header('Signature')))throw new \RuntimeException('A SimplePay válasza nem hitelesíthető.');
  $v=$r->json();if(!is_array($v)||!empty($v['errorCodes']))throw new \RuntimeException('A SimplePay nem fogadta el a kérést.');return $v;
 }
 public function checkout(string $bookingId):array{
  $store=app(SalonStore::class);$keys=app(Connections::class)->all();if(empty($keys['simplepay_merchant'])||empty($keys['simplepay_secret']))throw new \InvalidArgumentException('Előbb állítsd be a saját SimplePay kereskedői fiókodat.');
  $order=$store->mutate(function(&$s)use($bookingId,$keys){$b=collect($s['bookings'])->firstWhere('id',$bookingId);abort_unless($b,404);abort_unless(in_array($b['status'],['pending','confirmed'],true),422,'Ez a foglalás nem fizethető.');$paid=collect($s['payments'])->where('bookingId',$bookingId)->where('status','paid')->sum(fn($p)=>$p['amount']-($p['refundedAmount']??0));$amount=Offers::total($b)-$paid;abort_unless($amount>0,422,'Nincs fennmaradó fizetendő összeg.');
   foreach($s['checkouts'] as $ref=>$old)if(($old['gateway']??'')==='simplepay'&&$old['bookingId']===$bookingId&&$old['status']!=='paid'&&\Carbon\CarbonImmutable::parse($old['createdAt'])->gt(now()->subMinutes(35))){if($old['amount']===$amount&&($old['status']??'')==='pending')return ['reuse'=>$old['url']];throw new \InvalidArgumentException('Ehhez a foglaláshoz már indult SimplePay-fizetés. Előbb ellenőrizd a korábbi tranzakciót.');}
   $ref='raero-'.app(Tenant::class)->id.'-'.Str::uuid();$s['checkouts'][$ref]=['type'=>'booking','gateway'=>'simplepay','bookingId'=>$bookingId,'amount'=>$amount,'status'=>'creating','merchant'=>$keys['simplepay_merchant'],'environment'=>$keys['simplepay_environment']??'sandbox','createdAt'=>now()->toISOString()];return ['ref'=>$ref,'amount'=>$amount,'booking'=>$b];
  });if(isset($order['reuse']))return ['url'=>$order['reuse']];
  $b=$order['booking'];$request=['salt'=>bin2hex(random_bytes(16)),'merchant'=>$keys['simplepay_merchant'],'orderRef'=>$order['ref'],'currency'=>'HUF','customerEmail'=>($b['email']??'')?:($keys['simplepay_customer_email']??''),'language'=>'HU','sdkVersion'=>'Raero-PHP-1.0','methods'=>['CARD'],'total'=>(string)$order['amount'],'timeout'=>now()->addMinutes(30)->toIso8601String(),'url'=>rtrim(config('app.url'),'/').'/simplepay/visszateres?provider='.app(Tenant::class)->id];
  if(!$request['customerEmail']){$store->mutate(function(&$s)use($order){$s['checkouts'][$order['ref']]['status']='failed';});throw new \InvalidArgumentException('A SimplePay-fizetéshez a vendég e-mail-címe szükséges.');}
  try{$v=$this->call('start',$request,$keys);$expectedHost=($keys['simplepay_environment']??'sandbox')==='live'?'secure.simplepay.hu':'sandbox.simplepay.hu';$url=$v['paymentUrl']??'';if(!is_string($url)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!==$expectedHost||empty($v['transactionId']))throw new \RuntimeException();
   $store->mutate(function(&$s)use($order,$v,$url){$s['checkouts'][$order['ref']]=array_replace($s['checkouts'][$order['ref']],['status'=>'pending','transactionId'=>(string)$v['transactionId'],'url'=>$url]);});return ['url'=>$url];
  }catch(\Throwable){$store->mutate(function(&$s)use($order){$s['checkouts'][$order['ref']]['status']='unknown';});throw new \InvalidArgumentException('A SimplePay-fizetés indítása nem igazolható. Ellenőrizd a SimplePay-fiókot, mielőtt új fizetést indítasz.');}
 }
 public function ipn(Request $r){
  $keys=app(Connections::class)->all();$raw=$r->getContent();abort_unless(!empty($keys['simplepay_secret'])&&hash_equals(self::signature($raw,$keys['simplepay_secret']),(string)$r->header('Signature')),400,'Érvénytelen SimplePay-aláírás.');
  try{$v=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){abort(400);}
  abort_unless(is_array($v)&&($v['merchant']??'')===($keys['simplepay_merchant']??null),400,'Eltérő SimplePay-kereskedő.');$ref=$v['orderRef']??'';$store=app(SalonStore::class);$s=$store->read();$checkout=$s['checkouts'][$ref]??null;abort_unless($checkout&&($checkout['gateway']??'')==='simplepay'&&$checkout['merchant']===$v['merchant'],409,'A tranzakció mentése még folyamatban.');
  if(($v['status']??'')==='FINISHED'&&($checkout['status']??'')!=='paid'){
   // IPN has no reliable total: obtain it from a separately signed server-side query.
   try{$query=$this->call('query',['salt'=>bin2hex(random_bytes(16)),'merchant'=>$keys['simplepay_merchant'],'sdkVersion'=>'Raero-PHP-1.0','orderRefs'=>[$ref]],$keys);}catch(\Throwable){return response()->json(['error'=>'A SimplePay-tranzakció ellenőrzése most nem sikerült. Újrapróbálás szükséges.'],503);}
   $transaction=collect($query['transactions']??[])->firstWhere('orderRef',$ref);abort_unless($transaction&&($transaction['status']??'')==='FINISHED'&&($transaction['currency']??'')==='HUF'&&(float)($transaction['total']??-1)===(float)$checkout['amount']&&(string)($transaction['transactionId']??'')===(string)($v['transactionId']??''),422,'A SimplePay-összeg vagy tranzakció nem egyezik.');
   $store->mutate(function(&$state)use($ref,$v,$transaction){$old=&$state['checkouts'][$ref];if($old['status']==='paid')return;abort_unless(empty($old['transactionId'])||(string)$old['transactionId']===(string)$v['transactionId'],422);$booking=collect($state['bookings'])->firstWhere('id',$old['bookingId']);abort_unless($booking,409);$state['payments'][]=['id'=>(string)Str::uuid(),'bookingId'=>$old['bookingId'],'amount'=>$old['amount'],'method'=>'card','status'=>'paid','paidAt'=>now()->toISOString(),'source'=>'simplepay','simplepayOrder'=>$ref,'simplepayTransaction'=>(string)$v['transactionId'],'createdAt'=>now()->toISOString(),'buyer'=>[]];$old['status']='paid';$old['transactionId']=(string)$v['transactionId'];});
  }
  $v['receiveDate']=now()->format('Y-m-d\TH:i:sO');$body=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);return response($body,200,['Content-Type'=>'application/json','Signature'=>self::signature($body,$keys['simplepay_secret'])]);
 }
 public function returned(Request $r){
  $keys=app(Connections::class)->all();$raw=base64_decode((string)$r->query('r',''),true);$signature=str_replace(' ','+',(string)$r->query('s',''));abort_unless($raw!==false&&!empty($keys['simplepay_secret'])&&hash_equals(self::signature($raw,$keys['simplepay_secret']),$signature),400,'A visszatérés nem hitelesíthető.');$v=json_decode($raw,true);abort_unless(is_array($v)&&($v['m']??$v['merchant']??'')===($keys['simplepay_merchant']??''),400);$ref=$v['o']??$v['orderRef']??'';$s=app(SalonStore::class)->read();$checkout=$s['checkouts'][$ref]??null;abort_unless($checkout&&($checkout['gateway']??'')==='simplepay',404);$b=collect($s['bookings'])->firstWhere('id',$checkout['bookingId']);abort_unless($b,404);
  // Browser return never grants a paid state. The signed IPN performs settlement.
  return redirect('/modositas?provider='.app(Tenant::class)->id.'&token='.$b['token'].'&payment=processing');
 }
}
