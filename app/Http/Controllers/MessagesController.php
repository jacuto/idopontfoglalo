<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant,Clients};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Crypt};
use Illuminate\Support\Str;
final class MessagesController
{
 private function visible(array $m,array $clients,int $guest):bool{
  if((int)($m['guestId']??0)===$guest)return true;
  return !empty($m['clientId'])&&collect($clients)->contains(fn($c)=>$c['id']===$m['clientId']&&(int)($c['guestId']??0)===$guest&&Clients::visible($c));
 }
 public function recipients(Request $r){
  abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);$s=app(SalonStore::class)->read();$rows=Clients::search($s,(string)$r->query('q',''));return response()->json(array_map(function($c)use($s){$full=collect($s['clients'])->firstWhere('id',$c['id']);return $c+['guestId'=>empty($full['guestId'])?null:(int)$full['guestId'],'linked'=>!empty($full['guestId'])];},$rows))->header('Cache-Control','no-store');
 }
 public function handle(Request $r){
  $provider=$r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id;$guest=(int)$r->session()->get('guest_id',0);abort_unless($provider||$guest,401);
  if($r->isMethod('GET')){
   $s=app(SalonStore::class)->read();$deletedKey=$provider?'providerDeletedAt':'guestDeletedAt';
   $rows=array_values(array_filter($s['messages'],fn($m)=>empty($m[$deletedKey])&&($provider||$this->visible($m,$s['clients'],$guest))));
   $accounts=DB::table('guest_accounts')->whereIn('id',array_filter(array_column($rows,'guestId')))->get()->keyBy('id');
   foreach($rows as &$message){$client=collect($s['clients'])->firstWhere('id',$message['clientId']??'');$account=$accounts->get($message['guestId']??0);$message['senderName']=$message['sender']==='guest'?($account->profile_name??$account->name??$client['name']??'Vendég'):($s['settings']['name']??'Szolgáltató');$message['senderAvatar']=$message['sender']==='guest'?(!empty($account->avatar)?'/api/guest/avatar/'.$account->id.'?provider='.app(Tenant::class)->id:null):($s['profile']['logo']??null);}unset($message);
   return response()->json($rows)->header('Cache-Control','no-store');
  }
  if($r->isMethod('DELETE')){
   $v=$r->validate(['id'=>'required|string|max:80']);app(SalonStore::class)->mutate(function(&$s)use($v,$provider,$guest){foreach($s['messages'] as &$m)if($m['id']===$v['id']){abort_unless($provider||$this->visible($m,$s['clients'],$guest),404);$m[$provider?'providerDeletedAt':'guestDeletedAt']=now()->toISOString();return;}abort(404);});return response()->json(['ok'=>true]);
  }
  $v=$r->validate(['emailCopy'=>'sometimes|boolean','clientId'=>'sometimes|string|max:80','guestId'=>'sometimes|integer|min:1','text'=>$r->isMethod('POST')?'required|string|max:5000':'sometimes|string|max:5000']);
  if($r->isMethod('POST'))return response()->json(app(SalonStore::class)->mutate(function(&$s)use($v,$provider,$guest){
   if($provider)$client=collect($s['clients'])->first(fn($c)=>Clients::visible($c)&&(!empty($v['clientId'])?$c['id']===$v['clientId']:((int)($c['guestId']??0)===(int)($v['guestId']??0)&&!empty($v['guestId']))));
   else $client=collect($s['clients'])->first(fn($c)=>Clients::visible($c)&&(int)($c['guestId']??0)===$guest);
   abort_unless($client,404,'Nincs ehhez a vállalkozáshoz kapcsolt vendég.');$toGuest=empty($client['guestId'])?null:(int)$client['guestId'];if($provider&&!$toGuest)abort_unless(!empty($v['emailCopy'])&&!empty($client['email']),422,'Nem regisztrált vendégnél válaszd az e-mailes küldést vagy küldj meghívót.');
   $message=['id'=>(string)Str::uuid(),'clientId'=>$client['id'],'guestId'=>$toGuest,'sender'=>$provider?'provider':'guest','text'=>$v['text'],'createdAt'=>now()->toISOString(),'readAt'=>null,'emailCopy'=>$provider&&!$toGuest&&!empty($v['emailCopy'])];$s['messages'][]=$message;
   $email=$provider?($client['email']??''):($s['settings']['providerEmail']??'');if($email){$p=['kind'=>'message','messageId'=>$message['id'],'recipient'=>$email,'guest'=>$provider];DB::table('deliveries')->insertOrIgnore(['provider_id'=>app(Tenant::class)->id,'dedup'=>hash('sha256','message:'.$message['id']),'payload'=>Crypt::encryptString(json_encode($p)),'status'=>'pending','attempts'=>0,'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);}
   return $message;
  }),201);
  if($r->isMethod('PATCH')){app(SalonStore::class)->mutate(function(&$s)use($provider,$guest,$v){
   $client=null;if($provider){$client=collect($s['clients'])->first(fn($c)=>Clients::visible($c)&&(!empty($v['clientId'])?$c['id']===$v['clientId']:(!empty($v['guestId'])&&(int)($c['guestId']??0)===(int)$v['guestId'])));abort_unless($client,404);}
   foreach($s['messages'] as &$m){$selected=$provider?((!empty($m['clientId'])&&$m['clientId']===$client['id'])||(!empty($client['guestId'])&&(int)($m['guestId']??0)===(int)$client['guestId'])):$this->visible($m,$s['clients'],$guest);if($selected&&empty($m[$provider?'providerDeletedAt':'guestDeletedAt'])&&$m['sender']!==($provider?'provider':'guest')&&empty($m['readAt']))$m['readAt']=now()->toISOString();}unset($m);
  });return response()->json(['ok'=>true]);}
  abort(405);
 }
}
