<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt,Hash};
use Illuminate\Support\Str;
final class GuestPasswords{
 public static function queue(object $credential,string $purpose,int $provider,string $returnTo=''):void{
  if(DB::table('guest_auth_tokens')->where('credential_id',$credential->id)->where('purpose',$purpose)->whereNull('used_at')->where('expires_at','>',now())->where('created_at','>',now()->subMinutes(2))->exists())return;
  $token=bin2hex(random_bytes(32));$id=(string)Str::uuid();$payload=Crypt::encryptString(json_encode(['token'=>$token,'email'=>$credential->email,'provider'=>$provider,'returnTo'=>$returnTo],JSON_THROW_ON_ERROR));
  DB::table('guest_auth_tokens')->insert(['id'=>$id,'credential_id'=>$credential->id,'purpose'=>$purpose,'token_hash'=>hash('sha256',$token),'payload'=>$payload,'expires_at'=>now()->addHours($purpose==='verify'?24:1),'created_at'=>now(),'updated_at'=>now()]);DB::table('deliveries')->insert(['provider_id'=>$provider,'dedup'=>hash('sha256','guest-auth:'.$id),'payload'=>Crypt::encryptString(json_encode(['kind'=>'guest_auth','tokenId'=>$id])),'status'=>'pending','attempts'=>0,'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function deliver(string $id):string{
  $row=DB::table('guest_auth_tokens')->find($id);if(!$row||$row->used_at||now()->gte($row->expires_at))return 'skipped';$c=DB::table('guest_credentials')->find($row->credential_id);if(!$c||($row->purpose==='verify'&&$c->verified_at))return 'skipped';$g=DB::table('guest_accounts')->find($c->guest_id);if(!$g)return 'skipped';$p=json_decode(Crypt::decryptString($row->payload),true,32,JSON_THROW_ON_ERROR);if($p['email']!==$c->email)return 'skipped';
  $url=rtrim(config('app.url'),'/').'/fiokom?provider='.(int)$p['provider'].'&'.$row->purpose.'='.urlencode($p['token']);if(in_array($p['returnTo'],['booking','messages','operatorMessages'],true))$url.='&returnTo='.$p['returnTo'];
  return SystemMail::send($c->email,$row->purpose==='verify'?'guest_verify':'guest_reset',['business'=>'Ráérő','name'=>$g->profile_name??$g->name,'details'=>$row->purpose==='verify'?'Erősítsd meg az e-mail-címedet, majd állítsd be a saját nevedet és jelszavadat. A hivatkozás 24 óráig érvényes.':'Új jelszót kértél. A hivatkozás egy óráig érvényes. Ha nem te kérted, nem kell tenned semmit.','manage_url'=>$url,'account_url'=>$url,'provider_url'=>rtrim(config('app.url'),'/')],$c->locale);
 }
 public static function consume(string $token,string $purpose,?string $password=null):object{
  return DB::transaction(function()use($token,$purpose,$password){$row=DB::table('guest_auth_tokens')->where('token_hash',hash('sha256',$token))->where('purpose',$purpose)->lockForUpdate()->first();abort_unless($row&&!$row->used_at&&now()->lt($row->expires_at),422,'A hivatkozás érvénytelen vagy lejárt.');$c=DB::table('guest_credentials')->where('id',$row->credential_id)->lockForUpdate()->first();abort_unless($c,422);$p=json_decode(Crypt::decryptString($row->payload),true);abort_unless($p['email']===$c->email,422);
   $update=['updated_at'=>now(),'verified_at'=>$c->verified_at?:now()];$update['password_hash']=Hash::make($password);$update['version']=$c->version+1;DB::table('guest_credentials')->where('id',$c->id)->update($update);DB::table('guest_accounts')->where('id',$c->guest_id)->update(['email_verified'=>true,'updated_at'=>now()]);DB::table('guest_auth_tokens')->where('credential_id',$c->id)->whereNull('used_at')->update(['used_at'=>now(),'updated_at'=>now()]);return DB::table('guest_credentials')->find($c->id);
  },5);
 }
}
