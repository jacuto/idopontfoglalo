<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
final class MediaController
{
 public function upload(Request $r){
  abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);
  $v=$r->validate(['target'=>'required|in:logo,cover,photo,staff,service','entityId'=>'nullable|string|max:80','image'=>'required|file|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=4096,max_height=4096']);
  $image=$v['image'];$info=@getimagesize($image->getRealPath());abort_unless($info&&in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true),422,'Csak valódi JPG-, PNG- vagy WebP-kép tölthető fel.');
  $id=(string)Str::uuid();$path=$image->store('media','local');$url=rtrim(config('app.url'),'/').'/media/'.$id;$target=$v['target'];$entity=$v['entityId']??null;
  try{app(SalonStore::class)->mutate(function(&$s)use($id,$path,$url,$target,$entity){
   if(in_array($target,['staff','service'],true)){$key=$target==='staff'?'staff':'services';$found=false;foreach($s[$key] as &$row)if($row['id']===$entity&&empty($row['archived'])){$row['image']=$url;$found=true;break;}unset($row);abort_unless($found,404);}
   elseif($target==='photo'){if(count($s['profile']['photos']??[])>=12)throw new \InvalidArgumentException('Legfeljebb 12 üzletfotó tölthető fel.');$s['profile']['photos'][]=$url;}
   else $s['profile'][$target]=$url;
   DB::table('media_assets')->insert(['id'=>$id,'provider_id'=>app(Tenant::class)->id,'target'=>$target,'entity_id'=>$entity,'path'=>$path,'created_at'=>now(),'updated_at'=>now()]);
  });}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
  return response()->json(['url'=>$url],201);
 }
 public function image(string $id){
  $asset=DB::table('media_assets')->find($id);abort_unless($asset,404);$row=DB::table('salon_state')->find($asset->provider_id);abort_unless($row,404);$s=json_decode($row->data,true);$linked=false;
  if(in_array($asset->target,['logo','cover'],true))$linked=str_ends_with($s['profile'][$asset->target]??'','/media/'.$id);
  elseif($asset->target==='photo')$linked=collect($s['profile']['photos']??[])->contains(fn($url)=>str_ends_with($url,'/media/'.$id));
  else {$key=$asset->target==='staff'?'staff':'services';$entity=collect($s[$key]??[])->firstWhere('id',$asset->entity_id);$linked=$entity&&empty($entity['archived'])&&str_ends_with($entity['image']??'','/media/'.$id);}
  abort_unless($linked&&Storage::disk('local')->exists($asset->path),404);return response()->file(Storage::disk('local')->path($asset->path),['Cache-Control'=>'public,max-age=3600','X-Content-Type-Options'=>'nosniff']);
 }
}
