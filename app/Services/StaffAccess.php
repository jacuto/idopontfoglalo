<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class StaffAccess{
 public static function clear(Request $r):void{$r->session()->forget(['admin','provider_id','provider_member_id','provider_member_version','provider_auth_method','provider_password_version']);}
 public static function refresh(Request $r):void{
  if(!$r->session()->get('provider_member_id'))return;$m=DB::table('provider_members')->find($r->session()->get('provider_member_id'));
  if(!$m||!$m->active||(int)$m->provider_id!==(int)$r->session()->get('provider_id')||(int)$m->version!==(int)$r->session()->get('provider_member_version')){self::clear($r);return;}
  $row=DB::table('salon_state')->where('id',$m->provider_id)->value('data');$s=$row?json_decode($row,true):null;$staff=$s?collect($s['staff'])->first(fn($v)=>$v['id']===$m->staff_id&&empty($v['archived'])):null;
  if(!$staff||!PaidFeatures::enabled($s)||($m->role==='editor'&&($s['settings']['businessMode']??'team')!=='team')){self::clear($r);return;}
  $r->attributes->set('provider_member',$m);$r->session()->put('admin',true);
 }
 public static function editor(?Request $r=null):bool{$r??=request();return ($r->attributes->get('provider_member')->role??null)==='editor';}
 public static function guard(array $b,?Request $r=null):void{$r??=request();if(self::editor($r))abort_unless(($b['staff']??null)===$r->attributes->get('provider_member')->staff_id,403,'Csak a saját időpontjaidat kezelheted.');}
 public static function ownClient(array $s,?string $id,Request $r):void{if(!self::editor($r)||!$id)return;abort_unless(collect($s['bookings'])->contains(fn($b)=>($b['clientId']??'')===$id&&$b['staff']===$r->attributes->get('provider_member')->staff_id),403,'Ez a vendég nem tartozik a saját foglalásaidhoz.');}
 public static function projection(array $s,Request $r):array{
  $m=$r->attributes->get('provider_member');$access=['role'=>$m->role??'admin','memberId'=>$m->id??null,'staffId'=>$m->staff_id??null,'owner'=>!$m];if(!self::editor($r)){$s['_access']=$access;return $s;}
  $staff=$m->staff_id;$bookings=array_values(array_filter($s['bookings'],fn($b)=>$b['staff']===$staff));$clients=array_filter(array_column($bookings,'clientId'));$services=array_values(array_filter($s['services'],fn($v)=>in_array($staff,Settings::serviceStaff($s,$v),true)));$settings=array_intersect_key($s['settings'],array_flip(['name','profession','businessMode','primaryStaffId','openingTime','closingTime','open','close','slotStep','confirmation','advancedBookingEnabled','servicePhasesEnabled','defaultServiceId','skipUnnamedWarning','noShowFee','couponsEnabled','passesEnabled']));$settings['couponsEnabled']=false;$settings['passesEnabled']=false;
  $availability=$s['availability'];$availability['weeklyStaff']=array_values(array_filter($availability['weeklyStaff']??[],fn($v)=>$v['staffId']===$staff));$availability['overrides']=array_values(array_filter($availability['overrides']??[],fn($v)=>empty($v['staffId'])||$v['staffId']===$staff));$availability['blocks']=array_values(array_filter($availability['blocks'],fn($v)=>empty($v['staffId'])||$v['staffId']===$staff));$availability['dayLunches']=array_values(array_filter($availability['dayLunches']??[],fn($v)=>empty($v['staffId'])||$v['staffId']===$staff));
  return ['_access'=>$access,'settings'=>$settings,'staff'=>array_values(array_filter($s['staff'],fn($v)=>$v['id']===$staff)),'services'=>$services,'bookings'=>$bookings,'clients'=>array_values(array_map(fn($v)=>array_intersect_key($v,array_flip(['id','name','email','phone','guestId','nameManaged'])),array_filter($s['clients'],fn($v)=>in_array($v['id'],$clients,true)))),'availability'=>$availability,'profile'=>array_intersect_key($s['profile'],array_flip(['logo','city','address'])),'groupSessions'=>array_values(array_filter($s['groupSessions'],fn($g)=>$g['staff']===$staff)),'payments'=>[],'paymentDues'=>[],'reviews'=>[],'coupons'=>[],'passes'=>[],'clientPasses'=>[],'subscription'=>[],'recurringSeries'=>array_filter($s['recurringSeries'],fn($v)=>($v['template']['staff']??null)===$staff)];
 }
 public static function signIn(Request $r,object $m):void{
  abort_unless($m->active,403);$r->session()->regenerate();self::clear($r);$r->session()->forget(['platform_admin','pending_guest_invitation']);$r->session()->put(['admin'=>true,'provider_id'=>(int)$m->provider_id,'provider_member_id'=>$m->id,'provider_member_version'=>$m->version,'provider_auth_method'=>'member']);ProviderGuest::ensure($r,(int)$m->provider_id,$m);self::refresh($r);abort_unless($r->session()->get('admin'),403,'A munkatársi hozzáférés nem elérhető.');
 }
}
