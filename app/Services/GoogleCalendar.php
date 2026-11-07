<?php
namespace App\Services;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\{DB,Crypt,Http,Schema};
final class GoogleCalendar
{
 public function enqueue(array $state,array $before=[]):void {
  $provider=app(Tenant::class)->id;$changed=[];$staff=[];$guests=[];
  foreach($state['bookings'] as $b){$old=$before[$b['id']]??[];$version=self::version($b);if(($old['version']??null)===$version)continue;$changed[]=[$b,$old,$version];$staff[]=$b['staff'];if(!empty($old['staff']))$staff[]=$old['staff'];foreach([$b['guestId']??null,$old['guestId']??null] as $g)if($g)$guests[]=(string)$g;}
  if(!$changed||!Schema::hasTable('calendar_tasks'))return;
  $connections=DB::table('calendar_connections')->where('enabled',true)->where(function($q)use($provider,$staff,$guests){$q->where(fn($q)=>$q->where('owner_type','staff')->where('provider_id',$provider)->whereIn('owner_id',array_unique($staff)));if($guests)$q->orWhere(fn($q)=>$q->where('owner_type','guest')->where('provider_id',0)->whereIn('owner_id',array_unique($guests)));})->get();$by=[];foreach($connections as $c)$by[$c->owner_type.':'.$c->owner_id][]=$c;
  foreach($changed as [$b,$old,$version]){$targets=[];foreach(array_unique(['staff:'.$b['staff'],'staff:'.($old['staff']??''),'guest:'.($b['guestId']??''),'guest:'.($old['guestId']??'')]) as $key)foreach($by[$key]??[] as $c)$targets[$c->id]=$c;
   foreach($targets as $c)DB::table('calendar_tasks')->upsert([['connection_id'=>$c->id,'provider_id'=>$provider,'booking_id'=>$b['id'],'version'=>$version.':'.bin2hex(random_bytes(8)),'status'=>'pending','attempts'=>0,'available_at'=>now(),'locked_at'=>null,'last_error'=>null,'created_at'=>now(),'updated_at'=>now()]],['connection_id','provider_id','booking_id'],['version','status','attempts','available_at','locked_at','last_error','updated_at']);
  }
 }
 private static function version(array $b):string{return hash('sha256',json_encode([$b['updatedAt']??$b['start'],$b['status'],$b['attendance']??'',$b['start'],$b['staff'],$b['guestId']??null]));}
 public static function versions(array $state):array {$out=[];foreach($state['bookings'] as $b)$out[$b['id']]=['version'=>self::version($b),'staff'=>$b['staff'],'guestId'=>$b['guestId']??null];return $out;}
 public function backfill(object $c):void {
  $rows=$c->owner_type==='staff'?DB::table('salon_state')->where('id',$c->provider_id)->get():DB::table('salon_state')->get();
  foreach($rows as $row)app(Tenant::class)->run((int)$row->id,function()use($c,$row){$s=json_decode($row->data,true);$s['bookings']=array_values(array_filter($s['bookings'],fn($b)=>Date::parse($b['start'])->gt(now()->subMonth())&&($c->owner_type==='staff'?$b['staff']===$c->owner_id:(int)($b['guestId']??0)===(int)$c->owner_id)));$this->enqueue($s);});
 }
 private function token(object $c):string {
  $data=json_decode(Crypt::decryptString($c->credentials),true,512,JSON_THROW_ON_ERROR);if(($data['expires']??0)>time()+60)return $data['access_token'];
  $shared=SharedConnections::all();$r=Http::connectTimeout(3)->timeout(12)->asForm()->post('https://oauth2.googleapis.com/token',['client_id'=>$shared['google_client_id'],'client_secret'=>$shared['google_client_secret'],'refresh_token'=>$data['refresh_token'],'grant_type'=>'refresh_token'])->throw()->json();
  if(empty($r['access_token']))throw new \RuntimeException('Missing access token.');$data['access_token']=$r['access_token'];$data['expires']=time()+($r['expires_in']??3600);DB::table('calendar_connections')->where('id',$c->id)->update(['credentials'=>Crypt::encryptString(json_encode($data)),'updated_at'=>now()]);return $data['access_token'];
 }
 private function request(object $c):\Illuminate\Http\Client\PendingRequest {return Http::connectTimeout(3)->timeout(12)->withToken($this->token($c));}
 public function pullBusy(object $c):void {
  $start=now()->subDays(7)->utc()->toISOString();$end=now()->addDays(180)->utc()->toISOString();$busy=[];$page=null;$pages=0;
  do{$query=['timeMin'=>$start,'timeMax'=>$end,'singleEvents'=>'true','maxResults'=>1000,'showDeleted'=>'false'];if($page)$query['pageToken']=$page;
   $response=$this->request($c)->get('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($c->calendar_id).'/events',$query)->throw()->json();
   foreach($response['items']??[] as $event){if(($event['status']??'')==='cancelled'||($event['transparency']??'')==='transparent')continue;$from=$event['start']['dateTime']??(!empty($event['start']['date'])?$event['start']['date'].'T00:00:00+01:00':null);$to=$event['end']['dateTime']??(!empty($event['end']['date'])?$event['end']['date'].'T00:00:00+01:00':null);if(!$from||!$to)continue;
    if(!empty($event['start']['date']))$from=Date::parse($event['start']['date'],'Europe/Budapest')->toISOString();if(!empty($event['end']['date']))$to=Date::parse($event['end']['date'],'Europe/Budapest')->toISOString();
    $meta=$event['extendedProperties']['private']??[];
    if($c->owner_type==='staff'&&(string)($meta['raero_provider']??'')===(string)$c->provider_id)continue;
    $busy[]=['start'=>Date::parse($from)->getTimestamp(),'end'=>Date::parse($to)->getTimestamp(),'bookingId'=>$meta['raero_booking']??null,'providerId'=>$meta['raero_provider']??null];
   }
   $page=$response['nextPageToken']??null;$pages++;if($page&&$pages>=5)throw new \RuntimeException('Too many calendar pages.');
  }while($page);
  DB::table('calendar_connections')->where('id',$c->id)->update(['busy'=>json_encode($busy),'busy_until'=>now()->addDays(180),'last_synced_at'=>now(),'last_attempt_at'=>now(),'last_error'=>null,'updated_at'=>now()]);
 }
 public function busy(array $s,?int $guest=null):array {
  if(isset($s['_googleBusy']))return $s['_googleBusy'];if(!Schema::hasTable('calendar_connections'))return [];
  $q=DB::table('calendar_connections')->where('enabled',true)->where(fn($q)=>$q->where(fn($q)=>$q->where('owner_type','staff')->where('provider_id',app(Tenant::class)->id))->when($guest,fn($q)=>$q->orWhere(fn($q)=>$q->where('owner_type','guest')->where('provider_id',0)->where('owner_id',(string)$guest))));$out=[];
  foreach($q->get() as $c)$out[$c->owner_type.':'.$c->owner_id]=json_decode($c->busy??'[]',true);return $out;
 }
 public function validate(array $b,array $s,int $duration,?string $ignore):void {
  $all=$this->busy($s,!empty($b['public'])?(int)($b['guestId']??0):null);$ranges=$all['staff:'.$b['staff']]??[];if(!empty($b['public'])&&!empty($b['guestId']))$ranges=array_merge($ranges,$all['guest:'.$b['guestId']]??[]);
  $from=Date::parse($b['start'])->getTimestamp();$to=$from+$duration*60;
  foreach($ranges as $range){if($ignore&&($range['bookingId']??null)===$ignore&&(int)($range['providerId']??0)===app(Tenant::class)->id)continue;if($from<$range['end']&&$to>$range['start'])throw new \InvalidArgumentException('Ez az időszak a kapcsolt Google Naptárban foglalt. Válassz másik időpontot.');}
 }
 private function execute(object $job,object $c):void {
  $row=DB::table('salon_state')->find($job->provider_id);if(!$row)return;$s=json_decode($row->data,true);$b=collect($s['bookings'])->firstWhere('id',$job->booking_id);if(!$b)return;
  $removed=$c->owner_type==='guest'?(int)($b['guestId']??0)!==(int)$c->owner_id:($b['staff']!==$c->owner_id||(int)$c->provider_id!==(int)$job->provider_id);
  $id='raero'.substr(hash('sha256',$job->provider_id.':'.$job->booking_id),0,48);$base='https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($c->calendar_id).'/events';
  if($removed||in_array($b['status'],['cancelled','rejected'],true)){$r=$this->request($c)->delete($base.'/'.$id);if(!in_array($r->status(),[200,204,404,410],true))$r->throw();return;}
  $start=Date::parse($b['start']);$payload=['summary'=>($b['status']==='no_show'?'Nem jelent meg · ':'').$s['settings']['name'].' · '.$b['name'].' · '.implode(' + ',array_column($b['serviceSnapshot'],'name')),'description'=>'Ráérő időpont. Az időpontot a Ráérő felületén módosítsd.','start'=>['dateTime'=>$start->utc()->toISOString(),'timeZone'=>'Europe/Budapest'],'end'=>['dateTime'=>$start->addMinutes(array_sum(array_column($b['serviceSnapshot'],'duration')))->utc()->toISOString(),'timeZone'=>'Europe/Budapest'],'extendedProperties'=>['private'=>['raero_provider'=>(string)$job->provider_id,'raero_booking'=>$b['id']]]];
  $r=$this->request($c)->get($base.'/'.$id);if($r->status()===404||$r->status()===410){$r=$this->request($c)->post($base,['id'=>$id]+$payload);if($r->status()===409)$r=$this->request($c)->patch($base.'/'.$id,$payload);}else{$r->throw();$r=$this->request($c)->patch($base.'/'.$id,$payload);}$r->throw();
 }
 public function run(int $limit=20):array {
  DB::table('calendar_tasks')->where('status','processing')->where('locked_at','<',now()->subMinutes(10))->update(['status'=>'pending','locked_at'=>null]);$counts=['synced'=>0,'failed'=>0,'busyUpdated'=>0];
  for($i=0;$i<$limit;$i++){$job=DB::transaction(function(){$j=DB::table('calendar_tasks')->join('calendar_connections','calendar_connections.id','=','calendar_tasks.connection_id')->where('calendar_connections.enabled',true)->where('calendar_tasks.status','pending')->where('calendar_tasks.available_at','<=',now())->select('calendar_tasks.*')->orderBy('calendar_tasks.id')->lockForUpdate()->first();if($j)DB::table('calendar_tasks')->where('id',$j->id)->update(['status'=>'processing','locked_at'=>now()]);return $j;});if(!$job)break;$c=DB::table('calendar_connections')->find($job->connection_id);if(!$c||!$c->enabled)continue;
   try{$this->execute($job,$c);DB::table('calendar_tasks')->where('id',$job->id)->where('version',$job->version)->update(['status'=>'synced','last_error'=>null,'locked_at'=>null,'updated_at'=>now()]);$counts['synced']++;}
   catch(\Throwable){$error='A Google Naptár frissítése nem sikerült. Ellenőrizd az engedélyt és a kapcsolatot.';DB::table('calendar_tasks')->where('id',$job->id)->where('version',$job->version)->update(['status'=>$job->attempts>=4?'failed':'pending','attempts'=>$job->attempts+1,'available_at'=>now()->addMinutes(min(60,5*($job->attempts+1))),'last_error'=>$error,'locked_at'=>null,'updated_at'=>now()]);DB::table('calendar_connections')->where('id',$c->id)->update(['last_error'=>$error]);$counts['failed']++;}
  }
  foreach(DB::table('calendar_connections')->where('enabled',true)->where(fn($q)=>$q->whereNull('last_attempt_at')->orWhere('last_attempt_at','<',now()->subMinutes(5)))->orderBy('last_attempt_at')->limit(3)->get() as $c){try{$this->pullBusy($c);$counts['busyUpdated']++;}catch(\Throwable){DB::table('calendar_connections')->where('id',$c->id)->update(['last_error'=>'A Google Naptár foglaltsága nem frissült. Csatlakoztasd újra, vagy próbáld később.','last_attempt_at'=>now()]);$counts['failed']++;}}
  return $counts;
 }
}
