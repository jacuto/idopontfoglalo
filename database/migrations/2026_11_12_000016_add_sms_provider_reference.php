<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB,Crypt};
return new class extends Migration {
 public function up():void{
  if(!Schema::hasColumn('deliveries','provider_reference'))Schema::table('deliveries',fn(Blueprint $t)=>$t->string('provider_reference',100)->nullable());
  // Changing gateway must not release a backlog of old SMS when the new keys are entered.
  DB::table('deliveries')->whereIn('status',['pending','blocked','processing'])->orderBy('id')->chunkById(200,function($rows){foreach($rows as $row){
   try{$p=json_decode(Crypt::decryptString($row->payload),true);$sms=($p['channel']??'')==='sms';
    if(!$sms&&($p['kind']??'')==='guest_invite'){$invite=DB::table('guest_invitations')->find($p['invitationId']);if($invite)$sms=(json_decode(Crypt::decryptString($invite->payload),true)['channel']??'')==='sms';}
    if($sms)DB::table('deliveries')->where('id',$row->id)->where('status',$row->status)->update(['status'=>$row->status==='processing'?'unknown':'on_hold','locked_at'=>null,'error'=>'SMS-szolgáltató cseréje. Ellenőrzés nélkül nem küldjük újra a korábbi üzenetet.','updated_at'=>now()]);
   }catch(Throwable){} // Never destroy an unreadable, encrypted payload.
  }});
 }
 public function down():void{throw new RuntimeException('Adatmegőrző migráció: SMS-azonosítók automatikusan nem törölhetők.');}
};
