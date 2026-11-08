<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt,Hash};
use Illuminate\Support\Str;
final class ProviderPasswords{
 public static function version(int $id):int{return (int)(DB::table('provider_security')->where('provider_id',$id)->value('version')??1);}
 public static function hash(int $id):?string{return DB::table('provider_security')->where('provider_id',$id)->value('password_hash')??($id===1?config('raero.admin_password_hash'):DB::table('providers')->where('id',$id)->value('password_hash'));}
 public static function email(int $id):string{if($id!==1)return (string)DB::table('providers')->where('id',$id)->value('email');$s=DB::table('salon_state')->where('id',1)->value('data');return $s?(json_decode($s,true)['settings']['providerEmail']??''):'';}
 public static function change(int $id,string $password):int{
  DB::table('provider_security')->insertOrIgnore(['provider_id'=>$id,'version'=>1,'created_at'=>now(),'updated_at'=>now()]);$c=DB::table('provider_security')->where('provider_id',$id)->lockForUpdate()->first();$version=$c->version+1;DB::table('provider_security')->where('provider_id',$id)->update(['password_hash'=>Hash::make($password),'version'=>$version,'updated_at'=>now()]);DB::table('provider_password_tokens')->where('provider_id',$id)->whereNull('used_at')->update(['used_at'=>now()]);return $version;
 }
 public static function queue(int $id):void{
  if(DB::table('provider_password_tokens')->where('provider_id',$id)->whereNull('used_at')->where('created_at','>',now()->subMinutes(2))->where('expires_at','>',now())->exists())return;
  $token=bin2hex(random_bytes(32));$uuid=(string)Str::uuid();DB::table('provider_password_tokens')->insert(['id'=>$uuid,'provider_id'=>$id,'email'=>self::email($id),'token_hash'=>hash('sha256',$token),'payload'=>Crypt::encryptString($token),'expires_at'=>now()->addHour(),'created_at'=>now(),'updated_at'=>now()]);DB::table('deliveries')->insert(['provider_id'=>$id,'dedup'=>hash('sha256','provider-reset:'.$uuid),'payload'=>Crypt::encryptString(json_encode(['kind'=>'provider_reset','tokenId'=>$uuid])),'status'=>'pending','attempts'=>0,'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
 }
 public static function deliver(string $id):string{$t=DB::table('provider_password_tokens')->find($id);if(!$t||$t->used_at||now()->gte($t->expires_at)||$t->email!==self::email((int)$t->provider_id))return 'skipped';$url=rtrim(config('app.url'),'/').'/belepes?reset='.Crypt::decryptString($t->payload);return SystemMail::send($t->email,'provider_reset',['business'=>'Ráérő','name'=>'','details'=>'Szolgáltatói jelszó-visszaállítást kértél. A hivatkozás egy óráig és egyszer használható. Ha nem te kérted, nem kell tenned semmit.','manage_url'=>$url,'account_url'=>$url,'provider_url'=>rtrim(config('app.url'),'/')]);}
}
