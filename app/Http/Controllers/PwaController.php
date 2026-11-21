<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

final class PwaController
{
 public function manifest(){return response()->json(['id'=>'/raero-app','name'=>'Ráérő','short_name'=>'Ráérő','lang'=>'hu-HU','description'=>'Időpontok, vendégek és naptár egy helyen.','start_url'=>'/app','scope'=>'/','display'=>'standalone','background_color'=>'#f5f7fc','theme_color'=>'#132c59','launch_handler'=>['client_mode'=>'focus-existing'],'icons'=>[['src'=>'/pwa/icon-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any'],['src'=>'/pwa/icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable']]],200,['Content-Type'=>'application/manifest+json','Cache-Control'=>'public,max-age=3600']);}
 public function icon(string $size){abort_unless(in_array($size,['180','192','512'],true),404);return response(base64_decode(file_get_contents(resource_path('pwa/icon-'.$size.'.base64')),true),200,['Content-Type'=>'image/png','Cache-Control'=>'public,max-age=86400']);}
 public function launch(Request $r){if($r->session()->get('admin')===true)return redirect('/?provider='.(int)$r->session()->get('provider_id',1));return response()->view('app-launch')->header('Cache-Control','no-store');}
}
