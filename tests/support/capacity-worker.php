<?php
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='mysql'||!str_ends_with(config('database.connections.mysql.database'),'_test'))exit(2);
app(\App\Services\Tenant::class)->id=78;while(microtime(true)<(float)$argv[3])usleep(10000);
try{app(\App\Services\SalonStore::class)->mutate(function(&$s)use($argv){$b=['id'=>'seat-'.$argv[2],'name'=>'Versenyteszt','phone'=>'06301234567','email'=>'','services'=>['cut'],'staff'=>'anna','start'=>'2035-03-12T09:00:00Z','status'=>'confirmed','public'=>false,'guestId'=>null,'serviceSnapshot'=>[$s['services'][0]]];\App\Services\GroupSessions::prepare($b,$s);app(\App\Services\BookingRules::class)->validate($b,$s);usleep(30000);$s['bookings'][]=$b;});echo 'OK';}catch(InvalidArgumentException){echo 'UNAVAILABLE';}
