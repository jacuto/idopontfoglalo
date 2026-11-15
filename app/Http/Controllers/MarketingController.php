<?php
namespace App\Http\Controllers;
use App\Services\{MarketingConnections,MarketingCampaigns,MarketingGateway,MarketingException,SalonStore,Tenant,StaffAccess,Contact,SafeHtml};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,RateLimiter};
use Illuminate\Support\Str;
final class MarketingController
{
 private function owner(Request $r):int{
  if($r->is('api/platform/marketing*')){abort_unless($r->session()->get('platform_admin')===true,403);return 0;}
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r)&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);abort_unless(MarketingConnections::allowed(app(SalonStore::class)->read()),402,'A hírlevélküldés Pro funkció.');return app(Tenant::class)->id;
 }
 public function connection(Request $r){
  $provider=$this->owner($r);if($r->isMethod('GET'))return response()->json(MarketingConnections::status($provider));
  if($r->isMethod('DELETE')){DB::table('marketing_connections')->where('provider_id',$provider)->delete();return response()->json(['ok'=>true]);}
  if($r->isMethod('POST')){$keys=MarketingConnections::get($provider);abort_unless(!empty($keys['apiKey']),422,'Előbb mentsd el a kapcsolatot.');try{return response()->json((new MarketingGateway($keys))->test());}catch(MarketingException $e){abort(422,$e->getMessage());}}
  $v=$r->validate(['engine'=>'required|in:brevo,mailchimp','apiKey'=>'nullable|string|max:1000','senderEmail'=>'required|email|max:200','senderName'=>'required|string|max:150','company'=>'required|string|max:150','address'=>'required|string|max:300','city'=>'required|string|max:100','zip'=>'required|string|max:20','country'=>'required|string|size:2','listId'=>'nullable|alpha_dash|max:100','folderId'=>'nullable|integer|min:1']);$v['senderEmail']=Contact::email($v['senderEmail']);$v['country']=strtoupper($v['country']);MarketingConnections::save($provider,$v);return response()->json(['ok'=>true]);
 }
 public function campaigns(Request $r,?string $id=null,?string $action=null){
  $provider=$this->owner($r);
  if($r->isMethod('GET'))return response()->json(['campaigns'=>DB::table('marketing_campaigns')->where('provider_id',$provider)->select('id','engine','subject','html','status','remote_id','recipient_count','scheduled_at','submitted_at','error','created_at','updated_at')->latest('created_at')->limit(100)->get(),'recipients'=>MarketingCampaigns::recipients($provider),'connection'=>MarketingConnections::status($provider)]);
  $keys=MarketingConnections::get($provider);abort_unless(!empty($keys['apiKey']),422,'Előbb kapcsold össze a hírlevélküldő fiókodat.');
  if(!$id){$v=$r->validate(['subject'=>'required|string|max:200','html'=>'required|string|max:50000']);$id=(string)Str::uuid();DB::table('marketing_campaigns')->insert(['id'=>$id,'provider_id'=>$provider,'engine'=>$keys['engine'],'subject'=>$v['subject'],'html'=>SafeHtml::clean($v['html']),'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);return response()->json(['id'=>$id],201);}
  $campaign=DB::table('marketing_campaigns')->where('id',$id)->where('provider_id',$provider)->first();abort_unless($campaign,404);
  if($action==='test'){$v=$r->validate(['email'=>'required|email|max:200']);abort_unless(in_array($campaign->status,['ready','submitted'],true)&&$campaign->remote_id,422,'Előbb készítsd elő a kampányt.');$key='marketing-test:'.$provider;\App\Services\AttemptLimit::check($key,5);RateLimiter::hit($key,60);try{(new MarketingGateway($keys))->preview($campaign->remote_id,$v['email']);}catch(MarketingException $e){abort(422,$e->getMessage());}return response()->json(['ok'=>true]);}
  if($action==='report'){abort_unless($campaign->remote_id,422);try{$report=(new MarketingGateway($keys))->report($campaign->remote_id);}catch(MarketingException $e){abort(422,$e->getMessage());}return response()->json(['status'=>$report['status']??'','emailsSent'=>$report['emails_sent']??$report['statistics']['globalStats']['sent']??null]);}
  return DB::transaction(function()use($r,$provider,$campaign,$action,$keys){$row=DB::table('marketing_campaigns')->where('id',$campaign->id)->lockForUpdate()->first();
   if($action==='prepare'){$v=$r->validate(['recipientIds'=>'required|array|min:1|max:5000','recipientIds.*'=>'integer|min:1']);abort_unless($row->status==='draft',422,'Csak piszkozat készíthető elő.');$recipients=array_values(array_filter(MarketingCampaigns::recipients($provider),fn($c)=>in_array($c['id'],$v['recipientIds'],true)));abort_unless($recipients,422,'Nincs kiválasztott, feliratkozott címzett.');DB::table('marketing_campaigns')->where('id',$row->id)->update(['status'=>'preparing','recipient_count'=>count($recipients),'recipients'=>json_encode(['revision'=>$keys['revision'],'items'=>$recipients,'cursor'=>0]),'scheduled_at'=>now(),'updated_at'=>now()]);}
   elseif($action==='send'){$v=$r->validate(['confirm'=>'accepted','scheduledAt'=>'nullable|date|after_or_equal:now']);abort_unless($row->status==='ready',422,'Csak előkészített kampány küldhető.');DB::table('marketing_campaigns')->where('id',$row->id)->update(['status'=>'scheduled','scheduled_at'=>empty($v['scheduledAt'])?now():\Carbon\CarbonImmutable::parse($v['scheduledAt']),'updated_at'=>now()]);}
   elseif($action==='cancel'){abort_unless(in_array($row->status,['draft','preparing','ready','scheduled','blocked'],true),422,'A már átadott vagy bizonytalan kampányt a hírlevélküldőnél ellenőrizd.');DB::table('marketing_campaigns')->where('id',$row->id)->update(['status'=>'cancelled','updated_at'=>now()]);}
   else{$v=$r->validate(['subject'=>'required|string|max:200','html'=>'required|string|max:50000']);abort_unless($row->status==='draft',422);DB::table('marketing_campaigns')->where('id',$row->id)->update(['subject'=>$v['subject'],'html'=>SafeHtml::clean($v['html']),'updated_at'=>now()]);}
   return response()->json(['ok'=>true]);
  });
 }
 public function consents(Request $r){
  $provider=$this->owner($r);abort_unless($provider>0,422);
  if($r->isMethod('GET'))return response()->json(DB::table('marketing_consents')->where('provider_id',$provider)->get());
  $v=$r->validate(['clientId'=>'required|string|max:80','subscribed'=>'required|boolean','evidence'=>'required_if:subscribed,true|string|max:500']);$client=collect(app(SalonStore::class)->read()['clients'])->first(fn($c)=>$c['id']===$v['clientId']&&\App\Services\Clients::visible($c));abort_unless($client&&!empty($client['email']),422,'A vendég e-mail-címe szükséges.');$email=Contact::email($client['email']);
  DB::table('marketing_consents')->updateOrInsert(['provider_id'=>$provider,'email'=>$email],['client_id'=>$client['id'],'guest_id'=>$client['guestId']??null,'name'=>$client['name'],'source'=>$v['evidence']??'Szolgáltatói leiratkoztatás','consented_at'=>$v['subscribed']?now():null,'unsubscribed_at'=>$v['subscribed']?null:now(),'created_at'=>now(),'updated_at'=>now()]);return response()->json(['ok'=>true]);
 }
 public function guestConsent(Request $r){
  $guest=(int)$r->session()->get('guest_id',0);abort_unless($guest,401);$provider=app(Tenant::class)->id;$account=DB::table('guest_accounts')->find($guest);$email=$account->email??null;abort_unless($account&&$account->email_verified&&$email,422,'Ellenőrzött e-mail-cím szükséges.');
  if($r->isMethod('GET'))return response()->json(['subscribed'=>DB::table('marketing_consents')->where('provider_id',$provider)->where('email',mb_strtolower($email))->whereNotNull('consented_at')->whereNull('unsubscribed_at')->exists()]);
  $v=$r->validate(['subscribed'=>'required|boolean']);DB::table('marketing_consents')->updateOrInsert(['provider_id'=>$provider,'email'=>mb_strtolower($email)],['guest_id'=>$guest,'name'=>$account->profile_name?:$account->name,'source'=>'Vendég saját fiókja','consented_at'=>$v['subscribed']?now():null,'unsubscribed_at'=>$v['subscribed']?null:now(),'created_at'=>now(),'updated_at'=>now()]);return response()->json(['ok'=>true]);
 }
 public function webhook(Request $r,int $provider,string $engine,string $token){
  $keys=MarketingConnections::get($provider);abort_unless(($keys['engine']??'')===$engine&&!empty($keys['webhookToken'])&&hash_equals($keys['webhookToken'],$token),404);if($r->isMethod('GET'))return response('OK');
  $event=$r->input('event',$r->input('type'));$email=$r->input('email',$r->input('data.email'));if(in_array($event,['unsubscribed','unsubscribe','spam','hardBounce','cleaned'],true)&&is_string($email)&&filter_var($email,FILTER_VALIDATE_EMAIL))MarketingCampaigns::unsubscribe($provider,$email);return response()->json(['received'=>true]);
 }
}
