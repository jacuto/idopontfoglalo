<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant,StaffAccess,Clients,Notifications,PaidFeatures};
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
final class ReminderPreferencesController
{
 public function save(Request $r,?string $id=null){
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r)&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);
  $v=$r->validate(['preference'=>'required_without:reset|in:inherit,on,off','reset'=>'sometimes|accepted']);
  app(SalonStore::class)->mutate(function(&$s)use($v,$id){
   if(!empty($v['reset'])){foreach($s['clients'] as &$c)unset($c['reminderPreference']);unset($c);}
   else{if($v['preference']==='on')PaidFeatures::require($s);$found=false;foreach($s['clients'] as &$c)if($c['id']===$id&&Clients::visible($c)){$c['reminderPreference']=$v['preference'];$found=true;break;}unset($c);abort_unless($found,404);}
   $s['settings']['notificationVersion']=($s['settings']['notificationVersion']??1)+1;
   foreach($s['bookings'] as $b)if($b['status']==='confirmed'&&CarbonImmutable::parse($b['start'])->isFuture())app(Notifications::class)->enqueue($b,$s,'reminder');
  });
  $warnings=[];$state=app(SalonStore::class)->read();
  if(($v['preference']??'')==='on'){
   $client=collect($state['clients'])->firstWhere('id',$id);
   $channel=$state['settings']['channel']??'email';
   if(in_array($channel,['email','mindkettő'],true)&&empty($client['email']))$warnings[]='A vendéghez még nincs megadva e-mail-cím.';
   if(in_array($channel,['sms','mindkettő'],true)&&empty($client['phone']))$warnings[]='A vendéghez még nincs megadva telefonszám.';$connections=app(\App\Services\Connections::class);
   if(in_array($channel,['email','mindkettő'],true)&&!$connections->mailReady())$warnings[]='Az e-mailes emlékeztetéshez még nincs működő levelezőkapcsolat.';
   if(in_array($channel,['sms','mindkettő'],true)){
    if(!\App\Services\BulkGateSms::ready($connections->all()))$warnings[]='Az SMS-küldő kapcsolat még nincs beállítva.';
    if(\App\Services\SmsWallet::info(app(Tenant::class)->id)['available']<\App\Services\SmsWallet::price())$warnings[]='Az SMS-egyenleg nem elegendő az emlékeztetőhöz.';
   }
  }
  return response()->json(['ok'=>true,'warnings'=>$warnings]);
 }
}
