<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class ProviderGuest{
 public static function ensure(Request $r,int $provider,?object $member=null):int{
  $subject=$member?'member-'.$member->id:'owner-'.$provider;
  $account=DB::table('guest_accounts')->where('provider','business')->where('subject',$subject)->first();
  if(!$account){$s=app(Tenant::class)->run($provider,fn()=>app(SalonStore::class)->read());$person=collect($s['staff'])->firstWhere('id',$member?$member->staff_id:$s['settings']['primaryStaffId']);$email=$member?$member->email:ProviderPasswords::email($provider);
   DB::table('guest_accounts')->insertOrIgnore(['provider'=>'business','subject'=>$subject,'name'=>mb_substr($person['name']??$s['settings']['name'],0,150),'email'=>$email?:null,'contact_email'=>$email?:null,'phone'=>'','email_verified'=>false,'created_at'=>now(),'updated_at'=>now()]);$account=DB::table('guest_accounts')->where('provider','business')->where('subject',$subject)->first();
  }$r->session()->put('guest_id',$account->id);$r->session()->forget(['guest_password_account','guest_password_version']);return (int)$account->id;
 }
 public static function allowed(Request $r):bool{
  $id=(int)$r->session()->get('guest_id');if(!$id)return false;if(!$r->session()->get('admin'))return true;$subject=$r->session()->get('provider_member_id')?'member-'.$r->session()->get('provider_member_id'):'owner-'.(int)$r->session()->get('provider_id',1);return DB::table('guest_accounts')->where('id',$id)->where('provider','business')->where('subject',$subject)->exists();
 }
}
