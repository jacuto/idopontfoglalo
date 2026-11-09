<?php
namespace App\Http\Controllers;
use App\Services\{ProviderPasswords,ProviderIdentity,Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Crypt,Hash};
final class ProviderSecurityController{
 public function pending(Request $r){$p=$r->session()->get('provider_social_pending');abort_unless($p&&$p['expires']>=time(),404);return response()->json(array_intersect_key($p,array_flip(['name','email','network'])));}
 public function handle(Request $r,string $action){
  if($action==='forgot'){$v=$r->validate(['email'=>'required|email|max:200']);$email=mb_strtolower(trim($v['email']));$ids=DB::table('providers')->where('active',true)->whereRaw('LOWER(email) = ?',[$email])->pluck('id')->all();if(mb_strtolower(ProviderPasswords::email(1))===$email)$ids[]=1;foreach(array_unique($ids) as $id)DB::transaction(fn()=>ProviderPasswords::queue((int)$id));foreach(DB::table('provider_members')->where('email',$email)->where('active',true)->get() as $member)DB::transaction(fn()=>ProviderPasswords::queue((int)$member->provider_id,(int)$member->id));return response()->json(['ok'=>true]);}
  if($action==='reset'){$v=$r->validate(['token'=>'required|regex:/^[a-f0-9]{64}$/','password'=>'required|string|min:12|max:200|confirmed']);DB::transaction(function()use($v){$t=DB::table('provider_password_tokens')->where('token_hash',hash('sha256',$v['token']))->lockForUpdate()->first();abort_unless($t&&!$t->used_at&&now()->lt($t->expires_at)&&$t->email===ProviderPasswords::tokenEmail($t),422,'A hivatkozás érvénytelen vagy lejárt.');$t->member_id?ProviderPasswords::changeMember((int)$t->member_id,$v['password']):ProviderPasswords::change((int)$t->provider_id,$v['password']);});return response()->json(['ok'=>true]);}
  abort_unless($r->session()->get('admin')===true,403);$id=app(Tenant::class)->id;abort_unless((int)$r->session()->get('provider_id',1)===$id,403);$member=$r->attributes->get('provider_member');$v=$r->validate(['current_password'=>'required|string|max:200','password'=>'required|string|min:12|max:200|confirmed']);abort_unless(Hash::check($v['current_password'],$member?$member->password_hash:(ProviderPasswords::hash($id)??'')),422,'A jelenlegi jelszó hibás.');$version=DB::transaction(fn()=>$member?ProviderPasswords::changeMember((int)$member->id,$v['password']):ProviderPasswords::change($id,$v['password']));$r->session()->regenerate();$r->session()->put($member?'provider_member_version':'provider_password_version',$version);return response()->json(['ok'=>true]);
 }
}
