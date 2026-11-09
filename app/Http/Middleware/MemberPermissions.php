<?php
namespace App\Http\Middleware;
use App\Services\{StaffAccess,SalonStore};
use Illuminate\Http\Request;
use Closure;
final class MemberPermissions{
 public function handle(Request $r,Closure $next){
  if(!StaffAccess::editor($r))return $next($r);$path=$r->path();$public=$r->boolean('public');
  if($r->is('auth/*')&&(in_array($r->query('mode'),['admin','photo','provider-link'],true)||($r->is('auth/google-calendar*')&&$r->query('owner','guest')!=='guest')))abort(403,'Ehhez adminjog szükséges.');
  if(!$r->is('api/*'))return $next($r);
  if(preg_match('#^api/guest/avatar/([0-9]+)$#',$path,$m)){$own=(int)$r->session()->get('guest_id')===(int)$m[1];$s=StaffAccess::projection(app(SalonStore::class)->read(),$r);abort_unless($own||(app(\App\Services\Tenant::class)->id===(int)$r->attributes->get('provider_member')->provider_id&&collect($s['clients'])->contains(fn($c)=>(int)($c['guestId']??0)===(int)$m[1])),404);}
  if($path==='api/clients/search'&&$r->isMethod('GET')){$s=StaffAccess::projection(app(SalonStore::class)->read(),$r);$q=mb_strtolower(trim((string)$r->query('q','')));$digits=\App\Services\Clients::phone($q);$rows=mb_strlen($q)<3?[]:array_values(array_filter($s['clients'],fn($c)=>str_contains(mb_strtolower($c['name']),$q)||(strlen($digits)>=3&&str_contains(\App\Services\Clients::phone($c['phone']??''),$digits))));return response()->json(array_slice($rows,0,20))->header('Cache-Control','no-store');}
  if(preg_match('#^api/clients/([^/]+)/detail$#',$path,$m)&&$r->isMethod('GET')){$s=StaffAccess::projection(app(SalonStore::class)->read(),$r);return response()->json(\App\Services\Clients::detail($s,$m[1]))->header('Cache-Control','no-store');}
  if($r->is('api/guest*')||$r->is('api/auth/*')||in_array($path,['api/public','api/providers','api/csrf','api/logout','api/provider-login','api/provider-workspace','api/provider-password/forgot','api/provider-password/reset','api/provider-password/change','api/manage','api/manage/review','api/manage/checkout','api/quote'],true))return $next($r);
  if($path==='api/calendar/connection'&&$r->query('owner','guest')==='guest')return $next($r);
  if($path==='api/state'&&$r->isMethod('GET'))return response()->json(StaffAccess::projection(app(SalonStore::class)->read(),$r))->header('Cache-Control','no-store');
  if($path==='api/group-sessions'&&$r->isMethod('GET'))return response()->json(StaffAccess::projection(app(SalonStore::class)->read(),$r)['groupSessions'])->header('Cache-Control','no-store');
  if(in_array($path,['api/slots','api/slots/next'],true)&&$r->isMethod('GET')){if(!$public){$staff=$r->attributes->get('provider_member')->staff_id;abort_unless(!$r->filled('staff')||$r->query('staff')===$staff,403);$r->query->set('staff',$staff);}return $next($r);}
  if($path==='api/bookings'&&$r->isMethod('POST'))return $next($r);
  if(preg_match('#^api/bookings/[^/]+$#',$path)&&$r->isMethod('PATCH'))return $next($r);
  if(preg_match('#^api/booking-series/[^/]+/cancel$#',$path)&&$r->isMethod('POST'))return $next($r);
  abort(403,'Ehhez adminjog szükséges. Szerkesztőként csak a saját foglalásaidat kezelheted.');
 }
}
