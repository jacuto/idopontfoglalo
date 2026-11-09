<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class ProviderIdentity{
 public static function signIn(Request $r,int $id,string $method='social'):void{
  abort_unless($id===1||DB::table('providers')->where('id',$id)->where('active',true)->exists(),403,'A szolgáltatói fiók nem elérhető.');
  $r->session()->regenerate();$r->session()->forget(['guest_id','guest_password_account','guest_password_version','platform_admin','pending_guest_invitation','provider_social_pending','provider_member_id','provider_member_version']);$r->session()->put(['admin'=>true,'provider_id'=>$id,'provider_auth_method'=>$method,'provider_password_version'=>ProviderPasswords::version($id)]);ProviderGuest::ensure($r,$id);
 }
 public static function complete(Request $r,string $network,string $subject,?string $email,string $name,string $mode,int $tenant){
  $identity=DB::table('provider_identities')->where('network',$network)->where('subject',$subject)->first();
  if($mode==='provider-link'){
   abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===$tenant,403);abort_if($identity&&(int)$identity->provider_id!==$tenant,409,'Ez a közösségi fiók már másik vállalkozáshoz kapcsolódik.');
   DB::table('provider_identities')->insertOrIgnore(['provider_id'=>$tenant,'network'=>$network,'subject'=>$subject,'created_at'=>now(),'updated_at'=>now()]);$linked=DB::table('provider_identities')->where('network',$network)->where('subject',$subject)->first();abort_unless((int)$linked->provider_id===$tenant,409);return redirect('/?provider='.$tenant.'&providerLinked=1');
  }
  if($identity){self::signIn($r,(int)$identity->provider_id);return redirect('/?provider='.$identity->provider_id);}
  $r->session()->regenerate();$r->session()->forget(['admin','platform_admin','guest_id','guest_password_account','guest_password_version']);$r->session()->put('provider_social_pending',['network'=>$network,'subject'=>$subject,'email'=>$email,'name'=>mb_substr($name,0,150),'expires'=>time()+600]);return redirect('/szolgaltato?social=1#csatlakozas');
 }
}
