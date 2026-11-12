<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

/** One transactional SMS request, shared by booking notifications and account invitations. */
final class BulkGateSms {
 public static function ready(?array $c=null):bool{$c??=SharedConnections::all();return preg_match('/^[1-9][0-9]*$/',(string)($c['bulkgate_application_id']??''))&& !empty($c['bulkgate_application_token'])&&(($c['bulkgate_sender_type']??'gSystem')==='gSystem'||!empty($c['bulkgate_sender_value']));}
 public static function encoding(string $text):array {
  $basic="@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";$extended="^{}\\[~]|€";$units=0;
  foreach(preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY) as $c){if(str_contains($basic,$c))$units++;elseif(str_contains($extended,$c))$units+=2;else return ['unicode'=>true,'units'=>strlen(mb_convert_encoding($text,'UTF-16BE','UTF-8'))/2];}
  return ['unicode'=>false,'units'=>$units];
 }
 public function send(string $recipient,string $text,?array $c=null):string {
  $c??=SharedConnections::all();if(!self::ready($c))throw new SmsDeliveryException('Hiányzó BulkGate Application ID, token vagy feladó.');
  $number=Contact::e164($recipient);if(!preg_match('/^\+[1-9]\d{7,14}$/',$number))throw new SmsDeliveryException('Az SMS címzettjének telefonszáma hibás.');
  $encoding=self::encoding($text);if(trim($text)===''||$encoding['units']>($encoding['unicode']?268:612))throw new SmsDeliveryException('Az SMS szövege üres vagy túl hosszú a BulkGate API számára. Ellenőrizd a foglalás szövegét.');
  $body=['application_id'=>(int)$c['bulkgate_application_id'],'application_token'=>$c['bulkgate_application_token'],'number'=>substr($number,1),'text'=>$text,'unicode'=>$encoding['unicode'],'sender_id'=>$c['bulkgate_sender_type']??'gSystem','duplicates_check'=>'on','tag'=>'raero'];
  if($body['sender_id']!=='gSystem')$body['sender_id_value']=$c['bulkgate_sender_value'];
  try{$response=Http::timeout(20)->connectTimeout(10)->acceptJson()->post('https://portal.bulkgate.com/api/1.0/simple/transactional',$body);}catch(\Throwable){throw new SmsDeliveryException('A BulkGate-küldés eredménye nem igazolható. Újraküldés előtt ellenőrizd a BulkGate üzenetelőzményét.',true);}
  if($response->status()===429)throw new SmsDeliveryException('A BulkGate átmenetileg korlátozza a kéréseket. Később újrapróbáljuk.',false,true);
  if($response->serverError())throw new SmsDeliveryException('A BulkGate-kérés eredménye nem igazolható. Ellenőrizd az üzenetelőzményt.',true);
  $data=$response->json();
  if(!$response->successful()||isset($data['error']))throw new SmsDeliveryException('A BulkGate elutasította az SMS-t. Ellenőrizd az API-kulcsot, az egyenleget, a feladót és az API napi limitjét.');
  $id=$data['data']['sms_id']??null;
  if(($data['data']['status']??'')!=='accepted'||!is_string($id)||$id==='')throw new SmsDeliveryException('A BulkGate nem igazolta az SMS átvételét. Ellenőrizd az üzenetelőzményt, mielőtt újraküldöd.',true);
  return $id;
 }
}
