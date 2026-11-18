<?php
/** Standalone CLI fixture installer. It is intentionally not an Artisan command or HTTP route. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Hash,Schema};
use App\Services\{SalonStore,Settings,NotificationPreferences};
use Carbon\CarbonImmutable as Time;

$action=$argv[1]??'help';
if(!in_array($action,['create','status','remove'],true)){
 echo "Mintaadatok, a meglévő fiókok módosítása nélkül.\n";
 echo "  php tools/test-data.php create\n  php tools/test-data.php status\n  php tools/test-data.php remove --delete-test-accounts\n";exit(0);
}
$dir=storage_path('app/manual-test-data');
if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Nem írható a storage/app könyvtár.');
$lock=fopen($dir.'/lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Már fut egy mintaadat-művelet.');
$file=$dir.'/manifest.json';
function saveManifest(string $file,array $data):void {
 $tmp=$file.'.tmp';if(file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('A mintaadat-jegyzék nem menthető.');
 chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('A mintaadat-jegyzék nem véglegesíthető.');
}
function showManifest(array $m):void {
 echo "\nTESZT ADATOK – nem valódi ügyfelek vagy befizetések\n";
 foreach($m['providers'] as $p)echo $p['name']."\n  Szolgáltatói belépés: ".$m['url'].'/?provider='.$p['id']."\n  Foglalóoldal: ".$m['url'].'/foglalas?provider='.$p['id']."\n";
 echo "Szolgáltatói jelszó: ".$m['providerPassword']."\n";
 echo "Vendégbelépés: ".$m['url']."/fiokom\nVendégek például:\n";
 foreach(array_slice($m['guestEmails'],0,3) as $email)echo "  $email\n";
 echo "Vendégek jelszava: ".$m['guestPassword']."\n";
 echo "Szolgáltatónként: 120 vendég, 21 munkatárs, 31 szolgáltatás, 360 foglalás, 180 üzenet.\n";
 echo "A belépési adatok a storage/app/manual-test-data/manifest.json fájlban is megvannak.\n";
}
try {
 if($action==='status'){
  if(!is_file($file)){echo "Nincs ezzel a szkripttel létrehozott tesztcsomag.\n";exit(0);}
  $m=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);showManifest($m);exit(0);
 }
 if($action==='create'){
  if(is_file($file))throw new RuntimeException('Már van tesztcsomag. Belépési adatok: php tools/test-data.php status. Újrafuttatás nem duplikálja az adatokat.');
  foreach(['providers','salon_state','guest_accounts','guest_credentials'] as $table)if(!Schema::hasTable($table))throw new RuntimeException('Hiányzó adatbázistábla: '.$table.'. Előbb telepítsd/frissítsd a rendszert.');
  $run=bin2hex(random_bytes(6));$now=Time::now('Europe/Budapest');
  $m=['version'=>1,'run'=>$run,'url'=>rtrim(config('app.url'),'/'),'createdAt'=>$now->toISOString(),'providers'=>[],'guestIds'=>[],'guestEmails'=>[],'providerPassword'=>bin2hex(random_bytes(10)).'Aa!','guestPassword'=>bin2hex(random_bytes(10)).'Bb!'];
  $providerHash=Hash::make($m['providerPassword']);$guestHash=Hash::make($m['guestPassword']);
  DB::transaction(function()use(&$m,$file,$run,$now,$providerHash,$guestHash){
   $last=['Tóth','Nagy','Kovács','Szabó','Varga','Kiss','Molnár','Németh','Farkas','Horváth'];
   $first=['Anna','Tamás','Eszter','Gábor','Júlia','Péter','Dóra','Zoltán','Réka','Ádám','Éva','Bence'];
   $clients=[];
   for($i=0;$i<120;$i++){
    $name=$last[$i%10].' '.$first[intdiv($i,10)%12].' · TESZT '.($i+1);$email='teszt-'.$run.'-'.($i+1).'@example.invalid';
    $gid=DB::table('guest_accounts')->insertGetId(['provider'=>'email','subject'=>hash('sha256',$email),'name'=>$name,'profile_name'=>$name,'email'=>$email,'contact_email'=>$email,'phone'=>'','email_verified'=>true,'created_at'=>$now,'updated_at'=>$now]);
    DB::table('guest_credentials')->insert(['guest_id'=>$gid,'email'=>$email,'password_hash'=>$guestHash,'verified_at'=>$now,'version'=>1,'locale'=>'hu','created_at'=>$now,'updated_at'=>$now]);
    $m['guestIds'][]=$gid;$m['guestEmails'][]=$email;
    $clients[]=['id'=>'test-'.$run.'-client-'.$i,'guestId'=>$gid,'name'=>$name,'email'=>$email,'phone'=>'','sample'=>true,'notes'=>'Mesterséges tesztvendég; nincs valódi telefonszám.'];
   }
   foreach(['TESZT – Fodrászat','TESZT – Szépségszalon'] as $tenantIndex=>$name){
    $pid=DB::table('providers')->insertGetId(['name'=>$name,'email'=>'szalon-'.$tenantIndex.'-'.$run.'@example.invalid','password_hash'=>$providerHash,'active'=>true,'trial_started_at'=>$now,'trial_ends_at'=>$now->addYears(5),'created_at'=>$now,'updated_at'=>$now]);
    if($pid===1)throw new RuntimeException('Előbb telepítsd az elsődleges szolgáltatót; az 1-es azonosító fenntartott.');
    $m['providers'][]=['id'=>$pid,'name'=>$name];
    $s=Settings::upgrade(SalonStore::initial());$s['staff']=[];$s['services']=[];$s['clients']=$clients;$s['bookings']=[];$s['payments']=[];$s['messages']=[];
    $s['manualTestRun']=$run;$s['sampleData']=true;
    $s['settings']=array_replace($s['settings'],['name'=>$name,'profession'=>$tenantIndex?'Szépségszalon':'Fodrász','confirmation'=>true,'businessMode'=>'team','providerEmail'=>'','providerPhone'=>'','notifyGuest'=>false,'notifyProvider'=>false,'reminderGuest'=>false,'guestInvitationSmsEnabled'=>false,'guestInvitationPolicy'=>'none','reviewRequestsEnabled'=>false,'autoInvoice'=>false,'invoiceProvider'=>'none','prepaymentEnabled'=>false,'waitlistEnabled'=>false]);
    $rules=[];foreach(['guest','provider'] as $audience)foreach($audience==='guest'?[...NotificationPreferences::EVENTS,'reminder']:NotificationPreferences::EVENTS as $event)$rules[$audience][$event]=['email'=>false,'sms'=>false];$s['settings']['notificationRules']=$rules;
    // No live Stripe IDs, payment gateways, calendar links or SMS credits are copied.
    $s['subscription']=['accessMode'=>'free','plan'=>'pro','status'=>'free','calendarBlocked'=>false,'accessPeriods'=>[['mode'=>'free','from'=>$now->subYears(10)->toISOString(),'to'=>null]],'charges'=>[]];
    $s['profile']=['intro'=>'Mesterséges tesztszalon – ne foglalj valódi szolgáltatást!','description'=>'<p>Tesztadatok kereséshez, lapozáshoz, módosításhoz és törléshez.</p>','terms'=>'<p>Ez tesztadat; nincs valódi fizetési kötelezettség.</p>','acceptedPayments'=>['cash'],'categories'=>[$tenantIndex?'beauty':'hair'],'phone'=>'','email'=>'','photos'=>[]];
    for($i=0;$i<21;$i++)$s['staff'][]=['id'=>'test-staff-'.$i,'name'=>$last[$i%10].' '.$first[$i%12].' · TESZT '.($i+1),'role'=>$tenantIndex?'Kozmetikus':'Fodrász','email'=>'','phone'=>'','description'=>'Mesterséges munkatárs.','sample'=>true];
    $s['settings']['primaryStaffId']=$s['staff'][0]['id'];
    $titles=['Hajvágás','Hajfestés','Szárítás','Frizura','Szakálligazítás','Arckezelés','Manikűr','Pedikűr'];
    for($i=0;$i<31;$i++)$s['services'][]=['id'=>'test-service-'.$i,'name'=>$titles[$i%8].' · TESZT '.($i+1),'duration'=>30+($i%3)*15,'price'=>3000+$i*500,'staff'=>array_column($s['staff'],'id'),'description'=>'Mesterséges szolgáltatás a teszteléshez.','sample'=>true];
    for($i=0;$i<360;$i++){
     $c=$clients[$i%120];$service=$s['services'][$i%31];$start=$now->startOfDay()->addDays(intdiv($i,6)-45)->setTime(9+$i%6,0);$past=$start->isPast();$status=$past?['confirmed','confirmed','cancelled','no_show','confirmed','rejected'][$i%6]:['confirmed','pending','confirmed','cancelled'][$i%4];
     $b=['id'=>'test-booking-'.$i,'clientId'=>$c['id'],'guestId'=>$c['guestId'],'name'=>$c['name'],'phone'=>'','email'=>$c['email'],'staff'=>$s['staff'][$i%21]['id'],'services'=>[$service['id']],'serviceSnapshot'=>[$service],'start'=>$start->utc()->toISOString(),'status'=>$status,'attendance'=>$past&&$status==='confirmed'?'attended':'unmarked','public'=>true,'locale'=>'hu','token'=>bin2hex(random_bytes(24)),'createdAt'=>$start->subDays(3)->utc()->toISOString(),'updatedAt'=>$start->subDays(2)->utc()->toISOString(),'sample'=>true];
     $s['bookings'][]=$b;
     if($past&&$status==='confirmed'&&$i%5!==0)$s['payments'][]=['id'=>'test-payment-'.$i,'bookingId'=>$b['id'],'amount'=>$service['price'],'method'=>['cash','card','transfer'][intdiv($i,3)%3],'paidAt'=>$start->addHours(1)->utc()->toISOString(),'status'=>'paid','source'=>'sample','note'=>'TESZT – nem valódi bevétel','createdAt'=>$start->utc()->toISOString(),'sample'=>true];
    }
    for($i=0;$i<180;$i++){$c=$clients[$i<65?0:($i-64)%120];$at=$now->subMinutes(180-$i)->utc()->toISOString();$s['messages'][]=['id'=>'test-message-'.$i,'clientId'=>$c['id'],'guestId'=>$c['guestId'],'sender'=>$i%2?'provider':'guest','text'=>($i%2?'TESZT válasz: az időpont megfelelő.':'TESZT kérdés: módosíthatom az időpontomat?').' #'.($i+1),'createdAt'=>$at,'readAt'=>$i%3?$at:null,'emailCopy'=>false,'sample'=>true];}
    for($i=0;$i<3;$i++){
     $s['coupons'][]=['id'=>'test-coupon-'.$i,'name'=>'TESZT kedvezmény '.($i+1),'code'=>'TESZT'.($i+1),'type'=>'percent','value'=>10+$i*5,'startsAt'=>$now->subDay()->toISOString(),'endsAt'=>$now->addMonths(3)->toISOString(),'services'=>[],'active'=>true,'public'=>true,'firstVisit'=>false,'perClient'=>1,'limit'=>100];
     $s['passes'][]=['id'=>'test-pass-'.$i,'name'=>'TESZT bérlet '.($i+1),'price'=>15000+$i*5000,'visits'=>5+$i,'days'=>90,'services'=>[],'active'=>true];
    }
    DB::table('salon_state')->insert(['id'=>$pid,'data'=>json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   }
   // Written before commit, so IDs remain recoverable even if the process stops immediately afterwards.
   saveManifest($file,$m);
  });
  showManifest($m);echo "\nElkészült. Nem indult SMS, e-mail, fizetés vagy naptárszinkron.\n";exit(0);
 }
 if(!in_array('--delete-test-accounts',$argv,true))throw new RuntimeException('A teljes tesztfiókok törléséhez: php tools/test-data.php remove --delete-test-accounts');
 if(!is_file($file))throw new RuntimeException('Nincs eltávolítható tesztcsomag.');
 $m=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$ids=array_column($m['providers'],'id');
 DB::transaction(function()use($m,$ids){
  foreach($ids as $id){$row=DB::table('salon_state')->where('id',$id)->lockForUpdate()->first();if($id<=1||!$row)throw new RuntimeException('Hiányzó vagy védett szolgáltatói azonosító: automatikus törlés nem végezhető.');$s=json_decode($row->data,true,512,JSON_THROW_ON_ERROR);if(($s['manualTestRun']??'')!==$m['run'])throw new RuntimeException('Eltérő tesztazonosító: a törlés megszakadt.');}
  if(count($ids)!==2||count(array_unique($ids))!==2)throw new RuntimeException('Érvénytelen tesztjegyzék.');
  if(DB::table('guest_accounts')->whereIn('id',$m['guestIds'])->where('provider','email')->where('email','like','teszt-'.$m['run'].'-%@example.invalid')->count()!==count($m['guestIds']))throw new RuntimeException('Eltérő vendégazonosítók: a törlés megszakadt.');
  if(DB::table('platform_payments')->whereIn('provider_id',$ids)->exists())throw new RuntimeException('A tesztfiókhoz már valódi előfizetési befizetés tartozik. Automatikusan nem törölhető.');
  $businessSubjects=array_map(fn($id)=>'owner-'.$id,$ids);
  if(Schema::hasTable('provider_members'))foreach(DB::table('provider_members')->whereIn('provider_id',$ids)->pluck('id') as $member)$businessSubjects[]='member-'.$member;
  $businessGuests=DB::table('guest_accounts')->where('provider','business')->whereIn('subject',$businessSubjects)->pluck('id')->map(fn($id)=>(int)$id)->all();
  $allGuestIds=array_merge($m['guestIds'],$businessGuests);
  foreach(DB::table('salon_state')->whereNotIn('id',$ids)->cursor() as $row){$s=json_decode($row->data,true,512,JSON_THROW_ON_ERROR);foreach($s['clients']??[] as $c)if(in_array((int)($c['guestId']??0),$allGuestIds,true))throw new RuntimeException('Tesztvendég másik szalonhoz is kapcsolódik. A törlés megállt.');}
  // Only rows owned by the freshly created test providers can be removed.
  foreach(Schema::getTables() as $table){$name=$table['name'];if(Schema::hasColumn($name,'provider_id'))DB::table($name)->whereIn('provider_id',$ids)->delete();}
  DB::table('platform_messages')->where('recipient_type','provider')->whereIn('recipient_id',$ids)->delete();
  DB::table('platform_messages')->where('recipient_type','guest')->whereIn('recipient_id',$allGuestIds)->delete();
  $credentialIds=DB::table('guest_credentials')->whereIn('guest_id',$allGuestIds)->pluck('id');
  DB::table('guest_auth_tokens')->whereIn('credential_id',$credentialIds)->delete();
  DB::table('guest_credentials')->whereIn('guest_id',$allGuestIds)->delete();
  DB::table('guest_accounts')->whereIn('id',$m['guestIds'])->where('provider','email')->where('email','like','teszt-'.$m['run'].'-%@example.invalid')->delete();
  DB::table('guest_accounts')->where('provider','business')->whereIn('id',$businessGuests)->delete();
  DB::table('salon_state')->whereIn('id',$ids)->delete();DB::table('providers')->whereIn('id',$ids)->delete();
 });
 if(!rename($file,$dir.'/removed-'.$m['run'].'.json'))throw new RuntimeException('Törölve, de a jegyzék átnevezése nem sikerült.');
 echo "A szkript által létrehozott tesztfiókok és adataik törölve. A többi fiók változatlan.\n";
}catch(Throwable $e){fwrite(STDERR,"Hiba: ".$e->getMessage()."\n");exit(1);}
