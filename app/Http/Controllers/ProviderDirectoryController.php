<?php
namespace App\Http\Controllers;
use App\Services\ProviderDiscovery as Discovery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Cache};
use Illuminate\Validation\Rule;
final class ProviderDirectoryController
{
 public const CACHE_KEY='public-directory-catalog-v4';
 public function index(Request $r){
  $v=$r->validate(['q'=>'nullable|string|max:100','city'=>'nullable|string|max:100','category'=>['nullable',Rule::in(array_keys(Discovery::CATEGORIES))],'page'=>'nullable|integer|min:1|max:10000']);$q=Discovery::fold($v['q']??'');$city=Discovery::fold($v['city']??'');$category=$v['category']??'';$page=(int)($v['page']??1);$limit=24;
  // Cache only an explicit public projection, shared by all filter combinations.
  $catalog=Cache::remember(self::CACHE_KEY,60,function(){
   $items=[];$active=DB::table('providers')->where('active',true)->pluck('id')->all();
   foreach(DB::table('salon_state')->select('id','data')->orderBy('id')->cursor() as $row){if((int)$row->id!==1&&!in_array((int)$row->id,$active))continue;$s=json_decode($row->data,true,512,JSON_THROW_ON_ERROR);$item=Discovery::publicRow((int)$row->id,$s);if($item)$items[]=$item;}
   return $items;
  });
  $towns=[];$counts=array_fill_keys(array_keys(Discovery::CATEGORIES),0);foreach($catalog as $item){if($item['city']!=='')$towns[$item['city']]=true;foreach($item['categories'] as $c)$counts[$c]++;}
  $cities=array_keys($towns);usort($cities,fn($a,$b)=>Discovery::fold($a)<=>Discovery::fold($b));
  $filtered=array_values(array_filter($catalog,fn($item)=>($city===''||Discovery::fold($item['city'])===$city)&&($category===''||in_array($category,$item['categories'],true))&&($q===''||str_contains(Discovery::fold($item['name'].' '.$item['profession'].' '.$item['city'].' '.$item['address'].' '.implode(' ',$item['services'])),$q))));
  return response()->json(['items'=>array_slice($filtered,($page-1)*$limit,$limit),'total'=>count($filtered),'page'=>$page,'pages'=>(int)ceil(count($filtered)/$limit),'cities'=>$cities,'categories'=>array_map(fn($id,$name)=>['id'=>$id,'name'=>$name,'count'=>$counts[$id]],array_keys(Discovery::CATEGORIES),array_values(Discovery::CATEGORIES))])->header('Cache-Control','public, max-age=60');
 }
}
