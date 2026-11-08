<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt,Http,Mail};
use Illuminate\Support\Str;
final class ClientInvitations
{
 public static function registered(array $contact):bool{
  $email=mb_strtolower(trim($contact['email']??''));if($email!==''&&DB::table('guest_accounts')->where(fn($q)=>$q->whereRaw('LOWER(email) = ?',[$email])->orWhereRaw('LOWER(contact_email) = ?',[$email]))->exists())return true;
  $phone=Clients::phone($contact['phone']??'');if($phone!=='')foreach(DB::table('guest_accounts')->whereNotNull('phone')->select('phone')->cursor() as $row)if(Clients::phone($row->phone)===$phone)return true;
  return false;
 }
 public static function queue(array $s,array $client):void{
  $policy=$s['settings']['guestInvitationPolicy']??'none';if($policy==='none'||!empty($client['guestId'])||!Clients::visible($client)||self::registered($client))return;
  $email=trim($client['email']??'');$phone=trim($client['phone']??'');$channel=match($policy){'email'=>$email!==''?'email':null,'sms'=>$phone!==''?'sms':null,'email_first'=>$email!==''?'email':($phone!==''?'sms':null),'sms_first'=>$phone!==''?'sms':($email!==''?'email':null),default=>null};if(!$channel)return;
  $recipient=$channel==='email'?Contact::email($email):Contact::e164($phone);$id=(string)Str::uuid();$token=bin2hex(random_bytes(32));$provider=app(Tenant::class)->id;$hash=hash_hmac('sha256',$channel.':'.$recipient,(string)config('app.key'));
  $payload=Crypt::encryptString(json_encode(['recipient'=>$recipient,'channel'=>$channel,'token'=>$token],JSON_THROW_ON_ERROR));
  $inserted=DB::table('guest_invitations')->insertOrIgnore(['id'=>$id,'provider_id'=>$provider,'client_id'=>$client['id'],'contact_hash'=>$hash,'token_hash'=>hash('sha256',$token),'payload'=>$payload,'expires_at'=>now()->addDays(30),'created_at'=>now(),'updated_at'=>now()]);if(!$inserted)return;
  DB::table('deliveries')->insertOrIgnore(['provider_id'=>$provider,'dedup'=>hash('sha256','guest-invitation:'.$id),'payload'=>Crypt::encryptString(json_encode(['kind'=>'guest_invite','invitationId'=>$id])),'available_at'=>now(),'status'=>'pending','attempts'=>0,'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function deliver(string $id,array $s):string{
  $row=DB::table('guest_invitations')->where('provider_id',app(Tenant::class)->id)->find($id);if(!$row||$row->claimed_at||now()->gte($row->expires_at)||($s['settings']['guestInvitationPolicy']??'none')==='none')return 'skipped';
  $client=collect($s['clients'])->firstWhere('id',$row->client_id);if(!$client||!Clients::visible($client)||!empty($client['guestId'])||self::registered($client))return 'skipped';
  $data=json_decode(Crypt::decryptString($row->payload),true,32,JSON_THROW_ON_ERROR);if(!self::matches($client,$data))return 'skipped';$url=rtrim(config('app.url'),'/').'/meghivo/'.$data['token'];$text=$s['settings']['name'].' meghív a Ráérő vendégfiók létrehozására. Saját fiókoddal később időpontot foglalhatsz és kezelheted a hozzád kapcsolt foglalásokat. Belépés / regisztráció: '.$url;
  $c=app(Connections::class)->all();
  if($data['channel']==='email'){
   if(!app(Connections::class)->mailReady())return 'blocked';$template=app(MailTemplates::class)->render('guest_invite',['business'=>$s['settings']['name'],'name'=>$client['name'],'details'=>$text,'manage_url'=>$url,'account_url'=>$url,'provider_url'=>rtrim(config('app.url'),'/').'/foglalas?provider='.app(Tenant::class)->id]);if(!$template['enabled'])return 'skipped';
   config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>$c['smtp_host'],'mail.mailers.smtp.port'=>(int)($c['smtp_port']?:587),'mail.mailers.smtp.username'=>$c['smtp_username']?:null,'mail.mailers.smtp.password'=>$c['smtp_password']?:null,'mail.mailers.smtp.scheme'=>(int)$c['smtp_port']===465?'smtps':'smtp','mail.from.address'=>$c['mail_from'],'mail.from.name'=>$c['mail_name']?:'Ráérő']);Mail::purge('smtp');
   Mail::to($data['recipient'])->send(new \App\Mail\NotificationMail($template['subject'],$template['html'],filter_var($s['settings']['providerEmail']??'',FILTER_VALIDATE_EMAIL)?$s['settings']['providerEmail']:null,$s['settings']['name']));
  }else{
   if(empty($c['twilio_sid'])||empty($c['twilio_token'])||empty($c['twilio_from']))return 'blocked';Http::timeout(20)->withBasicAuth($c['twilio_sid'],$c['twilio_token'])->asForm()->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($c['twilio_sid']).'/Messages.json',[str_starts_with($c['twilio_from'],'MG')?'MessagingServiceSid':'From'=>$c['twilio_from'],'To'=>$data['recipient'],'Body'=>$text])->throw();
  }return 'sent';
 }
 private static function matches(array $client,array $data):bool{return $data['recipient']===($data['channel']==='email'?Contact::email($client['email']??''):Contact::e164($client['phone']??''));}
 public static function claim(string $token,int $guest):array{
  return DB::transaction(function()use($token,$guest){$row=DB::table('guest_invitations')->where('token_hash',hash('sha256',$token))->lockForUpdate()->first();abort_unless($row,404);abort_if(now()->gte($row->expires_at),410,'A meghívó lejárt.');abort_if($row->guest_id&&(int)$row->guest_id!==$guest,403,'A meghívót másik vendégfiók már felhasználta.');
   if(!$row->claimed_at){app(Tenant::class)->run((int)$row->provider_id,fn()=>app(SalonStore::class)->mutate(function(&$s)use($row,$guest){$found=false;foreach($s['clients'] as &$c)if($c['id']===$row->client_id&&Clients::visible($c)){abort_if(!empty($c['guestId'])&&(int)$c['guestId']!==$guest,403);$data=json_decode(Crypt::decryptString($row->payload),true,32,JSON_THROW_ON_ERROR);abort_unless(self::matches($c,$data),410,'A vendég elérhetősége megváltozott, ez a meghívó már nem használható.');$c['guestId']=$guest;$found=true;break;}unset($c);abort_unless($found,404);foreach($s['bookings'] as &$b)if(($b['clientId']??null)===$row->client_id){abort_if(!empty($b['guestId'])&&(int)$b['guestId']!==$guest,403);$b['guestId']=$guest;$b['updatedAt']=now()->toISOString();}unset($b);}));DB::table('guest_invitations')->where('id',$row->id)->update(['guest_id'=>$guest,'claimed_at'=>now(),'updated_at'=>now()]);}
   return ['claimed'=>true,'providerId'=>(int)$row->provider_id];
  },5);
 }
}
