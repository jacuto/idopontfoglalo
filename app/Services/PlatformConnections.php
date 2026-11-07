<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
final class PlatformConnections {
 private const MAP=[
  'stripe_key'=>'integrations.platform_stripe.key','stripe_price'=>'integrations.platform_stripe.price','stripe_webhook_secret'=>'integrations.platform_stripe.webhook_secret',
  'billingo_key'=>'integrations.platform_billingo.key','billingo_block'=>'integrations.platform_billingo.block',
  'google_client_id'=>'integrations.connections.google_client_id','google_client_secret'=>'integrations.connections.google_client_secret','facebook_client_id'=>'integrations.connections.facebook_client_id','facebook_client_secret'=>'integrations.connections.facebook_client_secret','apple_client_id'=>'integrations.connections.apple_client_id','apple_team_id'=>'integrations.connections.apple_team_id','apple_key_id'=>'integrations.connections.apple_key_id','apple_private_key'=>'integrations.connections.apple_private_key','twilio_sid'=>'integrations.connections.twilio_sid','twilio_token'=>'integrations.connections.twilio_token','twilio_from'=>'integrations.connections.twilio_from',
  'smtp_host'=>'newsletter.host','smtp_port'=>'newsletter.port','smtp_username'=>'newsletter.username','smtp_password'=>'newsletter.password','mail_from'=>'newsletter.from','mail_name'=>'newsletter.name',
 ];
 private function path():string{return config('operator.connections_path',storage_path('app/private/platform-connections.enc'));}
 private function saved():array{$path=$this->path();return is_file($path)?json_decode(Crypt::decryptString(file_get_contents($path)),true,512,JSON_THROW_ON_ERROR):[];}
 public function values():array{return $this->saved();}
 public function apply():void {foreach($this->saved() as $key=>$value)if(isset(self::MAP[$key]))config([self::MAP[$key]=>$value]);$shared=SharedConnections::all();foreach(['smtp_host','smtp_port','smtp_username','smtp_password','mail_from','mail_name'] as $key)if(!empty($shared[$key]))config([self::MAP[$key]=>$shared[$key]]);}
 public function status():array {
  $result=[];foreach(self::MAP as $key=>$config){$v=in_array($key,SharedConnections::KEYS,true)?(SharedConnections::all()[$key]??null):config($config);$result[$key.'_configured']=!empty($v);if(!in_array($key,['stripe_key','stripe_webhook_secret','billingo_key','smtp_password','google_client_secret','facebook_client_secret','apple_private_key','twilio_sid','twilio_token'],true))$result[$key]=$v??'';}
  return $result;
 }
 public function save(array $data):void {
  $path=$this->path();if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);$lock=fopen($path.'.lock','c');
  if(!$lock||!flock($lock,LOCK_EX))throw new \RuntimeException('A központi kapcsolat most nem zárolható. Próbáld újra.');$temporary=null;
  try{$values=$this->saved();foreach(self::MAP as $key=>$config)if(isset($data[$key])&&$data[$key]!=='')$values[$key]=$data[$key];
   $temporary=tempnam(dirname($path),'platform-');chmod($temporary,0600);
   if(file_put_contents($temporary,Crypt::encryptString(json_encode($values,JSON_THROW_ON_ERROR)))===false||!rename($temporary,$path))throw new \RuntimeException('Nem sikerült elmenteni a központi kapcsolatot.');
  }finally{if($temporary&&is_file($temporary))unlink($temporary);flock($lock,LOCK_UN);fclose($lock);}
  $this->apply();
 }
}
