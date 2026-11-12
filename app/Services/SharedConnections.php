<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
final class SharedConnections
{
 public const KEYS=['smtp_host','smtp_port','smtp_username','smtp_password','mail_from','mail_name','bulkgate_application_id','bulkgate_application_token','bulkgate_sender_type','bulkgate_sender_value','google_client_id','google_client_secret','facebook_client_id','facebook_client_secret','apple_client_id','apple_team_id','apple_key_id','apple_private_key'];
 public static function all():array {
  $legacy=[];$path=config('integrations.path');if($path&&is_file($path))$legacy=json_decode(Crypt::decryptString(file_get_contents($path)),true,512,JSON_THROW_ON_ERROR);
  $saved=app(PlatformConnections::class)->values();$result=[];
  foreach(self::KEYS as $key){$fallback=config('integrations.connections.'.$key);if(str_starts_with($key,'smtp_')||in_array($key,['mail_from','mail_name'],true)){$map=['smtp_host'=>'host','smtp_port'=>'port','smtp_username'=>'username','smtp_password'=>'password','mail_from'=>'from','mail_name'=>'name'];$fallback=config('newsletter.'.$map[$key])?:$fallback;}$result[$key]=$saved[$key]??($legacy[$key]??$fallback);}
  return $result;
 }
 public static function privateForProvider(string $key):bool{return str_starts_with($key,'twilio_')||in_array(str_replace('_configured','',$key),self::KEYS,true)||str_starts_with($key,'admin_');}
}
