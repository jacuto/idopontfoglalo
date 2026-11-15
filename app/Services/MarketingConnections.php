<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt};
final class MarketingConnections
{
 public static function allowed(array $s):bool{return PaidFeatures::enabled($s)&&(($s['subscription']['plan']??'')==='pro'||($s['subscription']['status']??'')==='trialing');}
 public static function get(int $provider):array{$v=DB::table('marketing_connections')->where('provider_id',$provider)->value('credentials');return $v?json_decode(Crypt::decryptString($v),true,64,JSON_THROW_ON_ERROR):[];}
 public static function save(int $provider,array $v):void{
  DB::transaction(function()use($provider,$v){DB::table('marketing_connections')->insertOrIgnore(['provider_id'=>$provider,'credentials'=>Crypt::encryptString('{}'),'created_at'=>now(),'updated_at'=>now()]);$row=DB::table('marketing_connections')->where('provider_id',$provider)->lockForUpdate()->first();$old=json_decode(Crypt::decryptString($row->credentials),true);$changed=($old['engine']??'')!==$v['engine'];if($changed)$old=[];
   if(empty($v['apiKey']))unset($v['apiKey']);$keys=array_replace($old,$v);if(empty($keys['apiKey']))throw new \InvalidArgumentException('Add meg a választott hírlevélküldő API-kulcsát.');if($provider===0&&$keys['engine']!=='brevo')throw new \InvalidArgumentException('A központi hírlevélküldő Brevo.');
   if($keys['engine']==='mailchimp'){if(!preg_match('/-(us[0-9]{1,3})$/',$keys['apiKey'],$m))throw new \InvalidArgumentException('A Mailchimp API-kulcs végén szerepelnie kell a kiszolgálókódnak, például -us21.');$keys['dc']=$m[1];}
   $keys['webhookToken']=$keys['webhookToken']??bin2hex(random_bytes(32));$keys['revision']=hash('sha256',$keys['engine'].':'.$keys['apiKey']);DB::table('marketing_connections')->where('id',$row->id)->update(['credentials'=>Crypt::encryptString(json_encode($keys,JSON_THROW_ON_ERROR)),'updated_at'=>now()]);
  });
 }
 public static function patch(int $provider,array $patch):void{DB::transaction(function()use($provider,$patch){$row=DB::table('marketing_connections')->where('provider_id',$provider)->lockForUpdate()->first();abort_unless($row,422);$keys=json_decode(Crypt::decryptString($row->credentials),true);DB::table('marketing_connections')->where('id',$row->id)->update(['credentials'=>Crypt::encryptString(json_encode(array_replace($keys,$patch),JSON_THROW_ON_ERROR)),'updated_at'=>now()]);});}
 public static function status(int $provider):array{$keys=self::get($provider);$safe=array_intersect_key($keys,array_flip(['engine','senderEmail','senderName','company','address','city','zip','country','listId','folderId']));$safe['configured']=!empty($keys['apiKey']);$safe['webhookUrl']=empty($keys['webhookToken'])?'':rtrim(config('app.url'),'/').'/api/marketing/webhook/'.$provider.'/'.$keys['engine'].'/'.$keys['webhookToken'];return $safe;}
}
