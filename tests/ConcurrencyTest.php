<?php
namespace Tests;
use App\Services\{SalonStore,Tenant};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{DB,Artisan};
use Symfony\Component\Process\Process;
class ConcurrencyTest extends TestCase {
 public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
 public function test_two_real_processes_cannot_spend_one_pass_visit():void {$this->race('pass');}
 public function test_two_real_processes_cannot_exceed_global_coupon_limit():void {$this->race('coupon');}
 private function race(string $kind):void {
  if(config('database.default')!=='mysql')$this->markTestSkipped('Valódi MariaDB/MySQL szükséges a többfolyamatos zárolás tesztjéhez.');
  $this->assertStringEndsWith('_test',config('database.connections.mysql.database'));Artisan::call('migrate',['--force'=>true]);
  $s=SalonStore::initial();$s['clients']=[['id'=>'race-client','name'=>'Versenyteszt','phone'=>'123456','email'=>'']];
  $s['clientPasses']=[['id'=>'race-pass','clientId'=>'race-client','services'=>['cut'],'remaining'=>1,'expiresAt'=>now()->addYears(15)->toISOString(),'reservations'=>[]]];
  $s['coupons']=[['id'=>'race-coupon','code'=>'RACE','name'=>'Utolsó kupon','active'=>true,'public'=>false,'firstVisit'=>false,'startsAt'=>now()->subDay()->toISOString(),'endsAt'=>now()->addDay()->toISOString(),'services'=>['cut'],'type'=>'fixed','value'=>1000,'limit'=>1,'perClient'=>1]];
  DB::table('salon_state')->updateOrInsert(['id'=>77],['data'=>json_encode($s,JSON_THROW_ON_ERROR)]);
  $start=microtime(true)+1;
  $processes=[];foreach([1,2] as $number){$p=new Process([PHP_BINARY,'-c',php_ini_loaded_file(),__DIR__.'/support/reservation-worker.php',$kind,(string)$number,(string)$start],base_path(),['APP_ENV'=>'testing','APP_KEY'=>config('app.key'),'DB_CONNECTION'=>'mysql','DB_DATABASE'=>config('database.connections.mysql.database'),'DB_HOST'=>config('database.connections.mysql.host'),'DB_PORT'=>(string)config('database.connections.mysql.port'),'DB_USERNAME'=>config('database.connections.mysql.username'),'DB_PASSWORD'=>config('database.connections.mysql.password')]);$p->setTimeout(15);$p->start();$processes[]=$p;}
  $results=[];foreach($processes as $p){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());$results[]=$p->getOutput();}sort($results);$this->assertSame(['OK','UNAVAILABLE'],$results);
  $state=app(Tenant::class)->run(77,fn()=>app(SalonStore::class)->read());$this->assertCount(1,$state['bookings']);if($kind==='pass')$this->assertSame(0,$state['clientPasses'][0]['remaining']);
  DB::table('salon_state')->where('id',77)->delete();
 }
 public function test_eight_parallel_requests_cannot_overbook_three_places():void{$this->capacityRace('parallel',3,8);}
 public function test_two_parallel_requests_cannot_take_the_last_group_seat():void{$this->capacityRace('group',1,2);}
 private function capacityRace(string $mode,int $limit,int $requests):void{
  if(config('database.default')!=='mysql')$this->markTestSkipped('Valódi MySQL/MariaDB szükséges.');$this->assertStringEndsWith('_test',config('database.connections.mysql.database'));Artisan::call('migrate',['--force'=>true]);
  $s=SalonStore::initial();$s['services'][0]['bookingMode']=$mode;$s['services'][0]['capacity']=$limit;$s['groupSessions']=$mode==='group'?[['id'=>'race-group','serviceId'=>'cut','staff'=>'anna','start'=>'2035-03-12T09:00:00Z','serviceSnapshot'=>$s['services'][0],'cancelled'=>false]]:[];DB::table('salon_state')->updateOrInsert(['id'=>78],['data'=>json_encode($s)]);
  $start=microtime(true)+1;$processes=[];for($i=0;$i<$requests;$i++){$p=new Process([PHP_BINARY,'-c',php_ini_loaded_file(),__DIR__.'/support/capacity-worker.php',$mode,(string)$i,(string)$start],base_path(),['APP_ENV'=>'testing','APP_KEY'=>config('app.key'),'DB_CONNECTION'=>'mysql','DB_DATABASE'=>config('database.connections.mysql.database'),'DB_HOST'=>config('database.connections.mysql.host'),'DB_PORT'=>(string)config('database.connections.mysql.port'),'DB_USERNAME'=>config('database.connections.mysql.username'),'DB_PASSWORD'=>config('database.connections.mysql.password')]);$p->setTimeout(20);$p->start();$processes[]=$p;}
  $results=[];foreach($processes as $p){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());$results[]=$p->getOutput();}$this->assertSame($limit,count(array_filter($results,fn($r)=>$r==='OK')));$this->assertSame($requests-$limit,count(array_filter($results,fn($r)=>$r==='UNAVAILABLE')));$state=app(Tenant::class)->run(78,fn()=>app(SalonStore::class)->read());$this->assertCount($limit,$state['bookings']);DB::table('salon_state')->where('id',78)->delete();
 }

}
