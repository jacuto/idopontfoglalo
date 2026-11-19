<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
final class WebAddresses
{
 public static function normalize(mixed $value,string $field='website'):?string {
  if($value===null)return null;if(!is_string($value))throw ValidationException::withMessages([$field=>'Szöveges webcímet adj meg.']);if(trim($value)==='')return null;
  $url=trim((string)$value);if(!preg_match('~^[a-z][a-z0-9+.-]*:~i',$url))$url='https://'.ltrim($url,'/');
  $parts=parse_url($url);$host=strtolower($parts['host']??'');
  if(!$parts||!in_array(strtolower($parts['scheme']??''),['http','https'],true)||!filter_var($url,FILTER_VALIDATE_URL)||isset($parts['user'])||isset($parts['pass'])||!str_contains($host,'.'))throw ValidationException::withMessages([$field=>'Érvényes webcímet adj meg, például: www.pelda.hu.']);
  if(in_array($field,['facebook','instagram'],true)){$domain=$field.'.com';if($host!==$domain&&!str_ends_with($host,'.'.$domain))throw ValidationException::withMessages([$field=>($field==='facebook'?'Facebook':'Instagram').' hivatkozást adj meg ('.$domain.').']);}
  return $url;
 }
 public static function fields(array $data):array{foreach(['website','mapUrl','facebook','instagram','contactUrl','hostingPrivacyUrl'] as $key)if(array_key_exists($key,$data))$data[$key]=self::normalize($data[$key],$key);return $data;}
}
