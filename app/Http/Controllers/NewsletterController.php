<?php
namespace App\Http\Controllers;
use App\Services\{Newsletters,Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,RateLimiter};
final class NewsletterController {
 public function consent(Request $r){
  $provider=$r->session()->get('admin')===true;$id=$provider?(int)$r->session()->get('provider_id',1):(int)$r->session()->get('guest_id',0);abort_unless($id,401);
  $audience=$provider?'provider':'guest';
  if($r->isMethod('GET'))return response()->json(['subscribed'=>(bool)DB::table('newsletter_consents')->where('audience',$audience)->where('subject_id',$id)->whereNotNull('consented_at')->whereNull('unsubscribed_at')->exists()]);
  $v=$r->validate(['subscribed'=>'required|boolean']);
  $email=$provider?DB::table('providers')->where('id',$id)->value('email'):DB::table('guest_accounts')->where('id',$id)->where('email_verified',true)->value('email');
  if($provider&&$id===1)$email=app(\App\Services\SalonStore::class)->read()['settings']['providerEmail'];
  abort_unless($email,422,'A feliratkozáshoz ellenőrzött emailcím szükséges.');
  DB::table('newsletter_consents')->updateOrInsert(['audience'=>$audience,'subject_id'=>$id],['email'=>mb_strtolower($email),'consented_at'=>$v['subscribed']?now():null,'unsubscribed_at'=>$v['subscribed']?null:now(),'updated_at'=>now(),'created_at'=>now()]);return response()->json(['ok'=>true]);
 }
 public function unsubscribe(Request $r,int $id){abort_unless($r->hasValidSignature(),403);$c=DB::table('newsletter_consents')->find($id);abort_unless($c,404);DB::table('newsletter_consents')->where('email',$c->email)->update(['unsubscribed_at'=>now(),'updated_at'=>now()]);return response('Sikeresen leiratkoztál a Ráérő hírleveleiről.',200,['Content-Type'=>'text/plain; charset=UTF-8']);}
 public function handle(Request $r){
  abort_unless($r->session()->get('platform_admin')===true,401);
  $id=$r->route('id');$action=$r->route('action');
  if($r->isMethod('GET'))return response()->json(['configured'=>app(Newsletters::class)->ready(),'campaigns'=>DB::table('newsletters')->latest('id')->get(),'deliveries'=>DB::table('newsletter_deliveries')->select('id','newsletter_id','email','status','error','updated_at')->latest('id')->limit(300)->get()]);
  if($action==='test'){
   $v=$r->validate(['email'=>'required|email|max:200']);$n=DB::table('newsletters')->find($id);abort_unless($n,404);$key='newsletter-test:'.$r->ip();\App\Services\AttemptLimit::check($key,5);RateLimiter::hit($key,60);
   app(Newsletters::class)->send($v['email'],'[TESZT] '.$n->subject,$n->content);return response()->json(['accepted'=>true]);
  }
  if($action==='schedule'){
   $v=$r->validate(['scheduledAt'=>'required|date|after_or_equal:now']);$updated=DB::table('newsletters')->where('id',$id)->where('status','draft')->update(['status'=>'scheduled','scheduled_at'=>\Carbon\CarbonImmutable::parse($v['scheduledAt']),'updated_at'=>now()]);abort_unless($updated,422,'Csak piszkozat ütemezhető.');return response()->json(['ok'=>true]);
  }
  $v=$r->validate(['subject'=>'required|string|max:200','content'=>'required|string|max:50000','audience'=>'required|in:provider,guest']);
  if($id){$updated=DB::table('newsletters')->where('id',$id)->where('status','draft')->update($v+['updated_at'=>now()]);abort_unless($updated,422,'Csak piszkozat szerkeszthető.');}
  else $id=DB::table('newsletters')->insertGetId($v+['status'=>'draft','created_at'=>now(),'updated_at'=>now()]);return response()->json(['id'=>$id],201);
 }
}
