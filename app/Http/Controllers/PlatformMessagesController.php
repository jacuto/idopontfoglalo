<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Crypt};
final class PlatformMessagesController {
 public function handle(Request $r){
  $platform=$r->session()->get('platform_admin')===true;
  $type=$r->session()->get('admin')===true?'provider':'guest';$id=$type==='provider'?(int)$r->session()->get('provider_id',1):(int)$r->session()->get('guest_id',0);
  abort_unless($platform||$id,401);
  if($r->isMethod('GET')){
   $q=DB::table('platform_messages')->orderBy('id');if(!$platform)$q->where('recipient_type',$type)->where('recipient_id',$id);
   return response()->json($q->get());
  }
  if($platform){$v=$r->validate(['recipientType'=>'required|in:provider,guest','recipientId'=>'required|integer|min:1','text'=>'required|string|max:5000']);$type=$v['recipientType'];$id=$v['recipientId'];}
  else $v=$r->validate(['text'=>'required|string|max:5000']);
  if($r->isMethod('PATCH')){abort(405);}
  abort_unless($r->isMethod('POST'),405);
  if($type==='guest'){$account=DB::table('guest_accounts')->find($id);abort_unless($account,404);$email=$account->email;}
  else {abort_unless(DB::table('salon_state')->where('id',$id)->exists(),404);$email=app(Tenant::class)->run($id,fn()=>app(SalonStore::class)->read()['settings']['providerEmail']);}
  $message=DB::transaction(function()use($platform,$type,$id,$v,$email){
   $messageId=DB::table('platform_messages')->insertGetId(['recipient_type'=>$type,'recipient_id'=>$id,'sender'=>$platform?'platform':'recipient','text'=>$v['text'],'created_at'=>now(),'updated_at'=>now()]);
   $to=$platform?$email:config('newsletter.from');
   if($to)DB::table('deliveries')->insert(['provider_id'=>0,'dedup'=>hash('sha256','platform-message:'.$messageId),'payload'=>Crypt::encryptString(json_encode(['kind'=>'platform_message','messageId'=>$messageId,'recipient'=>$to,'platformRecipient'=>!$platform,'recipientType'=>$type,'recipientId'=>$id])),'status'=>'pending','available_at'=>now(),'attempts'=>0,'created_at'=>now(),'updated_at'=>now()]);
   return ['id'=>$messageId,'ok'=>true];
  });return response()->json($message,201);
 }
 public function read(Request $r){
  $platform=$r->session()->get('platform_admin')===true;$type=$r->session()->get('admin')===true?'provider':'guest';$id=$type==='provider'?(int)$r->session()->get('provider_id',1):(int)$r->session()->get('guest_id',0);abort_unless($platform||$id,401);
  $q=DB::table('platform_messages')->whereNull('read_at')->where('sender',$platform?'recipient':'platform');if(!$platform)$q->where('recipient_type',$type)->where('recipient_id',$id);
  $q->update(['read_at'=>now()]);return response()->json(['ok'=>true]);
 }
}
