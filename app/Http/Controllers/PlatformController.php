<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Settings,Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,RateLimiter};
final class PlatformController {
 public function handle(Request $r) {
  $path=$r->path();$method=$r->method();
  if($path==='api/platform/login'&&$method==='POST'){
   $key='platform-login:'.$r->ip();\App\Services\AttemptLimit::check($key,5);
   $hash=config('operator.password_hash');abort_unless($hash,503,'Előbb futtasd a szerveren: php artisan raero:platform-password');
   if(!Hash::check((string)$r->input('password'),$hash)){RateLimiter::hit($key,60);abort(401,'Hibás jelszó.');}
   RateLimiter::clear($key);$r->session()->regenerate();$r->session()->put('platform_admin',true);return response()->json(['ok'=>true]);
  }
  abort_unless($r->session()->get('platform_admin')===true,401);
  if(preg_match('#^api/platform/invoices/([1-9][0-9]*)/pdf$#',$path,$m)&&$method==='GET')return app(\App\Services\PlatformInvoices::class)->download((int)$m[1]);
  if(preg_match('#^api/platform/invoices/([1-9][0-9]*)/reconcile$#',$path,$m)&&$method==='PATCH'){
   $v=$r->validate(['decision'=>'required|in:issued,not_created','confirmed'=>'required|accepted','number'=>'required_if:decision,issued|nullable|string|max:100','reference'=>'required_if:decision,issued|nullable|string|max:100']);
   $updated=DB::table('platform_invoices')->where('id',$m[1])->where('status','unknown')->update(['status'=>$v['decision'],'number'=>$v['number']??null,'reference'=>$v['reference']??null,'updated_at'=>now()]);abort_unless($updated,422,'Csak bizonytalan számla egyeztethető.');return response()->json(['ok'=>true]);
  }
  if($path==='api/platform/connections'&&$method==='GET')return response()->json(app(\App\Services\PlatformConnections::class)->status());
  if($path==='api/platform/connections'&&$method==='PUT'){
   $v=$r->validate(['google_client_id'=>'nullable|string|max:300','google_client_secret'=>'nullable|string|max:1000','facebook_client_id'=>'nullable|string|max:300','facebook_client_secret'=>'nullable|string|max:1000','apple_client_id'=>'nullable|string|max:300','apple_team_id'=>'nullable|string|max:100','apple_key_id'=>'nullable|string|max:100','apple_private_key'=>'nullable|string|max:10000','twilio_sid'=>'nullable|string|max:200','twilio_token'=>'nullable|string|max:1000','twilio_from'=>'nullable|string|max:200','stripe_key'=>'nullable|string|max:1000','stripe_price'=>'nullable|string|max:200','stripe_webhook_secret'=>'nullable|string|max:1000','billingo_key'=>'nullable|string|max:1000','billingo_block'=>'nullable|integer|min:1','smtp_host'=>'nullable|string|max:200','smtp_port'=>'nullable|integer|between:1,65535','smtp_username'=>'nullable|string|max:250','smtp_password'=>'nullable|string|max:1000','mail_from'=>'nullable|email|max:200','mail_name'=>'nullable|string|max:150']);
   app(\App\Services\PlatformConnections::class)->save($v);return response()->json(['ok'=>true]);
  }
  if(preg_match('#^api/platform/providers/([1-9][0-9]*)/access$#',$path,$m)){
   abort_unless(DB::table('salon_state')->where('id',$m[1])->exists(),404);
   if($method==='GET')return response()->json(app(Tenant::class)->run((int)$m[1],fn()=>array_intersect_key(app(\App\Services\Connections::class)->all(),array_flip(['admin_google_emails','admin_facebook_ids','admin_apple_ids']))));
   $v=$r->validate(['admin_google_emails'=>'nullable|string|max:2000','admin_facebook_ids'=>'nullable|string|max:2000','admin_apple_ids'=>'nullable|string|max:2000']);app(Tenant::class)->run((int)$m[1],fn()=>app(\App\Services\Connections::class)->save($v));return response()->json(['ok'=>true]);
  }
  if(preg_match('#^api/platform/providers/([1-9][0-9]*)/plan$#',$path,$m)&&$method==='PATCH') {
   $v=$r->validate(['calendarLimit'=>'required|integer|between:1,100']);
   app(Tenant::class)->run((int)$m[1],fn()=>app(SalonStore::class)->mutate(function(&$s)use($v){$s['subscription']['calendarLimit']=$v['calendarLimit'];}));return response()->json(['ok'=>true]);
  }
  if($path==='api/platform/recipients'&&$method==='GET')return response()->json(['providers'=>DB::table('salon_state')->pluck('id'), 'guests'=>DB::table('guest_accounts')->select('id','name','email')->get()]);
  if($path==='api/platform/invoices'&&$method==='GET')return response()->json(DB::table('platform_invoices')->select('id','payment_id','provider_id','status','number','reference','updated_at')->latest('id')->get());
  if($path==='api/platform/invoices'&&$method==='POST'){$v=$r->validate(['paymentId'=>'required|integer|min:1']);return response()->json(app(\App\Services\PlatformInvoices::class)->issue($v['paymentId']));}
  if($path==='api/platform/providers'&&$method==='GET'){
   $rows=[];foreach(DB::table('salon_state')->get() as $row){$s=Settings::upgrade(json_decode($row->data,true));$rows[]=['id'=>$row->id,'name'=>$s['settings']['name'],'subscription'=>$s['subscription']];}return response()->json($rows);
  }
  if($path==='api/platform/providers'&&$method==='POST'){
   $v=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:200','password'=>'required|string|min:12|max:200']);
   $id=DB::transaction(function()use($v){
    DB::table('salon_state')->where('id',1)->lockForUpdate()->first();$id=max(2,(int)DB::table('salon_state')->max('id')+1);
    DB::table('providers')->insert(['id'=>$id,'name'=>$v['name'],'email'=>$v['email'],'password_hash'=>Hash::make($v['password']),'trial_started_at'=>now(),'trial_ends_at'=>now()->addMonthsNoOverflow(2),'created_at'=>now(),'updated_at'=>now()]);
    $s=SalonStore::initial();$s['staff']=[['id'=>'owner','name'=>$v['name'],'role'=>'Szolgáltató']];$s['services']=[];$s['settings']=array_replace($s['settings'],['name'=>$v['name'],'providerEmail'=>$v['email'],'businessMode'=>'solo','primaryStaffId'=>'owner']);
    $s['subscription']=['plan'=>'basic','status'=>'trialing','trialStartedAt'=>now()->toISOString(),'trialEndsAt'=>now()->addMonthsNoOverflow(2)->toISOString(),'price'=>4000,'calendarLimit'=>1];
    DB::table('salon_state')->insert(['id'=>$id,'data'=>json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);return $id;
   });return response()->json(['id'=>$id],201);
  }
  if($path==='api/platform/payments'&&$method==='GET')return response()->json(DB::table('platform_payments')->latest('id')->get());
  if($path==='api/platform/payments'&&$method==='POST'){
   $v=$r->validate(['providerId'=>'required|integer|min:1','reference'=>'required|string|max:150','paidAt'=>'required|date','chargeId'=>'nullable|string|max:100']);
   return response()->json(app(\App\Services\Subscriptions::class)->transfer($v['providerId'],$v['reference'],$v['paidAt'],$v['chargeId']??null));
  }
  if($path==='api/platform/operator'&&$method==='GET')return response()->json($this->operator());
  if($path==='api/platform/operator'&&$method==='PUT'){
   $rules=[];foreach(['name','country','zip','city','address','taxNumber','registrationNumber','bank','account','iban','bic'] as $key)$rules[$key]='required|string|max:250';
   $v=$r->validate($rules);$v['vat']='AAM';DB::table('platform_settings')->updateOrInsert(['id'=>1],['data'=>json_encode($v,JSON_THROW_ON_ERROR)]);return response()->json(['ok'=>true]);
  }
  abort(404);
 }
 public function operator():array {$row=DB::table('platform_settings')->find(1);$defaults=config('operator');unset($defaults['password_hash']);return array_replace($defaults,$row?json_decode($row->data,true):[]);}
}
