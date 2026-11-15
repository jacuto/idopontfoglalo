<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB, Mail, URL};
class Newsletters {
 public function ready():bool {return (bool)(config('newsletter.host')&&config('newsletter.from'));}
 public function send(string $to,string $subject,string $content,bool $html=false):void {
  if(!$this->ready())throw new \InvalidArgumentException('A központi SMTP-kapcsolat még nincs beállítva.');
  config(['mail.mailers.platform'=>['transport'=>'smtp','host'=>config('newsletter.host'),'port'=>(int)config('newsletter.port'),'username'=>config('newsletter.username'),'password'=>config('newsletter.password'),'scheme'=>(int)config('newsletter.port')===465?'smtps':'smtp','timeout'=>20]]);
  Mail::purge('platform');if($html){Mail::mailer('platform')->to($to)->send((new \App\Mail\NotificationMail($subject,$content))->from(config('newsletter.from'),config('newsletter.name')));}else Mail::mailer('platform')->raw($content,fn($m)=>$m->from(config('newsletter.from'),config('newsletter.name'))->to($to)->subject($subject));
 }
 public function run(int $limit=30):array {
  // Campaigns use the external marketing API; SMTP is reserved for service messages.
  DB::table('newsletters')->whereIn('status',['scheduled','sending'])->update(['status'=>'paused','updated_at'=>now()]);
  return MarketingCampaigns::run(min(5,max(1,$limit)));
 }
}
