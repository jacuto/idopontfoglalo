<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
final class PublicDocuments {
 public function settings():array {
  $row=DB::table('platform_settings')->find(1);$data=$row?json_decode($row->data,true):[];
  return array_replace(['privacyEmail'=>SharedConnections::all()['mail_from']??'','hostingName'=>'','hostingAddress'=>'','hostingPrivacyUrl'=>'','scope'=>'all','retentionNotes'=>'A fiókadatokat a fiók fennállásáig kezeljük. Törlési kérelemnél a szükségtelen adatokat töröljük vagy anonimizáljuk; a jogszabály vagy fennálló igény miatt megőrzendő adatokat elkülönítve, a szükséges ideig őrizzük meg. A foglalások megőrzéséről a választott szolgáltató adatkezelőként is rendelkezik.'], $data['legal']??[]);
 }
 public function save(array $values):void {
  DB::transaction(function()use($values){DB::table('platform_settings')->insertOrIgnore(['id'=>1,'data'=>'{}']);$row=DB::table('platform_settings')->where('id',1)->lockForUpdate()->first();$data=json_decode($row->data,true);$data['legal']=$values;DB::table('platform_settings')->where('id',1)->update(['data'=>json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);});
 }
 public function page(string $document){
  $operator=app(\App\Http\Controllers\PlatformController::class)->operator();unset($operator['legal']);
  return response()->view('documents.'.$document,['operator'=>$operator,'legal'=>$this->settings(),'updated'=>'2026. november 12.'])->header('Cache-Control','no-cache');
 }
}
