<?php
namespace App\Http\Controllers;
use App\Services\{Waitlist,Tenant,StaffAccess,SalonStore};
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class WaitlistController {
 public function availability(Request $r){
  $v=$r->validate(['date'=>'required|date_format:Y-m-d','services'=>'required|string|max:500','staff'=>'required|string|max:80']);
  $s=app(SalonStore::class)->read();\App\Services\Subscriptions::guard($s,false);if(empty($s['settings']['waitlistEnabled']))return response()->json(['eligible'=>false]);
  $ids=array_values(array_filter(explode(',',$v['services'])));if(!$ids||count($ids)>5)return response()->json(['eligible'=>false]);
  $catalog=collect($s['services'])->filter(fn($service)=>empty($service['archived']));if(array_diff($ids,$catalog->pluck('id')->all()))return response()->json(['eligible'=>false]);
  // Compare actual bookable starts with the same day without salon bookings.
  // Opening hours, breaks, time off, horizon and external calendar busy times remain in force.
  $probe=$r->duplicate(array_replace($r->query->all(),['public'=>1]));$controller=app(SalonController::class);
  $actual=$controller->slots($probe,$s);$s['bookings']=[];$possible=$controller->slots($probe,$s);
  return response()->json(['eligible'=>count(array_diff(array_column($possible,'start'),array_column($actual,'start')))>0])->header('Cache-Control','no-store');
 }
 public function join(Request $r){$v=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:200','services'=>'required|array|min:1|max:5','services.*'=>'required|string|distinct|max:80','staff'=>'nullable|string|max:80','firstDate'=>'required|date_format:Y-m-d','lastDate'=>'required|date_format:Y-m-d|after_or_equal:firstDate','timeFrom'=>'required|date_format:H:i','timeTo'=>'required|date_format:H:i|after:timeFrom','consent'=>'accepted']);$today=now('Europe/Budapest')->toDateString();abort_if($v['firstDate']<$today||Date::parse($v['lastDate'])->gt(Date::parse($today)->addDays(180))||Date::parse($v['firstDate'])->diffInDays(Date::parse($v['lastDate']))>30,422,'Legfeljebb 31 napos időszak adható meg, a következő 180 napon belül.');Waitlist::join($v);return response()->json(['ok'=>true,'message'=>'A megerősítő e-mail küldési sorba került. A várólista az e-mailes megerősítés után aktív.'],202);}
 public function token(Request $r,string $token){$row=DB::table('waitlist_entries')->where('token_hash',hash('sha256',$token))->first();abort_unless($row,404);$v=Waitlist::data($row);if($r->isMethod('POST')){$action=$r->validate(['action'=>'required|in:confirm,cancel'])['action'];if($action==='cancel')DB::table('waitlist_entries')->where('id',$row->id)->update(['status'=>'cancelled','updated_at'=>now()]);else{abort_if($row->last_date<now('Europe/Budapest')->toDateString(),410,'A megadott időszak lejárt.');DB::table('waitlist_entries')->where('id',$row->id)->where('status','pending')->update(['status'=>'active','updated_at'=>now()]);}return response()->json(['ok'=>true]);}return response()->json(['status'=>$row->status,'providerId'=>(int)$row->provider_id,'business'=>app(Tenant::class)->run((int)$row->provider_id,fn()=>app(SalonStore::class)->read()['settings']['name']),'firstDate'=>$row->first_date,'lastDate'=>$row->last_date,'services'=>$v['services'],'staff'=>$v['staff']??'any','offer'=>$row->status==='notified'||$row->status==='queued'?$v['offer']??null:null]);}
 public function manage(Request $r,?string $id=null){abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r)&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);$q=DB::table('waitlist_entries')->where('provider_id',app(Tenant::class)->id);if($r->isMethod('DELETE')){abort_unless((clone $q)->where('id',$id)->exists(),404);$q->where('id',$id)->update(['status'=>'cancelled','updated_at'=>now()]);return response()->json(['ok'=>true]);}return response()->json($q->orderByDesc('created_at')->limit(200)->get()->map(function($row){$v=Waitlist::data($row);return ['id'=>$row->id,'name'=>$v['name'],'email'=>$v['email'],'services'=>$v['services'],'staff'=>$v['staff']??'any','firstDate'=>$row->first_date,'lastDate'=>$row->last_date,'timeFrom'=>$v['timeFrom'],'timeTo'=>$v['timeTo'],'status'=>$row->status,'createdAt'=>$row->created_at];}));}
}
