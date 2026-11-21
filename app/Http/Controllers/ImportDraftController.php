<?php
namespace App\Http\Controllers;
use App\Services\{ContactImportCache,StaffAccess,Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class ImportDraftController
{
 private function key(Request $r):string{abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r),403);$provider=(int)$r->session()->get('provider_id',1);abort_unless($provider===app(Tenant::class)->id,403);return 'import-draft:'.$provider.':'.($r->session()->get('provider_member_id')?'member:'.$r->session()->get('provider_member_id'):'owner');}
 public function handle(Request $r){$key=$this->key($r);$cache=ContactImportCache::store();if($r->isMethod('GET')){$raw=$cache->get($key);return response()->json($raw?json_decode(Crypt::decryptString($raw),true):['draft'=>null,'version'=>null])->header('Cache-Control','no-store');}
  $v=$r->validate(['version'=>'present|nullable|uuid']);$deleting=$r->isMethod('DELETE')||$r->isMethod('POST');if($r->isMethod('POST'))$r->validate(['action'=>'required|in:discard']);
  if(!$deleting)$v+=$r->validate(['draft'=>'required|array:phase,source,records,page,group,search,confirmSelected,confirmPage,scroll,open,tabId','draft.phase'=>'required|in:review,confirm','draft.source'=>'required|in:phone,mail,file','draft.records'=>'required|array|min:1|max:2000','draft.records.*'=>'required|array:name,phone,email,groups,selected','draft.records.*.name'=>'present|nullable|string|max:2000','draft.records.*.phone'=>'present|nullable|string|max:2000','draft.records.*.email'=>'present|nullable|string|max:2000','draft.records.*.groups'=>'sometimes|array|max:100','draft.records.*.groups.*'=>'string|max:300','draft.records.*.selected'=>'required|boolean','draft.page'=>'sometimes|integer|min:1|max:100','draft.confirmPage'=>'sometimes|integer|min:1|max:100','draft.group'=>'sometimes|nullable|string|max:300','draft.search'=>'sometimes|nullable|string|max:2000','draft.confirmSelected'=>'sometimes|array|max:2000','draft.confirmSelected.*'=>'integer|min:0|max:1999|distinct','draft.open'=>'sometimes|boolean','draft.tabId'=>'sometimes|uuid','draft.scroll'=>'sometimes|integer|min:0|max:1000000']);
  return $cache->lock($key.':lock',10)->block(3,function()use($r,$cache,$key,$v,$deleting){$raw=$cache->get($key);$old=$raw?json_decode(Crypt::decryptString($raw),true):null;abort_if($old&&$old['version']!==$v['version'],409,'Az import piszkozatát egy másik ablakban módosították. Nyisd meg a friss piszkozatot.');if($deleting){$cache->forget($key);return response()->json(['ok'=>true]);}$result=['version'=>(string)Str::uuid(),'draft'=>$v['draft'],'savedAt'=>now()->toIso8601String(),'expiresAt'=>now()->addDay()->toIso8601String()];$cache->put($key,Crypt::encryptString(json_encode($result,JSON_THROW_ON_ERROR)),86400);return response()->json(['version'=>$result['version'],'savedAt'=>$result['savedAt'],'expiresAt'=>$result['expiresAt']]);});
 }
}
