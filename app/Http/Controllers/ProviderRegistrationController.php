<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,ProviderDiscovery};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\{Rule,ValidationException};
final class ProviderRegistrationController
{
 public function login(Request $r){
  $v=$r->validate(['email'=>'required|email|max:200','password'=>'required|string|max:200']);$email=mb_strtolower(trim($v['email']));$candidate=DB::table('providers')->where('active',true)->whereRaw('LOWER(email) = ?',[$email])->first();$id=$candidate?(int)$candidate->id:0;$hash=\App\Services\ProviderPasswords::hash($id?:1);
  if(!$candidate){$root=DB::table('salon_state')->where('id',1)->value('data');$settings=$root?json_decode($root,true)['settings']:[];if($email===mb_strtolower(trim($settings['providerEmail']??'')))$id=1;}
  $valid=$hash&&Hash::check($v['password'],$hash);abort_unless($valid&&$id,401,'Hibás e-mail-cím vagy jelszó.');\App\Services\ProviderIdentity::signIn($r,$id,'password');return response()->json(['id'=>$id,'url'=>'/?provider='.$id]);
 }
 public function store(Request $r){
  abort_if($r->session()->get('admin')||$r->session()->get('platform_admin'),409,'Már be vagy jelentkezve. Új vállalkozás regisztrálásához előbb lépj ki.');
  $pending=$r->session()->get('provider_social_pending');$social=$r->boolean('social')&&$pending&&$pending['expires']>=time();
  $v=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:200','password'=>($social?'nullable':'required').'|string|min:12|max:200|confirmed','city'=>'required|string|max:100','category'=>['required',Rule::in(array_keys(ProviderDiscovery::CATEGORIES))],'website_check'=>'nullable|string|max:0']);$v['email']=mb_strtolower(trim($v['email']));
  $id=DB::transaction(function()use($v,$social,$pending){
   $root=DB::table('salon_state')->where('id',1)->lockForUpdate()->first();abort_unless($root,503,'A rendszer kezdeti beállítása még nem készült el.');
   if(DB::table('providers')->whereRaw('LOWER(email) = ?',[$v['email']])->exists())throw ValidationException::withMessages(['email'=>'Ezzel az e-mail-címmel már van szolgáltatói fiók. Lépj be, vagy keresd az üzemeltetőt.']);
   $id=max(2,(int)DB::table('salon_state')->max('id')+1,(int)DB::table('providers')->max('id')+1);$start=now();$end=$start->copy()->addMonthsNoOverflow(2);
   DB::table('providers')->insert(['id'=>$id,'name'=>$v['name'],'email'=>$v['email'],'password_hash'=>Hash::make($social?bin2hex(random_bytes(32)):$v['password']),'trial_started_at'=>$start,'trial_ends_at'=>$end,'created_at'=>$start,'updated_at'=>$start]);
   $s=SalonStore::initial();$s['staff']=[['id'=>'owner','name'=>$v['name'],'role'=>'Szolgáltató']];$s['services']=[];$s['settings']=array_replace($s['settings'],['name'=>$v['name'],'profession'=>ProviderDiscovery::CATEGORIES[$v['category']],'providerEmail'=>$v['email'],'businessMode'=>'solo','primaryStaffId'=>'owner']);$s['profile']=['city'=>$v['city'],'categories'=>[$v['category']]];$s['subscription']=['status'=>'trialing','trialStartedAt'=>$start->toISOString(),'trialEndsAt'=>$end->toISOString(),'price'=>4000,'calendarLimit'=>1];
   DB::table('salon_state')->insert(['id'=>$id,'data'=>json_encode($s,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);if($social){abort_if(DB::table('provider_identities')->where('network',$pending['network'])->where('subject',$pending['subject'])->exists(),409,'Ez a közösségi fiók már regisztrálva van.');DB::table('provider_identities')->insert(['provider_id'=>$id,'network'=>$pending['network'],'subject'=>$pending['subject'],'created_at'=>now(),'updated_at'=>now()]);}return $id;
  },5);
  \App\Services\ProviderIdentity::signIn($r,$id,$social?'social':'password');return response()->json(['id'=>$id,'url'=>'/?provider='.$id],201);
 }
}
