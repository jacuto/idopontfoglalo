<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt,Mail};
use Illuminate\Support\Str;
final class ClientInvitations
{
 public static function registered(array $contact):bool{
  $email=mb_strtolower(trim($contact['email']??''));if($email!==''&&DB::table('guest_accounts')->where(fn($q)=>$q->whereRaw('LOWER(email) = ?',[$email])->orWhereRaw('LOWER(contact_email) = ?',[$email]))->exists())return true;
  $phone=Clients::phone($contact['phone']??'');if($phone!=='')foreach(DB::table('guest_accounts')->whereNotNull('phone')->select('phone')->cursor() as $row)if(Clients::phone($row->phone)===$phone)return true;
  return false;
 }
 public static function queue(array $s,array $client):void{
  $policy=$s['settings']['guestInvitationPolicy']??'none';if(($policy==='none'&&empty($s['settings']['guestInvitationSmsEnabled']))||!empty($client['guestId'])||!Clients::visible($client)||self::registered($client))return;
  $email=trim($client['email']??'');$phone=trim($client['phone']??'');$channel=!empty($s['settings']['guestInvitationSmsEnabled'])&&$phone!==''?'sms':match($policy){'email'=>$email!==''?'email':null,'sms'=>$phone!==''?'sms':null,'email_first'=>$email!==''?'email':($phone!==''?'sms':null),'sms_first'=>$phone!==''?'sms':($email!==''?'email':null),default=>null};if(!$channel)return;
  $recipient=$channel==='email'?Contact::email($email):Contact::e164($phone);$id=(string)Str::uuid();$token=bin2hex(random_bytes(32));$provider=app(Tenant::class)->id;$hash=hash_hmac('sha256',$channel.':'.$recipient,(string)config('app.key'));
  $payload=Crypt::encryptString(json_encode(['recipient'=>$recipient,'channel'=>$channel,'token'=>$token],JSON_THROW_ON_ERROR));
  $inserted=DB::table('guest_invitations')->insertOrIgnore(['id'=>$id,'provider_id'=>$provider,'client_id'=>$client['id'],'contact_hash'=>$hash,'token_hash'=>hash('sha256',$token),'payload'=>$payload,'expires_at'=>now()->addDays(30),'created_at'=>now(),'updated_at'=>now()]);if(!$inserted)return;
  DB::table('deliveries')->insertOrIgnore(['provider_id'=>$provider,'dedup'=>hash('sha256','guest-invitation:'.$id),'payload'=>Crypt::encryptString(json_encode(['kind'=>'guest_invite','invitationId'=>$id,'channel'=>$channel])),'available_at'=>now(),'status'=>'pending','attempts'=>0,'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function smsContent(string $id,array $s):?array{
  $row=DB::table('guest_invitations')->where('provider_id',app(Tenant::class)->id)->find($id);if(!$row||$row->claimed_at||now()->gte($row->expires_at))return null;
  $client=collect($s['clients'])->firstWhere('id',$row->client_id);if(!$client||!Clients::visible($client)||!empty($client['guestId']))return null;
  $data=json_decode(Crypt::decryptString($row->payload),true,32,JSON_THROW_ON_ERROR);$policy=$s['settings']['guestInvitationPolicy']??'none';
  if(empty($data['manual'])&&self::registered($client))return null;
  if($data['channel']!=='sms'||!self::matches($client,$data)||(empty($data['manual'])&&empty($s['settings']['guestInvitationSmsEnabled'])&&!in_array($policy,['sms','email_first','sms_first'],true)))return null;
  return ['recipient'=>$data['recipient'],'text'=>mb_strimwidth($s['settings']['name'],0,40,'…').' – Ráérő vendégfiók: regisztrálj, hogy kezeld a foglalásaidat. '.rtrim(config('app.url'),'/').'/meghivo/'.$data['token']];
 }
 public static function manual(array $s,array $client,string $channel):array{
  abort_if(!empty($client['guestId']),422,'A vendégnek már van kapcsolt fiókja.');$recipient=$channel==='email'?Contact::email($client['email']??''):Contact::e164($client['phone']??'');abort_unless($recipient,422,'A választott csatornához nincs elérhetőség.');$provider=app(Tenant::class)->id;
  return DB::transaction(function()use($provider,$client,$recipient,$channel){$old=DB::table('guest_invitations')->where('provider_id',$provider)->where('client_id',$client['id'])->lockForUpdate()->first();abort_if($old&&now()->lt(\Carbon\CarbonImmutable::parse($old->updated_at)->addDay()),422,'Ehhez a vendéghez az elmúlt 24 órában már készült meghívó. Ellenőrizd a küldési előzményeket.');$id=$old?->id??(string)Str::uuid();$token=bin2hex(random_bytes(32));$hash=hash_hmac('sha256',$channel.':'.$recipient,(string)config('app.key'));abort_if(DB::table('guest_invitations')->where('provider_id',$provider)->where('contact_hash',$hash)->where('id','!=',$id)->exists(),422,'Ehhez az elérhetőséghez már tartozik meghívó. Ellenőrizd a vendég duplikált névjegyeit.');$data=['provider_id'=>$provider,'client_id'=>$client['id'],'contact_hash'=>$hash,'token_hash'=>hash('sha256',$token),'payload'=>Crypt::encryptString(json_encode(['recipient'=>$recipient,'channel'=>$channel,'token'=>$token,'manual'=>true])),'expires_at'=>now()->addDays(30),'claimed_at'=>null,'guest_id'=>null,'updated_at'=>now()];if($old)DB::table('guest_invitations')->where('id',$id)->update($data);else DB::table('guest_invitations')->insert($data+['id'=>$id,'created_at'=>now()]);DB::table('deliveries')->insert(['provider_id'=>$provider,'dedup'=>hash('sha256','manual-invite:'.$id.':'.$token),'payload'=>Crypt::encryptString(json_encode(['kind'=>'guest_invite','invitationId'=>$id,'channel'=>$channel,'tokenHash'=>hash('sha256',$token)])),'status'=>'pending','attempts'=>0,'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);return ['queued'=>true,'channel'=>$channel];});
 }
 public static function deliver(string $id,array $s):string{
  $row=DB::table('guest_invitations')->where('provider_id',app(Tenant::class)->id)->find($id);if(!$row||$row->claimed_at||now()->gte($row->expires_at))return 'skipped';$data=json_decode(Crypt::decryptString($row->payload),true,32,JSON_THROW_ON_ERROR);$manual=!empty($data['manual']);
  $client=collect($s['clients'])->firstWhere('id',$row->client_id);if(!$client||!Clients::visible($client)||!empty($client['guestId'])||(!$manual&&self::registered($client))||!self::matches($client,$data))return 'skipped';if(!$manual&&(($s['settings']['guestInvitationPolicy']??'none')==='none'&&empty($s['settings']['guestInvitationSmsEnabled'])))return 'skipped';if($data['channel']!=='email')return 'blocked';
  $url=rtrim(config('app.url'),'/').'/meghivo/'.$data['token'];$text=$s['settings']['name'].' meghív a Ráérő vendégfiók használatára. Regisztrálj vagy lépj be, majd a meghívó segítségével kapcsold össze a foglalásaidat és üzeneteidet.';
  return SystemMail::send($data['recipient'],'guest_invite',['business'=>$s['settings']['name'],'name'=>$client['name'],'details'=>$text,'manage_url'=>$url,'account_url'=>$url,'provider_url'=>rtrim(config('app.url'),'/').'/foglalas?provider='.app(Tenant::class)->id]);
 }
 private static function matches(array $client,array $data):bool{return $data['recipient']===($data['channel']==='email'?Contact::email($client['email']??''):Contact::e164($client['phone']??''));}
 public static function claim(string $token,int $guest):array{
  return DB::transaction(function()use($token,$guest){$row=DB::table('guest_invitations')->where('token_hash',hash('sha256',$token))->lockForUpdate()->first();abort_unless($row,404);abort_if(now()->gte($row->expires_at),410,'A meghívó lejárt.');abort_if($row->guest_id&&(int)$row->guest_id!==$guest,403,'A meghívót másik vendégfiók már felhasználta.');
   if(!$row->claimed_at){app(Tenant::class)->run((int)$row->provider_id,fn()=>app(SalonStore::class)->mutate(function(&$s)use($row,$guest){$found=false;foreach($s['clients'] as &$c)if($c['id']===$row->client_id&&Clients::visible($c)){abort_if(!empty($c['guestId'])&&(int)$c['guestId']!==$guest,403);$data=json_decode(Crypt::decryptString($row->payload),true,32,JSON_THROW_ON_ERROR);abort_unless(self::matches($c,$data),410,'A vendég elérhetősége megváltozott, ez a meghívó már nem használható.');$c['guestId']=$guest;$found=true;break;}unset($c);abort_unless($found,404);foreach($s['bookings'] as &$b)if(($b['clientId']??null)===$row->client_id){abort_if(!empty($b['guestId'])&&(int)$b['guestId']!==$guest,403);$b['guestId']=$guest;$b['updatedAt']=now()->toISOString();}unset($b);}));DB::table('guest_invitations')->where('id',$row->id)->update(['guest_id'=>$guest,'claimed_at'=>now(),'updated_at'=>now()]);}
   return ['claimed'=>true,'providerId'=>(int)$row->provider_id];
  },5);
 }
}
