<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** One session-bound staging protocol for contacts and calendar imports. Existing keys stay compatible. */
final class ImportStaging
{
 public static function key(Request $request,string $kind,string $id):string
 {
  if(!in_array($kind,['contact-import','calendar-import','record-import'],true))throw new \InvalidArgumentException('Ismeretlen importtípus.');
  $sessionKey=str_replace('-','_',$kind).'_nonce';$nonce=$request->session()->get($sessionKey);
  if(!is_string($nonce)||$nonce===''){$nonce=bin2hex(random_bytes(32));$request->session()->put($sessionKey,$nonce);}
  return $kind.':'.WorkspaceAccess::admin($request).':'.hash('sha256',$nonce).':'.$id;
 }
 public static function create(Request $request,string $kind,array $data):string{$id=(string)Str::uuid();self::put(self::key($request,$kind,$id),$data);return $id;}
 public static function put(string $key,array $data,int $seconds=1200):void{ContactImportCache::store()->put($key,Crypt::encryptString(json_encode($data,JSON_THROW_ON_ERROR)),$seconds);}
 public static function read(Request $request,string $kind,string $id):array{$raw=ContactImportCache::store()->get(self::key($request,$kind,$id));abort_unless($raw,410,$kind==='contact-import'?'Az import előnézete lejárt. Olvasd be újra a névjegyeket.':'A naptárimport lejárt. Kapcsold össze újra.');return json_decode(Crypt::decryptString($raw),true,512,JSON_THROW_ON_ERROR);}
}
