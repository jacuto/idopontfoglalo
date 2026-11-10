<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
final class MemberEmails{
 public static function sync(string $staff,string $email):void{
  $provider=app(Tenant::class)->id;$member=DB::table('provider_members')->where('provider_id',$provider)->where('staff_id',$staff)->where('active',true)->lockForUpdate()->first();if(!$member||$member->email===$email)return;
  if(!$email)throw new \InvalidArgumentException('Engedélyezett belépéshez e-mail-cím szükséges.');
  if(mb_strtolower(ProviderPasswords::email($provider))===$email||DB::table('provider_members')->where('provider_id',$provider)->where('email',$email)->where('id','!=',$member->id)->exists())throw new \InvalidArgumentException('Ehhez az e-mail-címhez már tartozik belépés.');
  DB::table('provider_members')->where('id',$member->id)->update(['email'=>$email,'version'=>$member->version+1,'updated_at'=>now()]);DB::table('provider_password_tokens')->where('member_id',$member->id)->whereNull('used_at')->update(['used_at'=>now()]);
 }
}
