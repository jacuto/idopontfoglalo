<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Cache};
final class ProviderDirectoryController
{
 public function index(Request $r){
  $v=$r->validate(['q'=>'nullable|string|max:100','page'=>'nullable|integer|min:1|max:10000']);$q=mb_strtolower(\Illuminate\Support\Str::ascii(trim($v['q']??'')));$page=(int)($v['page']??1);$limit=24;
  // Read only explicitly public catalog fields; never hydrate guest or subscription data.
  $result=Cache::remember('public-directory-v1:'.hash('sha256',$q.':'.$page),60,function()use($q,$page,$limit){
   $items=[];$total=0;$active=DB::table('providers')->where('active',true)->pluck('id')->all();
   foreach(DB::table('salon_state')->select('id','data')->orderBy('id')->cursor() as $row){
    if((int)$row->id!==1&&!in_array((int)$row->id,$active))continue;
    $s=json_decode($row->data,true,512,JSON_THROW_ON_ERROR);$settings=$s['settings']??[];$profile=$s['profile']??[];
    $staff=array_column(array_filter($s['staff']??[],fn($p)=>empty($p['archived'])&&(($settings['businessMode']??'team')!=='solo'||$p['id']===($settings['primaryStaffId']??''))),'id');$services=array_filter($s['services']??[],fn($v)=>empty($v['archived'])&&array_intersect($v['staff']??[],$staff));if(!$services)continue;
    $name=$settings['name']??'Szolgáltató';$profession=$settings['profession']??'';$address=$profile['address']??'';
    $search=mb_strtolower(\Illuminate\Support\Str::ascii($name.' '.$profession.' '.$address.' '.implode(' ',array_column($services,'name'))));if($q!==''&&!str_contains($search,$q))continue;
    $total++;if($total<=($page-1)*$limit||count($items)>=$limit)continue;
    $logo=$profile['logo']??null;if(!is_string($logo)||!preg_match('#^/media/[a-f0-9-]+$#D',$logo))$logo=null;
    $items[]=['id'=>(int)$row->id,'name'=>$name,'profession'=>$profession,'address'=>$address,'logo'=>$logo,'services'=>array_slice(array_column($services,'name'),0,4),'url'=>'/foglalas?provider='.(int)$row->id];
   }
   return ['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/$limit)];
  });return response()->json($result)->header('Cache-Control','public, max-age=60');
 }
}
