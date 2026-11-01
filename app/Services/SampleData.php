<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable as Time;
final class SampleData
{
 public function add():array {
  $safe=app()->environment(['local','testing']) && (config('database.default')==='sqlite' ? str_starts_with((string)config('database.connections.sqlite.database'),base_path('work/')) : str_ends_with((string)config('database.connections.mysql.database'),'_test'));
  if(!$safe)throw new \InvalidArgumentException('Mintaadat csak külön local/testing példány work/ alatti SQLite- vagy _test végű adatbázisába tölthető. Éles adatbázisba nem.');
  return app(SalonStore::class)->mutate(function(&$s){
   if(empty($s['services'])||empty($s['staff']))throw new \InvalidArgumentException('Előbb legyen legalább egy szolgáltatás és munkatárs a tesztpéldányban.');
   $service=$s['services'][0];$staff=$service['staff'][0];$added=0;
   for($i=0;$i<12;$i++){ $id='sample-client-'.$i;if(!collect($s['clients'])->contains('id',$id))$s['clients'][]=['id'=>$id,'name'=>'Minta Vendég '.($i+1),'phone'=>'+36 30 000 '.str_pad((string)$i,4,'0',STR_PAD_LEFT),'email'=>'minta'.$i.'@example.invalid','sample'=>true]; }
   for($day=0;$day<75;$day++){
    if($day>0&&$day%7===0)continue; // Zero-revenue days must remain visible in the chart.
    $id='sample-booking-'.$day;if(collect($s['bookings'])->contains('id',$id))continue;
    $client=$s['clients'][array_search('sample-client-'.($day%12),array_column($s['clients'],'id'))];
    $start=Time::now('Europe/Budapest')->subDays($day)->setTime(9,0);
    $service=$s['services'][$day%count($s['services'])];$staff=$service['staff'][0];
    if($day===0)$start=Time::now('Europe/Budapest')->subMinutes((int)$service['duration']+15);
    $price=(int)$service['price'];$b=['id'=>$id,'clientId'=>$client['id'],'guestId'=>null,'name'=>$client['name'],'phone'=>$client['phone'],'email'=>$client['email'],'start'=>$start->utc()->toISOString(),'staff'=>$staff,'services'=>[$service['id']],'serviceSnapshot'=>[$service],'status'=>$day>0&&$day%11===0?'cancelled':'confirmed','attendance'=>$day>0&&$day%11===0?'unmarked':($day>0&&$day%13===0?'no_show':'attended'),'token'=>bin2hex(random_bytes(24)),'createdAt'=>$start->utc()->toISOString(),'updatedAt'=>$start->utc()->toISOString(),'sample'=>true];
    $s['bookings'][]=$b;
    if($b['status']==='confirmed'&&$b['attendance']==='attended')$s['payments'][]=['id'=>'sample-payment-'.$day,'bookingId'=>$id,'amount'=>$price,'method'=>['cash','card','transfer'][$day%3],'paidAt'=>$start->addHours(2)->utc()->toISOString(),'status'=>'paid','source'=>'sample','createdAt'=>$start->utc()->toISOString(),'sample'=>true];
    $added++;
   }
   for($i=0;$i<3;$i++){
    $id='sample-pending-'.$i;if(collect($s['bookings'])->contains('id',$id))continue;
    $c=collect($s['clients'])->firstWhere('id','sample-client-'.$i);$start=Time::now('Europe/Budapest')->addDays($i+1)->setTime(10,15);
    $s['bookings'][]=['id'=>$id,'clientId'=>$c['id'],'guestId'=>null,'name'=>$c['name'],'phone'=>$c['phone'],'email'=>$c['email'],'start'=>$start->utc()->toISOString(),'staff'=>$staff,'services'=>[$service['id']],'serviceSnapshot'=>[$service],'status'=>'pending','token'=>bin2hex(random_bytes(24)),'createdAt'=>now()->toISOString(),'updatedAt'=>now()->toISOString(),'sample'=>true];$added++;
   }
   $s['sampleData']=true;
   return ['addedBookings'=>$added,'sampleClients'=>12];
  });
 }
}
