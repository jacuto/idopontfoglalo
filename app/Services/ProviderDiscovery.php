<?php
namespace App\Services;
use Illuminate\Support\Str;
final class ProviderDiscovery
{
 public const CATEGORIES=['hair'=>'Fodrászat és barber','beauty'=>'Szépség és ápolás','massage'=>'Masszázs és wellness','fitness'=>'Sport és mozgás','health'=>'Egészség és terápia','coaching'=>'Tanácsadás és oktatás','other'=>'Egyéb szolgáltatás'];
 public static function fold(string $value):string{return mb_strtolower(Str::ascii(trim($value)));}
 public static function city(array $profile):string{
  if(!empty($profile['city']))return trim($profile['city']);
  $address=trim($profile['address']??'');if(preg_match('/^(?:HU[- ]?)?\d{4}\s+([^,\n\d]+)(?:,|\n|$)/u',$address,$m))return trim($m[1]);
  if(preg_match('/^([^,\n\d]+)[,\n]/u',$address,$m))return trim($m[1]);return '';
 }
 public static function categories(array $s,array $services):array{
  $chosen=array_values(array_intersect($s['profile']['categories']??[],array_keys(self::CATEGORIES)));if($chosen)return $chosen;
  $text=self::fold(($s['settings']['profession']??'').' '.implode(' ',array_column($services,'name')));$out=[];
  $words=['hair'=>['haj','fodrasz','barber','borbely'],'beauty'=>['szepseg','kozmet','smink','korom','pedikur','szempilla','szemoldok','gyanta'],'massage'=>['masszazs','wellness'],'fitness'=>['edzes','edzo','fitness','joga','yoga','pilates','sport'],'health'=>['gyogytorna','terapia','orvos','pszicholog','dietet','egeszseg'],'coaching'=>['tanacsadas','coaching','oktatas','tanar']];foreach($words as $key=>$terms)foreach($terms as $word)if(str_contains($text,$word)){$out[]=$key;break;}
  return $out?:['other'];
 }
 public static function image(mixed $url):?string{
  if(!is_string($url))return null;if(preg_match('#^/media/[a-f0-9-]{36}$#D',$url))return $url;
  return filter_var($url,FILTER_VALIDATE_URL)&&strtolower(parse_url($url,PHP_URL_SCHEME)??'')==='https'?$url:null;
 }
 public static function publicRow(int $id,array $s):?array{
  $people=array_column(Settings::activeStaff($s),'id');$services=array_values(array_filter($s['services']??[],fn($v)=>empty($v['archived'])&&array_intersect(Settings::serviceStaff($s,$v),$people)));if(!$services)return null;
  $p=$s['profile']??[];$categories=self::categories($s,$services);$cover=self::image($p['cover']??null);if(!$cover)foreach($services as $v)if($cover=self::image($v['image']??null))break;
  $reviews=array_values(array_filter($s['reviews']??[],fn($v)=>($v['direction']??'')==='guest'));
  return ['id'=>$id,'name'=>$s['settings']['name']??'Szolgáltató','profession'=>$s['settings']['profession']??'','city'=>self::city($p),'address'=>$p['address']??'','logo'=>self::image($p['logo']??null),'cover'=>$cover,'categories'=>$categories,'services'=>array_slice(array_column($services,'name'),0,4),'minPrice'=>min(array_column($services,'price')),'reviewCount'=>count($reviews),'rating'=>$reviews?round(array_sum(array_column($reviews,'score'))/count($reviews),1):null,'url'=>'/foglalas?provider='.$id];
 }
}
