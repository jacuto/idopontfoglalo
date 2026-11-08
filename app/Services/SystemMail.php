<?php
namespace App\Services;
use Illuminate\Support\Facades\Mail;
final class SystemMail{
 public static function send(string $recipient,string $event,array $variables,string $locale='hu'):string{
  $c=app(Connections::class)->all();if(!app(Connections::class)->mailReady())return 'blocked';$t=app(MailTemplates::class)->render($event,$variables);if(in_array($locale,['en','de'],true)&&!\Illuminate\Support\Facades\DB::table('mail_templates')->where('event',$event)->exists())$t=self::localized($event,$variables,$locale,$t);if(!$t['enabled'])return 'skipped';
  config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>$c['smtp_host'],'mail.mailers.smtp.port'=>(int)($c['smtp_port']?:587),'mail.mailers.smtp.username'=>$c['smtp_username']?:null,'mail.mailers.smtp.password'=>$c['smtp_password']?:null,'mail.mailers.smtp.scheme'=>(int)$c['smtp_port']===465?'smtps':'smtp','mail.from.address'=>$c['mail_from'],'mail.from.name'=>$c['mail_name']?:'Ráérő']);Mail::purge('smtp');Mail::to($recipient)->send(new \App\Mail\NotificationMail($t['subject'],$t['html']));return 'sent';
 }
 private static function localized(string $event,array $v,string $locale,array $fallback):array{
  $copy=[
   'en'=>['guest_verify'=>['Confirm your email address','Confirm your email address, then choose your name and password. This link is valid for 24 hours.','Complete your account'],'guest_reset'=>['Reset your password','You requested a new password. This link is valid for one hour. If you did not request this, no action is needed.','Set a new password'],'review_request'=>['How was your visit?','Thank you for visiting! Share your experience and help other guests choose their provider.','Write a review']],
   'de'=>['guest_verify'=>['E-Mail-Adresse bestätigen','Bestätige deine E-Mail-Adresse und lege dann deinen Namen und dein Passwort fest. Dieser Link ist 24 Stunden gültig.','Konto fertigstellen'],'guest_reset'=>['Passwort zurücksetzen','Du hast ein neues Passwort angefordert. Dieser Link ist eine Stunde gültig. Falls du dies nicht angefordert hast, musst du nichts tun.','Neues Passwort festlegen'],'review_request'=>['Wie war dein Besuch?','Vielen Dank für deinen Besuch! Teile deine Erfahrung und hilf anderen Kunden bei der Auswahl.','Bewertung schreiben']]
  ][$locale][$event]??null;if(!$copy)return $fallback;return MailTemplates::renderTemplate(['enabled'=>true,'subject'=>$copy[0].' — {{business}}','html'=>'<h2>'.$copy[0].'</h2><p>{{name}}</p><p>{{details}}</p><p><a href="{{manage_url}}">'.$copy[2].'</a></p>'],array_replace($v,['details'=>$copy[1]]));
 }
}
