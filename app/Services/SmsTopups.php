<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
final class SmsTopups
{
 public static function bank():array{$v=app(\App\Http\Controllers\PlatformController::class)->operator();return ['name'=>$v['name']??'','bank'=>$v['bank']??'','account'=>($v['iban']??'')?:($v['account']??''),'ready'=>!empty($v['name'])&&(!empty($v['iban'])||!empty($v['account']))];}
 public static function transfer(int $provider,int $amount,string $request):array{
  abort_unless($amount>=3000,422,'A minimum feltöltés 3 000 Ft.');$bank=self::bank();abort_unless($bank['ready'],422,'Az üzemeltető átutalási adatai még nincsenek megadva.');
  $row=DB::transaction(function()use($provider,$amount,$request){DB::table('sms_topups')->insertOrIgnore(['id'=>(string)Str::uuid(),'provider_id'=>$provider,'request_id'=>$request,'amount'=>$amount,'status'=>'awaiting_transfer','method'=>'transfer','created_at'=>now(),'updated_at'=>now()]);$row=DB::table('sms_topups')->where('provider_id',$provider)->where('request_id',$request)->lockForUpdate()->first();abort_unless((int)$row->amount===$amount&&$row->method==='transfer',422,'Ezzel az azonosítóval már másik feltöltés indult.');return $row;});
  return ['id'=>$row->id,'amount'=>(int)$row->amount,'status'=>$row->status,'bank'=>$bank,'reference'=>'SMS-'.$provider.'-'.strtoupper(substr(str_replace('-','',$row->id),0,12))];
 }
 public static function creditTransfer(array $v):array{
  abort_unless(DB::table('salon_state')->where('id',$v['providerId'])->exists(),404);$reference=mb_strtoupper(preg_replace('/\s+/u','',trim($v['bankReference'])));abort_unless(strlen($reference)>=3,422);
  return DB::transaction(function()use($v,$reference){SmsWallet::locked((int)$v['providerId']);$old=DB::table('sms_topups')->where('bank_reference',$reference)->lockForUpdate()->first();if($old){abort_unless((int)$old->provider_id===(int)$v['providerId']&&(int)$old->amount===(int)$v['amount']&&(empty($v['topupId'])||$old->id===$v['topupId']),422,'A banki tranzakció már más feltöltéshez tartozik.');return ['ok'=>true,'alreadyCredited'=>true];}
   if(!empty($v['topupId'])){$row=DB::table('sms_topups')->where('id',$v['topupId'])->lockForUpdate()->first();abort_unless($row&&(int)$row->provider_id===(int)$v['providerId']&&$row->method==='transfer'&&$row->status==='awaiting_transfer'&&(int)$row->amount===(int)$v['amount'],422,'A kiválasztott átutalási kérelem nem egyezik a jóváírással.');$id=$row->id;}
   else{$id=(string)Str::uuid();DB::table('sms_topups')->insert(['id'=>$id,'provider_id'=>$v['providerId'],'request_id'=>(string)Str::uuid(),'amount'=>$v['amount'],'method'=>'transfer','status'=>'awaiting_transfer','created_at'=>now(),'updated_at'=>now()]);}
   // A globally unique bank reference plus a unique ledger entry prevents duplicate credit.
   DB::table('sms_topups')->where('id',$id)->update(['status'=>'paid','bank_reference'=>$reference,'credited_at'=>now(),'credited_by'=>'platform_admin','updated_at'=>now()]);SmsWallet::credit((int)$v['providerId'],(int)$v['amount'],'topup:'.$id,'topup',['topupId'=>$id,'method'=>'transfer','bankReference'=>$reference,'creditedBy'=>'platform_admin']);return ['ok'=>true,'id'=>$id];
  });
 }
 public function checkout(int $provider,int $amount,string $request):array{
  abort_unless($amount>=3000,422,'A minimum feltöltés 3 000 Ft.');$key=config('integrations.platform_stripe.key');if(!$key)throw new \InvalidArgumentException('A központi Stripe-kapcsolat még nincs beállítva.');
  $row=DB::transaction(function()use($provider,$amount,$request){DB::table('sms_topups')->insertOrIgnore(['id'=>(string)Str::uuid(),'provider_id'=>$provider,'request_id'=>$request,'amount'=>$amount,'status'=>'creating','created_at'=>now(),'updated_at'=>now()]);$row=DB::table('sms_topups')->where('provider_id',$provider)->where('request_id',$request)->lockForUpdate()->first();abort_unless($row->method==='card'&&(int)$row->amount===$amount,422,'Ezzel a kérelemazonosítóval más összegű feltöltés indult.');return $row;});
  if($row->status==='paid')throw new \InvalidArgumentException('Ezt a feltöltést már jóváírtuk.');if($row->checkout_url)return ['url'=>$row->checkout_url,'id'=>$row->id];
  $meta=['type'=>'sms_topup','topup_id'=>$row->id,'provider_id'=>(string)$provider];$base=rtrim(config('app.url'),'/').'/?provider='.$provider.'&smsWallet=';
  try{$r=Http::timeout(20)->withToken($key)->withHeaders(['Idempotency-Key'=>'sms-topup-'.$row->id])->asForm()->post('https://api.stripe.com/v1/checkout/sessions',['mode'=>'payment','payment_method_types'=>['card'],'line_items'=>[['price_data'=>['currency'=>'huf','unit_amount'=>$amount*100,'product_data'=>['name'=>'Ráérő SMS-egyenleg feltöltése']],'quantity'=>1]],'metadata'=>$meta,'payment_intent_data'=>['metadata'=>$meta],'client_reference_id'=>$row->id,'billing_address_collection'=>'required','success_url'=>$base.'success','cancel_url'=>$base.'cancelled']);
   $v=$r->json();if(!$r->successful()||!is_string($v['id']??null)||parse_url($v['url']??'',PHP_URL_HOST)!=='checkout.stripe.com'||parse_url($v['url']??'',PHP_URL_SCHEME)!=='https')throw new \RuntimeException();
   DB::table('sms_topups')->where('id',$row->id)->whereNotIn('status',['paid','refunded'])->update(['status'=>'pending','session_id'=>$v['id'],'checkout_url'=>$v['url'],'updated_at'=>now()]);return ['url'=>$v['url'],'id'=>$row->id];
  }catch(\Throwable){DB::table('sms_topups')->where('id',$row->id)->where('status','creating')->update(['status'=>'unknown','updated_at'=>now()]);throw new \InvalidArgumentException('A feltöltési fizetés indítása nem igazolható. Ugyanezzel a kérelemmel próbáld újra; ne hozz létre párhuzamos fizetést.');}
 }
 /** Called only after the central Stripe signature has been verified. */
 public static function webhook(array $event,array $o):bool{
  if(($event['type']??'')==='checkout.session.completed'&&($o['metadata']['type']??'')==='sms_topup'){
   DB::transaction(function()use($o){$row=DB::table('sms_topups')->where('id',$o['metadata']['topup_id']??'')->lockForUpdate()->first();abort_unless($row,409,'A feltöltés mentése még folyamatban.');abort_unless($row->session_id&&$row->session_id===($o['id']??null),409,'A fizetési munkamenet mentése még folyamatban.');abort_unless((int)$row->provider_id===(int)($o['metadata']['provider_id']??0)&&($o['currency']??'')==='huf'&&(int)($o['amount_total']??0)===(int)$row->amount*100,422,'A feltöltés összege vagy tulajdonosa eltér.');if(($o['payment_status']??'')!=='paid')return;
    SmsWallet::credit((int)$row->provider_id,(int)$row->amount,'topup:'.$row->id,'topup',['topupId'=>$row->id,'sessionId'=>$row->session_id]);DB::table('sms_topups')->where('id',$row->id)->update(['status'=>$row->refunded>=$row->amount?'refunded':'paid','payment_intent'=>$o['payment_intent']??null,'updated_at'=>now()]);
   });return true;
  }
  if(($event['type']??'')==='charge.refunded'){
   $row=DB::table('sms_topups')->where('payment_intent',$o['payment_intent']??'')->first();if(!$row&&($o['metadata']['type']??'')==='sms_topup')$row=DB::table('sms_topups')->find($o['metadata']['topup_id']??'');if(!$row)return false;
   DB::transaction(function()use($row,$o){$row=DB::table('sms_topups')->where('id',$row->id)->lockForUpdate()->first();$refunded=min((int)$row->amount,(int)round(($o['amount_refunded']??0)/100));if($refunded<=(int)$row->refunded)return;
    SmsWallet::credit((int)$row->provider_id,-($refunded-(int)$row->refunded),'refund:'.$row->id.':'.$refunded,'refund',['topupId'=>$row->id]);DB::table('sms_topups')->where('id',$row->id)->update(['refunded'=>$refunded,'status'=>$refunded>=$row->amount?'refunded':$row->status,'updated_at'=>now()]);
   });return true;
  }
  return false;
 }
}
