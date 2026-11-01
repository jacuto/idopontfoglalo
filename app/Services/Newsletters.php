<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB, Mail, URL};
class Newsletters {
 public function ready():bool {return (bool)(config('newsletter.host')&&config('newsletter.from'));}
 public function send(string $to,string $subject,string $content):void {
  if(!$this->ready())throw new \InvalidArgumentException('A központi SMTP-kapcsolat még nincs beállítva.');
  config(['mail.mailers.platform'=>['transport'=>'smtp','host'=>config('newsletter.host'),'port'=>(int)config('newsletter.port'),'username'=>config('newsletter.username'),'password'=>config('newsletter.password'),'scheme'=>(int)config('newsletter.port')===465?'smtps':'smtp','timeout'=>20]]);
  Mail::purge('platform');Mail::mailer('platform')->raw($content,fn($m)=>$m->from(config('newsletter.from'),config('newsletter.name'))->to($to)->subject($subject));
 }
 public function run(int $limit=30):array {
  // An interrupted SMTP request may have been accepted: never retry it automatically.
  DB::table('newsletter_deliveries')->where('status','processing')->where('updated_at','<',now()->subMinutes(10))->update(['status'=>'unknown','error'=>'Megszakadt küldés. A kézbesítést a levelezőszolgáltatónál kell ellenőrizni.']);
  foreach(DB::table('newsletters')->where('status','scheduled')->where('scheduled_at','<=',now())->pluck('id') as $id)DB::transaction(function()use($id){
   $n=DB::table('newsletters')->where('id',$id)->lockForUpdate()->first();if(!$n||$n->status!=='scheduled')return;
   foreach(DB::table('newsletter_consents')->where('audience',$n->audience)->whereNotNull('consented_at')->whereNull('unsubscribed_at')->get() as $c)DB::table('newsletter_deliveries')->insertOrIgnore(['newsletter_id'=>$id,'consent_id'=>$c->id,'email'=>$c->email,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
   DB::table('newsletters')->where('id',$id)->update(['status'=>'sending','updated_at'=>now()]);
  });
  $count=['accepted'=>0,'blocked'=>0,'skipped'=>0,'unknown'=>0];
  for($i=0;$i<$limit;$i++) {
   if(!$this->ready()){$count['blocked']++;break;}
   $job=DB::transaction(function(){ $job=DB::table('newsletter_deliveries')->where('status','pending')->orderBy('id')->lockForUpdate()->first();if($job)DB::table('newsletter_deliveries')->where('id',$job->id)->update(['status'=>'processing','updated_at'=>now()]);return $job; });if(!$job)break;
   $consent=DB::table('newsletter_consents')->find($job->consent_id);$n=DB::table('newsletters')->find($job->newsletter_id);
   if(!$consent||!$consent->consented_at||$consent->unsubscribed_at||$consent->email!==$job->email){$status='skipped';$error=null;}
   else{try{$link=URL::signedRoute('newsletter.unsubscribe',['id'=>$consent->id]);$this->send($job->email,$n->subject,$n->content."\n\nLeiratkozás: ".$link);$status='accepted';$error=null;}catch(\Throwable){$status='unknown';$error='A küldés eredménye nem bizonyítható. Ellenőrizd a központi SMTP-szolgáltatónál.';}}
   DB::table('newsletter_deliveries')->where('id',$job->id)->update(['status'=>$status,'error'=>$error,'updated_at'=>now()]);$count[$status]++;
  }
  foreach(DB::table('newsletters')->where('status','sending')->pluck('id') as $id)if(!DB::table('newsletter_deliveries')->where('newsletter_id',$id)->whereIn('status',['pending','processing'])->exists())DB::table('newsletters')->where('id',$id)->update(['status'=>'completed','updated_at'=>now()]);
  return $count;
 }
}
