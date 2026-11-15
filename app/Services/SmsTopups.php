<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
final class SmsTopups
{
 public function checkout(int $provider,int $amount,string $request):array{
  $key=config('integrations.platform_stripe.key');if(!$key)throw new \InvalidArgumentException('A központi Stripe-kapcsolat még nincs beállítva.');
  $row=DB::transaction(function()use($provider,$amount,$request){DB::table('sms_topups')->insertOrIgnore(['id'=>(string)Str::uuid(),'provider_id'=>$provider,'request_id'=>$request,'amount'=>$amount,'status'=>'creating','created_at'=>now(),'updated_at'=>now()]);$row=DB::table('sms_topups')->where('provider_id',$provider)->where('request_id',$request)->lockForUpdate()->first();abort_unless((int)$row->amount===$amount,422,'Ezzel a kérelemazonosítóval más összegű feltöltés indult.');return $row;});
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
