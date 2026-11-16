<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt};
use Illuminate\Support\Str;

/** Amounts are whole HUF. The provider wallet row serializes reservations and charges. */
final class SmsWallet
{
 public static function price():int{return max(1,(int)config('integrations.sms_segment_price',25));}
 public static function isSms(array $payload):bool{return ($payload['channel']??'')==='sms';}
 public static function segments(string $text):int{$e=BulkGateSms::encoding($text);return $e['units']<=($e['unicode']?70:160)?1:(int)ceil($e['units']/($e['unicode']?67:153));}
 public static function locked(int $provider):object{DB::table('sms_wallets')->insertOrIgnore(['provider_id'=>$provider,'created_at'=>now(),'updated_at'=>now()]);return DB::table('sms_wallets')->where('provider_id',$provider)->lockForUpdate()->first();}
 public static function credit(int $provider,int $amount,string $reference,string $kind='topup',array $details=[]):bool{
  return DB::transaction(function()use($provider,$amount,$reference,$kind,$details){self::locked($provider);$inserted=DB::table('sms_ledger')->insertOrIgnore(['provider_id'=>$provider,'reference'=>$provider.':'.$reference,'kind'=>$kind,'amount'=>$amount,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);if(!$inserted)return false;DB::table('sms_wallets')->where('provider_id',$provider)->increment('balance',$amount,['updated_at'=>now(),'alert_message_id'=>null]);return true;});
 }
 public static function welcome(object $payment):void{
  if((int)$payment->amount<=0)return;
  $first=DB::table('platform_payments')->where('provider_id',$payment->provider_id)->where('amount','>',0)->orderBy('id')->value('id');
  if((int)$first!==(int)$payment->id)return;
  self::credit((int)$payment->provider_id,500,'welcome-bonus','gift',['paymentId'=>(int)$payment->id]);
 }
 public static function info(int $provider):array{
  $w=DB::table('sms_wallets')->where('provider_id',$provider)->first();$balance=(int)($w->balance??0);$reserved=(int)($w->reserved??0);
  return ['balance'=>$balance,'reserved'=>$reserved,'available'=>$balance-$reserved,'price'=>self::price(),'packages'=>[3000,5000,10000],'minimumTopup'=>3000,'transfer'=>SmsTopups::bank(),'blocked'=>DB::table('deliveries')->where('provider_id',$provider)->where('status','waiting_balance')->count(),'ledger'=>DB::table('sms_ledger')->where('provider_id',$provider)->latest('id')->limit(100)->get(),'topups'=>DB::table('sms_topups')->where('provider_id',$provider)->select('id','amount','refunded','status','invoice_number','invoice_issued_on','created_at')->latest('created_at')->limit(50)->get()];
 }
 /** Reserve every due SMS for this provider together, before any can leave the process. */
 public static function prepare(int $provider):array{
  if($provider<1)return ['skipped'=>0,'failed'=>0,'blocked'=>0];
  return app(Tenant::class)->run($provider,fn()=>DB::transaction(function()use($provider){
   $stats=['skipped'=>0,'failed'=>0,'blocked'=>0];$wallet=self::locked($provider);$state=app(SalonStore::class)->read();$jobs=DB::table('deliveries')->where('provider_id',$provider)->whereIn('status',['pending','blocked','waiting_balance'])->where('available_at','<=',now())->orderBy('id')->lockForUpdate()->get();$entries=[];$total=0;
   foreach($jobs as $job){try{$p=json_decode(Crypt::decryptString($job->payload),true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){continue;}if(!is_array($p)||!self::isSms($p))continue;
    $old=DB::table('sms_reservations')->where('delivery_id',$job->id)->first();if($old&&in_array($old->status,['reserved','unknown','sent'],true))continue;
    try{$content=app(Notifications::class)->smsContent($p,$state);}catch(\Throwable){DB::table('deliveries')->where('id',$job->id)->update(['status'=>'failed','error'=>'Az SMS adatai nem állíthatók össze. Ellenőrizd a címzettet és a foglalást.','updated_at'=>now()]);$stats['failed']++;continue;}
    if(!$content){DB::table('deliveries')->where('id',$job->id)->update(['status'=>'skipped','updated_at'=>now()]);$stats['skipped']++;continue;}
    $e=BulkGateSms::encoding($content['text']);if($e['units']>($e['unicode']?268:612)||!preg_match('/^\+[1-9]\d{7,14}$/',Contact::e164($content['recipient']))){DB::table('deliveries')->where('id',$job->id)->update(['status'=>'failed','error'=>'Az SMS szövege túl hosszú, vagy a telefonszám hibás.','updated_at'=>now()]);$stats['failed']++;continue;}
    $segments=self::segments($content['text']);$amount=$segments*self::price();$entries[]=[$job,$content,$segments,$amount,$old];$total+=$amount;
   }
   if(!$entries)return $stats;
   if($wallet->balance-$wallet->reserved<$total){$ids=array_map(fn($e)=>$e[0]->id,$entries);DB::table('deliveries')->whereIn('id',$ids)->update(['status'=>'waiting_balance','error'=>'Nincs elegendő SMS-egyenleg a teljes küldési csoporthoz. Egyik új SMS-t sem indítottuk el.','updated_at'=>now()]);self::alert($provider,$wallet,count($entries),$total);$stats['blocked']+=count($entries);return $stats;}
   $batch=(string)Str::uuid();foreach($entries as [$job,$content,$segments,$amount,$old]){
    DB::table('sms_reservations')->updateOrInsert(['delivery_id'=>$job->id],['provider_id'=>$provider,'batch_id'=>$batch,'generation'=>($old->generation??0)+1,'segments'=>$segments,'unit_price'=>self::price(),'amount'=>$amount,'status'=>'reserved','payload'=>Crypt::encryptString(json_encode($content,JSON_THROW_ON_ERROR)),'provider_reference'=>null,'created_at'=>now(),'updated_at'=>now()]);
    DB::table('deliveries')->where('id',$job->id)->update(['status'=>'pending','error'=>null,'updated_at'=>now()]);
   }
   DB::table('sms_wallets')->where('provider_id',$provider)->increment('reserved',$total,['updated_at'=>now()]);
   return $stats;
  }));
 }
 private static function alert(int $provider,object $wallet,int $count,int $required):void{
  $available=max(0,(int)$wallet->balance-(int)$wallet->reserved);$text='Az SMS-küldés leállt: a teljes, '.$count.' üzenetes csoporthoz '.$required.' Ft szükséges, a szabad egyenleg '.$available.' Ft. A csoportból egy SMS sem indult el. Tölts fel az SMS-egyenleg oldalon; feltöltés után az aktuális értesítéseket újra ellenőrizzük. Az e-mailes küldést ez nem állítja le.';
  $existing=$wallet->alert_message_id?DB::table('platform_messages')->find($wallet->alert_message_id):null;
  if($existing){if($existing->text!==$text)DB::table('platform_messages')->where('id',$existing->id)->update(['text'=>$text,'read_at'=>null,'updated_at'=>now()]);return;}
  $id=DB::table('platform_messages')->insertGetId(['recipient_type'=>'provider','recipient_id'=>$provider,'sender'=>'platform','text'=>$text,'created_at'=>now(),'updated_at'=>now()]);DB::table('sms_wallets')->where('provider_id',$provider)->update(['alert_message_id'=>$id]);
 }
 public static function item(int $delivery):?object{return DB::table('sms_reservations')->where('delivery_id',$delivery)->first();}
 public static function finish(int $delivery,string $result,?string $reference=null):void{
  $item=self::item($delivery);if(!$item)return;
  DB::transaction(function()use($delivery,$result,$reference,$item){self::locked((int)$item->provider_id);$row=DB::table('sms_reservations')->where('delivery_id',$delivery)->lockForUpdate()->first();if(!in_array($row->status,['reserved','unknown'],true))return;
   if($result==='unknown'){DB::table('sms_reservations')->where('delivery_id',$delivery)->update(['status'=>'unknown','provider_reference'=>$reference,'updated_at'=>now()]);return;}
   DB::table('sms_wallets')->where('provider_id',$row->provider_id)->decrement('reserved',$row->amount,['updated_at'=>now()]);
   if($result==='sent'){DB::table('sms_wallets')->where('provider_id',$row->provider_id)->decrement('balance',$row->amount);DB::table('sms_ledger')->insertOrIgnore(['provider_id'=>$row->provider_id,'reference'=>$row->provider_id.':sms:'.$delivery.':'.$row->generation,'kind'=>'sms','amount'=>-$row->amount,'details'=>json_encode(['deliveryId'=>$delivery,'segments'=>$row->segments,'unitPrice'=>$row->unit_price,'reference'=>$reference]),'created_at'=>now(),'updated_at'=>now()]);}
   DB::table('sms_reservations')->where('delivery_id',$delivery)->update(['status'=>$result==='sent'?'sent':'released','provider_reference'=>$reference,'updated_at'=>now()]);
  });
 }
 public static function send(object $job,array $payload,array $state):?string{
  $item=self::item($job->id);if(!$item||$item->status!=='reserved')throw new \InvalidArgumentException('Az SMS-hez nincs lefoglalt fedezet.');
  $content=json_decode(Crypt::decryptString($item->payload),true);$current=app(Notifications::class)->smsContent($payload,$state);
  if(!$current||$current!==$content){self::finish($job->id,'released');return null;}
  try{$reference=app(BulkGateSms::class)->send($content['recipient'],$content['text'],app(Connections::class)->all());}catch(SmsDeliveryException $e){self::finish($job->id,$e->uncertain?'unknown':'released');throw $e;}
  try{self::finish($job->id,'sent',$reference);}catch(\Throwable){throw new SmsDeliveryException('A BulkGate átvette az SMS-t, de az elszámolás megszakadt. Ellenőrizd a küldést; újraküldés nem indult.',true);}
  return $reference;
 }
}
