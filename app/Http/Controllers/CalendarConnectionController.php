<?php
namespace App\Http\Controllers;
use App\Services\{GoogleCalendar,SharedConnections,Tenant,SalonStore};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Cache,Crypt,Http};
final class CalendarConnectionController
{
 private function owner(Request $r):array {
  if($r->query('owner','guest')==='staff'){
   abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);$id=$r->query('staff');abort_unless(is_string($id)&&collect(app(SalonStore::class)->read()['staff'])->contains(fn($p)=>$p['id']===$id&&empty($p['archived'])),404);return ['owner_type'=>'staff','owner_id'=>$id,'provider_id'=>app(Tenant::class)->id];
  }
  $guest=(int)$r->session()->get('guest_id');abort_unless($guest&&DB::table('guest_accounts')->where('id',$guest)->exists(),401);return ['owner_type'=>'guest','owner_id'=>(string)$guest,'provider_id'=>0];
 }
 public function handle(Request $r){
  $owner=$this->owner($r);$q=DB::table('calendar_connections')->where($owner);$c=$q->first();$shared=SharedConnections::all();
  if($r->isMethod('GET'))return response()->json(['configured'=>!empty($shared['google_client_id'])&&!empty($shared['google_client_secret']),'connected'=>(bool)$c,'enabled'=>$c?(bool)$c->enabled:false,'email'=>$c->account_email??null,'calendarId'=>$c->calendar_id??null,'lastSyncedAt'=>$c->last_synced_at??null,'error'=>$c?($c->last_error??DB::table('calendar_tasks')->where('connection_id',$c->id)->where('status','failed')->value('last_error')):null,'pending'=>$c?DB::table('calendar_tasks')->where('connection_id',$c->id)->whereIn('status',['pending','processing'])->count():0]);
  abort_unless($c,404);
  if($r->isMethod('DELETE')){DB::transaction(function()use($c,$q){DB::table('calendar_tasks')->where('connection_id',$c->id)->delete();$q->delete();});return response()->json(['ok'=>true]);}
  $v=$r->validate(['enabled'=>'required|boolean','calendarId'=>'sometimes|required|string|max:255']);$q->update(['enabled'=>$v['enabled'],'calendar_id'=>$v['calendarId']??$c->calendar_id,'busy'=>null,'last_synced_at'=>null,'last_attempt_at'=>null,'last_error'=>null,'updated_at'=>now()]);if($v['enabled'])app(GoogleCalendar::class)->backfill(DB::table('calendar_connections')->find($c->id));return response()->json(['ok'=>true]);
 }
 public function start(Request $r){
  $owner=$this->owner($r);$shared=SharedConnections::all();abort_unless(!empty($shared['google_client_id'])&&!empty($shared['google_client_secret']),503,'A főadmin még nem állította be a Google-kapcsolatot.');
  $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));$r->session()->put('google_calendar_oauth',['state'=>$state,'verifier'=>$verifier,'owner'=>$owner,'expires'=>time()+600]);
  $uri=rtrim(config('app.url'),'/').'/auth/google-calendar/callback';return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$shared['google_client_id'],'redirect_uri'=>$uri,'response_type'=>'code','scope'=>'openid email profile https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly','access_type'=>'offline','prompt'=>'consent','state'=>$state,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256']));
 }
 public function callback(Request $r){
  $saved=$r->session()->pull('google_calendar_oauth');if(!$saved||$saved['expires']<time()||!is_string($r->query('state'))||!hash_equals($saved['state'],$r->query('state'))||!$r->filled('code'))return redirect('/fiokom?calendarError=state');$owner=$saved['owner'];
  if($owner['owner_type']==='staff')abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===$owner['provider_id'],403);else abort_unless((int)$r->session()->get('guest_id')===(int)$owner['owner_id'],403);
  $target=$owner['owner_type']==='staff'?'/?provider='.$owner['provider_id'].'&calendarSettings=1':'/fiokom';
  try{$shared=SharedConnections::all();$token=Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token',['code'=>$r->query('code'),'client_id'=>$shared['google_client_id'],'client_secret'=>$shared['google_client_secret'],'redirect_uri'=>rtrim(config('app.url'),'/').'/auth/google-calendar/callback','grant_type'=>'authorization_code','code_verifier'=>$saved['verifier']])->throw()->json();$user=Http::timeout(10)->withToken($token['access_token'])->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
   $old=DB::table('calendar_connections')->where($owner)->first();$oldTokens=$old?json_decode(Crypt::decryptString($old->credentials),true):[];$refresh=$token['refresh_token']??$oldTokens['refresh_token']??null;if(!$refresh)throw new \RuntimeException('Missing refresh grant.');
   DB::table('calendar_connections')->updateOrInsert($owner,['credentials'=>Crypt::encryptString(json_encode(['access_token'=>$token['access_token'],'refresh_token'=>$refresh,'expires'=>time()+($token['expires_in']??3600)])),'calendar_id'=>'primary','account_email'=>$user['email']??null,'enabled'=>true,'busy'=>null,'last_synced_at'=>null,'last_attempt_at'=>null,'last_error'=>null,'created_at'=>now(),'updated_at'=>now()]);$c=DB::table('calendar_connections')->where($owner)->first();app(GoogleCalendar::class)->backfill($c);if($owner['owner_type']==='staff')app(\App\Services\BusinessAvatar::class)->import($owner['provider_id'],'staff',$owner['owner_id'],'google',$user['picture']??null);try{app(GoogleCalendar::class)->pullBusy($c);}catch(\Throwable){DB::table('calendar_connections')->where('id',$c->id)->update(['last_error'=>'A kapcsolódás megtörtént, az első foglaltságfrissítés még nem sikerült. A háttérfeladat újrapróbálja.']);}
   return redirect($target.(str_contains($target,'?')?'&':'?').'calendarConnected=1');
  }catch(\Throwable){return redirect($target.(str_contains($target,'?')?'&':'?').'calendarError=connection');}
 }
}
