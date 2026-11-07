<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Storage,Schema};
use Illuminate\Support\Str;
final class BusinessAvatar {
 public function import(int $provider,string $target,?string $entity,string $social,mixed $url,bool $replace=false):bool {
  try{if(!Schema::hasTable('media_assets')||!in_array($target,['logo','staff'],true))return false;$row=DB::table('salon_state')->find($provider);if(!$row)return false;$s=json_decode($row->data,true);$current=$target==='logo'?($s['profile']['logo']??null):(collect($s['staff'])->firstWhere('id',$entity)['image']??null);if($current&&!$replace)return false;
   $bytes=app(SocialAvatar::class)->fetch($social,$url);if(!$bytes)return false;$info=getimagesizefromstring($bytes);$ext=[IMAGETYPE_PNG=>'png',IMAGETYPE_JPEG=>'jpg',IMAGETYPE_WEBP=>'webp'][$info[2]];$id=(string)Str::uuid();$path='media/'.$id.'.'.$ext;if(!Storage::disk('local')->put($path,$bytes))return false;$uri=rtrim(config('app.url'),'/').'/media/'.$id;
   try{$changed=app(Tenant::class)->run($provider,fn()=>app(SalonStore::class)->mutate(function(&$state)use($target,$entity,$replace,$uri,$path,$id,$provider){
    if($target==='logo'){if(!empty($state['profile']['logo'])&&!$replace)return false;$state['profile']['logo']=$uri;}else{$found=false;foreach($state['staff'] as &$person)if($person['id']===$entity&&empty($person['archived'])){if(!empty($person['image'])&&!$replace)return false;$person['image']=$uri;$found=true;break;}unset($person);if(!$found)return false;}
    DB::table('media_assets')->insert(['id'=>$id,'provider_id'=>$provider,'target'=>$target,'entity_id'=>$entity,'path'=>$path,'created_at'=>now(),'updated_at'=>now()]);return true;
   }));if(!$changed)Storage::disk('local')->delete($path);return $changed;}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
  }catch(\Throwable){return false;}
 }
}
