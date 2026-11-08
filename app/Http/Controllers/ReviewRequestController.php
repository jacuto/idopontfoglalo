<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant,ReviewRequests};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
final class ReviewRequestController{
 public function request(Request $r,string $id){abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);return response()->json(app(SalonStore::class)->mutate(function(&$s)use($id){$b=collect($s['bookings'])->firstWhere('id',$id);abort_unless($b,404);abort_unless(ReviewRequests::eligible($s,$b),422,'Értékelést csak igazolt látogatás után kérhetsz.');abort_unless(!empty($b['email']),422,'Ehhez a vendéghez nincs e-mail-cím.');$queued=ReviewRequests::queue($s,$b);return ['queued'=>$queued];}));}
 public function review(Request $r){$v=$r->validate(['token'=>'required|string|regex:/^[a-f0-9]{48}$/','score'=>'required|integer|between:1,5','text'=>'required|string|max:3000']);$review=app(SalonStore::class)->mutate(function(&$s)use($v){$b=collect($s['bookings'])->firstWhere('token',$v['token']);abort_unless($b,404);abort_unless(ReviewRequests::eligible($s,$b),422,'Erre a látogatásra most nem írható értékelés.');$row=['id'=>(string)Str::uuid(),'bookingId'=>$b['id'],'clientId'=>$b['clientId'],'direction'=>'guest','score'=>$v['score'],'text'=>$v['text'],'createdAt'=>now()->toISOString()];$s['reviews'][]=$row;return ['ok'=>true];});return response()->json($review,201);}
}
