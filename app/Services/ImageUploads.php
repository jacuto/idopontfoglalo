<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Decode and re-encode every uploaded picture; never retain the oversized original. */
final class ImageUploads {
 public static function limit(string $target):int{return match($target){'logo','avatar'=>800,'staff','service'=>1000,default=>1500};}
 public static function process(string $bytes,string $target,?string $source=null):string{
  abort_unless(strlen($bytes)<=5*1024*1024,422,'Legfeljebb 5 MB méretű kép tölthető fel.');$info=@getimagesizefromstring($bytes);
  abort_unless($info&&in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)&&$info[0]<=6000&&$info[1]<=6000,422,'JPG, PNG vagy WebP kép szükséges, legfeljebb 6000 × 6000 képponttal.');
  abort_unless(extension_loaded('gd')&&function_exists('imagewebp'),422,'A képfeldolgozáshoz PHP GD és WebP támogatás szükséges. Kérd az üzemeltető segítségét.');
  $limit=ini_get('memory_limit');$budget=(int)$limit;if(str_ends_with(strtoupper($limit),'G'))$budget*=1024*1024*1024;elseif(str_ends_with(strtoupper($limit),'M'))$budget*=1024*1024;elseif(str_ends_with(strtoupper($limit),'K'))$budget*=1024;
  abort_if($budget>0&&memory_get_usage(true)+$info[0]*$info[1]*6+16*1024*1024>$budget,422,'Ehhez a képhez túl nagy memória szükséges. Válassz kisebb felbontású képet.');
  $image=@imagecreatefromstring($bytes);abort_unless($image,422,'A képfájl nem dolgozható fel.');$output=null;
  try{
   if($info[2]===IMAGETYPE_JPEG&&$source&&function_exists('exif_read_data')){ $exif=@exif_read_data($source);$orientation=(int)($exif['Orientation']??1);if(in_array($orientation,[2,4,5,7],true))imageflip($image,$orientation===4?IMG_FLIP_VERTICAL:IMG_FLIP_HORIZONTAL);$angle=match($orientation){3=>180,5,8=>90,6,7=>-90,default=>0};if($angle){$rotated=imagerotate($image,$angle,0);if($rotated){imagedestroy($image);$image=$rotated;}}}
   $width=imagesx($image);$height=imagesy($image);$scale=min(1,self::limit($target)/max($width,$height));$w=max(1,(int)round($width*$scale));$h=max(1,(int)round($height*$scale));$output=imagecreatetruecolor($w,$h);imagealphablending($output,false);imagesavealpha($output,true);imagefill($output,0,0,imagecolorallocatealpha($output,0,0,0,127));imagecopyresampled($output,$image,0,0,0,0,$w,$h,$width,$height);
   ob_start();try{$ok=imagewebp($output,null,82);$result=ob_get_contents();}finally{ob_end_clean();}abort_unless($ok&&$result,422,'A kép mentése nem sikerült.');return $result;
  }finally{imagedestroy($image);if($output)imagedestroy($output);}
 }
 public static function cleanup(?string $url):void{if(!$url||!preg_match('~/media/([a-f0-9-]{36})$~',$url,$m))return;$asset=\Illuminate\Support\Facades\DB::table('media_assets')->where('id',$m[1])->where('provider_id',app(Tenant::class)->id)->first();if(!$asset)return;$state=app(SalonStore::class)->read();$urls=array_merge([$state['profile']['logo']??'',$state['profile']['cover']??''],$state['profile']['photos']??[],array_column($state['staff'],'image'),array_column($state['services'],'image'));if(collect($urls)->contains(fn($value)=>is_string($value)&&str_ends_with($value,'/media/'.$m[1])))return;Storage::disk('local')->delete($asset->path);\Illuminate\Support\Facades\DB::table('media_assets')->where('id',$asset->id)->where('provider_id',app(Tenant::class)->id)->delete();}
 public static function store(\Illuminate\Http\UploadedFile $file,string $target,string $directory='media'):string{$bytes=self::process(file_get_contents($file->getRealPath()),$target,$file->getRealPath());$path=$directory.'/'.Str::uuid().'.webp';abort_unless(Storage::disk('local')->put($path,$bytes),422,'A kép nem menthető a tárhelyre.');return $path;}
}
