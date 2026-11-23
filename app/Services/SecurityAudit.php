<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
final class SecurityAudit
{
 public static function record(Request $r,string $event,string $actor,?int $id=null,?int $provider=null,?string $subjectType=null,?string $subjectId=null):void
 {
  if(!Schema::hasTable('security_audit_events'))return;
  DB::table('security_audit_events')->insert(['event'=>$event,'actor_type'=>$actor,'actor_id'=>$id,'provider_id'=>$provider,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'ip'=>$r->ip(),'user_agent'=>mb_substr((string)$r->userAgent(),0,300),'created_at'=>now()]);
 }
 public static function platform(Request $r,string $event,?int $provider=null,?string $subjectType=null,?string $subjectId=null):void{self::record($r,$event,'platform',null,$provider,$subjectType,$subjectId);}
}
