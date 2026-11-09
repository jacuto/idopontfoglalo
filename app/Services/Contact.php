<?php
namespace App\Services;
use InvalidArgumentException;
final class Contact{
 public static function phone(?string $value,bool $strict=true):string{
  $raw=trim($value??'');if($raw==='')return '';$v=preg_replace('/^tel:\s*/i','',$raw);
  if(preg_match('/[^0-9+\s\p{Z}\p{Pd}().\/]/u',$v)){if($strict)throw new InvalidArgumentException('Érvénytelen telefonszám. Ellenőrizd a számjegyeket.');return $raw;}
  $d=preg_replace('/\D/','',$v);$international=str_starts_with($v,'+')||str_starts_with($d,'00');if(str_starts_with($d,'00'))$d=substr($d,2);
  if(str_starts_with($d,'36')){$national=substr($d,2);if(str_starts_with($national,'06')&&strlen($national)===11)$national=substr($national,2);elseif(str_starts_with($national,'0')&&in_array(strlen($national),[9,10],true))$national=substr($national,1);$d='06'.$national;}
  elseif(!$international&&!str_starts_with($d,'06')&&in_array(strlen($d),[8,9],true))$d='06'.$d;
  if(preg_match('/^06(?:1\d{7}|(?:20|30|31|50|70)\d{7}|(?!(?:20|30|31|50|70))[2-9]\d{7})$/',$d))return $d;
  if($international&&!str_starts_with($d,'06')&&preg_match('/^[1-9]\d{7,14}$/',$d))return '+'.$d;
  if($strict)throw new InvalidArgumentException('Érvénytelen telefonszám. A szám túl rövid, túl hosszú vagy hibás. Példa: 06301234567.');return $raw;
 }
 public static function email(?string $value):string{$v=mb_strtolower(trim($value??''));if($v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Érvénytelen emailcím. Példa: nev@pelda.hu.');return $v;}
 public static function apply(array $input,array $old=[]):array{if(array_key_exists('phone',$input))$input['phone']=self::phone($input['phone']);if(array_key_exists('email',$input))$input['email']=self::email($input['email']);return $input;}
 public static function e164(string $phone):string{$v=self::phone($phone,false);return str_starts_with($v,'06')?'+36'.substr($v,2):$v;}
 public static function display(string $phone):string{$v=self::phone($phone,false);if(!str_starts_with($v,'06'))return $v;$d=substr($v,2);if(str_starts_with($d,'1'))return '06 1 '.substr($d,1,3).' '.substr($d,4);return '06 '.substr($d,0,2).' '.substr($d,2,3).' '.substr($d,5);}
}
