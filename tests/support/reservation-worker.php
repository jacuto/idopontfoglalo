<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='mysql'||!str_ends_with(config('database.connections.mysql.database'),'_test'))exit(2);
app(\App\Services\Tenant::class)->id=77;
while(microtime(true)<(float)$argv[3])usleep(10000);
try {
 app(\App\Services\SalonStore::class)->mutate(function(&$s)use($argv){
  $b=['id'=>'race-'.$argv[2],'name'=>'Versenyteszt','phone'=>'123456','email'=>'','clientId'=>'race-client','guestId'=>null,'public'=>false,'services'=>['cut'],'staff'=>'anna','start'=>$argv[2]==='1'?'2035-03-12T09:00:00Z':'2035-03-12T10:00:00Z','status'=>'confirmed','serviceSnapshot'=>[$s['services'][0]]];
  app(\App\Services\BookingRules::class)->validate($b,$s);
  $b['pricing']=app(\App\Services\Offers::class)->quote($s,$b,$argv[1]==='coupon'?'RACE':null);
  app(\App\Services\Offers::class)->reservePass($s,$b,$argv[1]==='pass'?'race-pass':null,true);
  usleep(120000);$s['bookings'][]=$b;
 });echo 'OK';
}catch(InvalidArgumentException){echo 'UNAVAILABLE';}
