<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\DB;
final class GroupMinimum
{
 public static function confirmed(array $g,array $s):int{return collect($s['bookings'])->filter(fn($b)=>($b['groupSessionId']??null)===$g['id']&&$b['status']==='confirmed')->count();}
 public static function deadline(array $g):Time{return Time::parse($g['start'])->subMinutes((int)($g['minimumLeadMinutes']??120));}
 public static function unavailable(array $g,array $s):bool{return !empty($g['cancelled'])||empty($g['minimumCheckedAt'])&&(int)($g['minimumParticipants']??0)>0&&self::deadline($g)->lte(Time::now())&&self::confirmed($g,$s)<(int)$g['minimumParticipants'];}
 public static function cancel(array &$s,array &$g,string $reason):void{
  $g['cancelled']=true;$g['status']='missed';$g['cancellationReason']=$reason;$g['minimumCheckedAt']=Time::now()->utc()->toISOString();
  foreach($s['bookings'] as &$b)if(($b['groupSessionId']??null)===$g['id']&&!in_array($b['status'],['cancelled','rejected'],true)){
   $next=$b;$next['status']='cancelled';$next['cancellationReason']=$reason==='minimum'?'group_minimum':'group_manual';$next['updatedAt']=Time::now()->utc()->toISOString();app(Offers::class)->transitionPass($s,$b,$next);$b=$next;app(Notifications::class)->enqueue($b,$s,'group_missed');
  }unset($b);app(Notifications::class)->groupNotice($g,$s);
 }
 public static function evaluate(array &$s):int{
  $count=0;foreach($s['groupSessions']??[] as $index=>$g){if(!empty($g['cancelled'])||!empty($g['minimumCheckedAt'])||(int)($g['minimumParticipants']??0)<1||self::deadline($g)->isFuture())continue;
   if(self::confirmed($g,$s)<(int)$g['minimumParticipants']){self::cancel($s,$s['groupSessions'][$index],'minimum');$count++;}else $s['groupSessions'][$index]['minimumCheckedAt']=Time::now()->utc()->toISOString();
  }return $count;
 }
 public static function run():array{
  $checked=0;$missed=0;foreach(DB::table('salon_state')->select('id','data')->orderBy('id')->cursor() as $row){$s=json_decode($row->data,true);$due=collect($s['groupSessions']??[])->contains(fn($g)=>empty($g['cancelled'])&&empty($g['minimumCheckedAt'])&&(int)($g['minimumParticipants']??0)>0&&self::deadline($g)->lte(Time::now()));if(!$due)continue;
   $changed=app(Tenant::class)->run((int)$row->id,fn()=>app(SalonStore::class)->mutate(function(&$fresh){return self::evaluate($fresh);},false));$checked++;$missed+=$changed;
  }return ['providersChecked'=>$checked,'missed'=>$missed];
 }
}
