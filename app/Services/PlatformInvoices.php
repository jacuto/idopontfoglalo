<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Http};
use InvalidArgumentException;
final class PlatformInvoices {
 public function recordManual(int $paymentId,string $number,string $issuedOn):array {
  return DB::transaction(function()use($paymentId,$number,$issuedOn){
   $payment=DB::table('platform_payments')->where('id',$paymentId)->lockForUpdate()->first();abort_unless($payment,404);
   if((int)$payment->amount<=0)throw new InvalidArgumentException('A díjmentes próbaidőről nem rögzíthető számla.');
   $old=DB::table('platform_invoices')->where('payment_id',$paymentId)->first();
   if($old&&!(($old->source==='manual'&&$old->status==='issued')||$old->status==='not_created'))throw new InvalidArgumentException('Ehhez a befizetéshez már indult Billingo-számlázás. Előbb egyeztesd a meglévő számlát.');
   $number=trim($number);
   if($number==='')throw new InvalidArgumentException('Add meg a számla sorszámát.');
   $duplicate=DB::table('platform_invoices')->where('number',$number)->where('status','issued');if($old)$duplicate->where('id','!=',$old->id);
   if($duplicate->exists())throw new InvalidArgumentException('Ez a számlaszám már másik befizetéshez tartozik.');
   $values=['source'=>'manual','status'=>'issued','number'=>$number,'issued_on'=>$issuedOn,'reference'=>null,'updated_at'=>now()];
   if($old)DB::table('platform_invoices')->where('id',$old->id)->update($values);
   else {
    $buyer=app(Tenant::class)->run((int)$payment->provider_id,fn()=>app(SalonStore::class)->read()['settings']);
    DB::table('platform_invoices')->insert($values+['payment_id'=>$paymentId,'provider_id'=>$payment->provider_id,'buyer'=>json_encode($buyer,JSON_THROW_ON_ERROR),'created_at'=>now()]);
   }
   return ['ok'=>true,'number'=>$number,'issued_on'=>$issuedOn];
  });
 }
 public function issue(int $paymentId):array {
  $recorded=DB::table('platform_invoices')->where('payment_id',$paymentId)->where('status','issued')->first();
  if($recorded)return ['status'=>'issued','number'=>$recorded->number,'alreadyIssued'=>true];
  $key=config('integrations.platform_billingo.key');$block=config('integrations.platform_billingo.block');
  if(!$key||!$block)throw new InvalidArgumentException('Az üzemeltető saját Billingo API-kulcsa és számlatömbje még nincs beállítva.');
  $payment=DB::table('platform_payments')->find($paymentId);abort_unless($payment,404);
  if((int)$payment->amount<=0)throw new InvalidArgumentException('A díjmentes próbaidőről nem állítunk ki számlát.');
  $buyer=app(Tenant::class)->run((int)$payment->provider_id,fn()=>app(SalonStore::class)->read()['settings']);
  foreach(['billingName','billingZip','billingCity','billingAddress'] as $field)if(empty($buyer[$field]))throw new InvalidArgumentException('A szolgáltató számlázási adatai hiányosak. Előbb egészítse ki a Beállításoknál.');
  $invoice=DB::transaction(function()use($payment,$buyer){
   DB::table('platform_payments')->where('id',$payment->id)->lockForUpdate()->first();
   $old=DB::table('platform_invoices')->where('payment_id',$payment->id)->first();
   if($old){if($old->status==='issued')return $old;if($old->status==='not_created'){DB::table('platform_invoices')->where('id',$old->id)->update(['status'=>'issuing','updated_at'=>now()]);$old->status='issuing';return $old;}throw new InvalidArgumentException('Ehhez a befizetéshez már indult számlázás. A bizonytalan eredményt a Billingóban kell ellenőrizni; automatikus ismétlés nincs.');}
   $id=DB::table('platform_invoices')->insertGetId(['payment_id'=>$payment->id,'provider_id'=>$payment->provider_id,'status'=>'issuing','buyer'=>json_encode($buyer,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);return DB::table('platform_invoices')->find($id);
  });
  if($invoice->status==='issued')return ['status'=>'issued','number'=>$invoice->number,'alreadyIssued'=>true];
  try {
   $partner=Http::timeout(20)->withHeaders(['X-API-KEY'=>$key])->post('https://api.billingo.hu/v3/partners',['name'=>$buyer['billingName'],'address'=>['country_code'=>$buyer['billingCountry']??'HU','post_code'=>$buyer['billingZip'],'city'=>$buyer['billingCity'],'address'=>$buyer['billingAddress']], 'emails'=>empty($buyer['providerEmail'])?[]:[$buyer['providerEmail']],...!empty($buyer['billingTaxNumber'])?['taxcode'=>$buyer['billingTaxNumber']]:[]])->throw()->json();
   $result=Http::timeout(30)->withHeaders(['X-API-KEY'=>$key])->post('https://api.billingo.hu/v3/documents',[
    'partner_id'=>$partner['id'],'block_id'=>(int)$block,'type'=>'invoice','fulfillment_date'=>\Carbon\CarbonImmutable::parse($payment->paid_at)->format('Y-m-d'),'due_date'=>now()->format('Y-m-d'),'payment_method'=>$payment->method==='transfer'?'wire_transfer':'bankcard','language'=>'hu','currency'=>'HUF','conversion_rate'=>1,'electronic'=>true,'paid'=>true,
    'items'=>[['name'=>'Ráérő előfizetés – havi rendszerhasználat és naptárbővítések','unit_price'=>$payment->amount,'unit_price_type'=>'gross','quantity'=>1,'unit'=>'hónap','vat'=>'AAM']],
    'comment'=>'Ráérő előfizetés. Befizetésazonosító: '.$payment->id,
   ])->throw()->json();
   if(empty($result['id']))throw new \RuntimeException();
   DB::table('platform_invoices')->where('id',$invoice->id)->update(['status'=>'issued','issued_on'=>$result['invoice_date']??now('Europe/Budapest')->format('Y-m-d'),'reference'=>(string)$result['id'],'number'=>$result['invoice_number']??(string)$result['id'],'updated_at'=>now()]);
   return ['status'=>'issued','number'=>$result['invoice_number']??(string)$result['id'],'alreadyIssued'=>false];
  }catch(\Throwable){DB::table('platform_invoices')->where('id',$invoice->id)->update(['status'=>'unknown','updated_at'=>now()]);throw new InvalidArgumentException('A Billingo-kérés eredménye nem igazolható. Ne indíts új számlát: előbb ellenőrizd a Billingóban.');}
 }
 public function download(int $id,?int $providerId=null) {
  $q=DB::table('platform_invoices')->where('id',$id)->where('status','issued');if($providerId!==null)$q->where('provider_id',$providerId);$invoice=$q->first();abort_unless($invoice&&$invoice->source==='billingo'&&$invoice->reference,404);
  $key=config('integrations.platform_billingo.key');if(!$key)throw new InvalidArgumentException('A központi Billingo-kapcsolat nincs beállítva.');
  $r=Http::timeout(30)->withHeaders(['X-API-KEY'=>$key])->get('https://api.billingo.hu/v3/documents/'.rawurlencode($invoice->reference).'/download');
  if(!$r->successful()||!str_starts_with($r->body(),'%PDF-'))throw new InvalidArgumentException('A Billingo PDF-je most nem érhető el. Próbáld később, vagy kérd az üzemeltetőtől.');
  return response($r->body(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="raero-elofizetes-'.$id.'.pdf"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
 }

}
