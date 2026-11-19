<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Artisan,DB,Http};
use Carbon\CarbonImmutable as Time;
use App\Services\{SalonStore,Subscriptions,CalendarReductions,WebAddresses};
class November19Test extends TestCase
{
 public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
 protected function setUp():void{parent::setUp();if(config('database.default')!=='sqlite')throw new \RuntimeException('SQLite test only');Http::preventStrayRequests();Time::setTestNow(Time::parse('2036-05-05T08:00:00Z'));\Carbon\Carbon::setTestNow(Time::now());Artisan::call('migrate',['--force'=>true]);DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode(SalonStore::initial())]);$this->withSession(['admin'=>true,'provider_id'=>1]);}
 protected function tearDown():void{Time::setTestNow();\Carbon\Carbon::setTestNow();parent::tearDown();}
 private function booking(array $values=[]):array{return array_replace(['id'=>'one','name'=>'Teszt','phone'=>'06301234567','staff'=>'anna','services'=>['cut'],'serviceSnapshot'=>[['id'=>'cut','name'=>'Vágás','duration'=>45,'price'=>10000]],'start'=>now()->subHour()->toISOString(),'status'=>'confirmed','attendance'=>'attended','pricing'=>['original'=>10000,'discount'=>2000,'payable'=>8000]],$values);}
 public function test_automatic_payment_requires_opt_in_attendance_and_time_and_preserves_other_payments():void{
  $store=app(SalonStore::class);$store->mutate(function(&$s){$s['bookings']=[$this->booking()];});$this->assertEmpty($store->read()['payments']);
  $store->mutate(function(&$s){$s['settings']['paymentDefaults']=['automatic'=>true,'method'=>'transfer','note'=>'Alap'];$s['bookings'][0]['attendance']='unmarked';});$this->assertEmpty($store->read()['payments']);
  $store->mutate(function(&$s){$s['bookings'][0]['attendance']='attended';$s['bookings'][0]['start']=now()->addHour()->toISOString();});$this->assertEmpty($store->read()['payments']);
  $store->mutate(function(&$s){$s['bookings'][0]['start']=now()->subHour()->toISOString();$s['payments'][]=['id'=>'deposit','bookingId'=>'one','amount'=>3000,'status'=>'paid','method'=>'card','source'=>'stripe','paidAt'=>now()->toISOString()];});
  $payments=$store->read()['payments'];$this->assertCount(2,$payments);$this->assertSame(5000,$payments[1]['amount']);$this->assertSame('transfer',$payments[1]['method']);$store->mutate(fn(&$s)=>null);$this->assertCount(2,$store->read()['payments']);
  $store->mutate(function(&$s){$s['bookings'][0]['pricing']['payable']=7000;});$p=$store->read()['payments'][1];$this->assertSame(4000,$p['amount']);$this->assertCount(2,$p['history']);
  $store->mutate(function(&$s){$s['bookings']=[];});$this->assertSame('void',$store->read()['payments'][1]['status']);$this->assertSame('paid',$store->read()['payments'][0]['status']);
 }
 public function test_invoiced_automatic_payment_is_flagged_and_manual_correction_stops_recreation():void{
  $store=app(SalonStore::class);$store->mutate(function(&$s){$s['settings']['paymentDefaults']['automatic']=true;$s['bookings']=[$this->booking()];});$p=$store->read()['payments'][0];
  $store->mutate(function(&$s)use($p){$s['invoiceRequests'][]=['id'=>'inv','paymentId'=>$p['id'],'bookingId'=>'one','status'=>'issued'];$s['bookings'][0]['status']='cancelled';});$changed=$store->read()['payments'][0];$this->assertSame(8000,$changed['amount']);$this->assertSame('paid',$changed['status']);$this->assertTrue($changed['reconciliationRequired']);
  $store->mutate(function(&$s){$s['invoiceRequests']=[];});$this->assertSame('void',$store->read()['payments'][0]['status']);
  $store->mutate(function(&$s){$s['bookings'][0]['status']='confirmed';});$p=$store->read()['payments'][0];$this->deleteJson('/api/payments/'.$p['id'],['version'=>$p['version']])->assertOk();$store->mutate(fn(&$s)=>null);$this->assertSame('void',$store->read()['payments'][0]['status']);
 }
 public function test_correcting_manual_deposit_rebalances_only_flexible_automatic_payment():void{
  $store=app(SalonStore::class);$store->mutate(function(&$s){$s['settings']['paymentDefaults']['automatic']=true;$s['bookings']=[$this->booking()];$s['payments']=[['id'=>'manual-deposit','bookingId'=>'one','amount'=>3000,'status'=>'paid','source'=>'manual','method'=>'cash','paidAt'=>now()->toISOString()]];});
  $this->patchJson('/api/payments/manual-deposit',['version'=>0,'amount'=>4000,'method'=>'cash','paidAt'=>now()->toISOString()])->assertOk();$payments=$store->read()['payments'];$this->assertSame(4000,$payments[0]['amount']);$this->assertSame(4000,$payments[1]['amount']);
 }
 public function test_calendar_cancellation_lowers_only_next_period_and_preserves_bookings():void{
  $store=app(SalonStore::class);$end=now()->addDays(15)->toISOString();$store->mutate(function(&$s)use($end){$s['subscription']=['status'=>'active','plan'=>'pro','calendarLimit'=>2,'extraCalendars'=>1,'paidUntil'=>$end,'calendarAddons'=>[['quantity'=>1,'startsAt'=>now()->subMonth()->toISOString()]]];$s['bookings']=[$this->booking(['staff'=>'eszter','start'=>now()->addMonth()->toISOString()])];});
  $id='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';$r=$this->postJson('/api/subscription/calendars/cancel',['requestId'=>$id,'staffId'=>'eszter','confirmed'=>true])->assertOk()->assertJsonPath('status','scheduled')->assertJsonPath('newMonthlyAmount',6000);$s=$store->read();$this->assertSame(8000,Subscriptions::monthlyAmount($s));$this->assertSame(6000,Subscriptions::monthlyAmount($s,$end));$this->assertFalse($s['staff'][1]['archived']??false);
  $this->postJson('/api/subscription/calendars/cancel',['requestId'=>$id,'staffId'=>'eszter','confirmed'=>true])->assertOk();$this->assertCount(2,$store->read()['subscription']['calendarAddons']);
  Time::setTestNow(Time::parse($end)->addMinute());\Carbon\Carbon::setTestNow(Time::now());$s=$store->read();$this->assertTrue($s['staff'][1]['archived']);$this->assertSame(1,$s['subscription']['calendarLimit']);$this->assertSame(0,$s['subscription']['extraCalendars']);$this->assertCount(1,$s['bookings']);$this->assertSame(6000,Subscriptions::monthlyAmount($s));
 }
 public function test_stripe_reduction_is_verified_idempotent_and_without_proration():void{
  config(['integrations.platform_stripe.key'=>'sk_test']);$end=now()->addDays(20)->timestamp;$store=app(SalonStore::class);$store->mutate(function(&$s){$s['subscription']=['id'=>'sub_one','status'=>'active','plan'=>'pro','calendarLimit'=>2,'extraCalendars'=>1,'calendarAddons'=>[['quantity'=>1,'startsAt'=>now()->subMonth()->toISOString()]]];});
  $remote=['id'=>'sub_one','status'=>'active','current_period_end'=>$end,'items'=>['data'=>[['id'=>'base','price'=>['currency'=>'huf','unit_amount'=>600000,'product'=>'prod']],['id'=>'extra','quantity'=>1,'price'=>['currency'=>'huf','unit_amount'=>200000,'product'=>'prod']]]]];
  Http::fake(['api.stripe.com/v1/subscriptions/sub_one'=>fn($r)=>Http::response($r->method()==='GET'?$remote:array_replace($remote,['items'=>['data'=>[$remote['items']['data'][0]]]]))]);
  $this->postJson('/api/subscription/calendars/cancel',['requestId'=>'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','staffId'=>'eszter','confirmed'=>true])->assertOk();Http::assertSent(fn($r)=>$r->method()==='POST'&&$r['proration_behavior']==='none'&&$r['items'][0]['deleted']==='true');$this->assertSame(2,$store->read()['subscription']['calendarLimit']);
 }
 public function test_profile_accepts_bare_http_and_https_urls_and_rejects_other_social_domains():void{
  foreach(['24.hu'=>'https://24.hu','www.24.hu'=>'https://www.24.hu','http://24.hu'=>'http://24.hu'] as $in=>$out){$this->putJson('/api/profile',['website'=>$in])->assertOk();$this->assertSame($out,app(SalonStore::class)->read()['profile']['website']);}
  $this->putJson('/api/profile',['instagram'=>'www.instagram.com/teszt','facebook'=>'facebook.com/teszt'])->assertOk();
  foreach(['https://instagram.com.evil.example/test','facebook.com/test','javascript:alert(1)','https://instagram.com@evil.example'] as $in)$this->putJson('/api/profile',['instagram'=>$in])->assertUnprocessable();
 }
 public function test_waitlist_excludes_other_days_other_workers_closed_hours_and_available_parallel_capacity():void{
  $store=app(SalonStore::class);$day=now()->addDay()->toDateString();$start=Time::parse($day.' 10:00','Europe/Budapest')->utc()->toISOString();
  $store->mutate(function(&$s)use($start){$s['settings']['waitlistEnabled']=true;$s['services'][0]['staff']=['anna','eszter'];$s['bookings']=[$this->booking(['start'=>Time::parse($start)->addDay()->toISOString()])];});
  $query=fn($staff)=>'/api/waitlist/availability?'.http_build_query(['date'=>$day,'services'=>'cut','staff'=>$staff]);$this->getJson($query('anna'))->assertOk()->assertJsonPath('eligible',false);
  $store->mutate(function(&$s)use($start){$s['bookings'][0]['start']=$start;$s['bookings'][0]['staff']='eszter';});$this->getJson($query('anna'))->assertJsonPath('eligible',false);$this->getJson($query('any'))->assertJsonPath('eligible',false);$this->getJson($query('eszter'))->assertJsonPath('eligible',true);
  $store->mutate(function(&$s)use($day){$s['availability']['overrides']=[['date'=>$day,'staffId'=>'eszter','closed'=>true,'openingTime'=>'08:00','closingTime'=>'20:00']];});$this->getJson($query('eszter'))->assertJsonPath('eligible',false);
  $store->mutate(function(&$s)use($start){$s['availability']['overrides']=[];$s['bookings'][0]['staff']='anna';$s['bookings'][0]['start']=$start;$s['services'][0]['bookingMode']='parallel';$s['services'][0]['capacity']=2;$s['bookings'][0]['serviceSnapshot'][0]['bookingMode']='parallel';$s['bookings'][0]['serviceSnapshot'][0]['capacity']=2;});$this->getJson($query('anna'))->assertJsonPath('eligible',false);
  $store->mutate(function(&$s){$other=$s['bookings'][0];$other['id']='second';$s['bookings'][]=$other;});$this->getJson($query('anna'))->assertJsonPath('eligible',true);
 }
 public function test_waitlist_is_only_useful_when_booking_removes_otherwise_bookable_start():void{
  $store=app(SalonStore::class);$day=now()->addDay()->toDateString();$store->mutate(function(&$s){$s['settings']['waitlistEnabled']=true;});$query='/api/waitlist/availability?'.http_build_query(['date'=>$day,'services'=>'cut','staff'=>'anna']);
  $this->getJson($query)->assertOk()->assertJsonPath('eligible',false);
  $store->mutate(function(&$s)use($day){$s['bookings']=[$this->booking(['start'=>Time::parse($day.' 10:00','Europe/Budapest')->utc()->toISOString()])];});$this->getJson($query)->assertOk()->assertJsonPath('eligible',true)->assertJsonMissingPath('bookings');
  $store->mutate(function(&$s){$s['bookings'][0]['status']='cancelled';});$this->getJson($query)->assertJsonPath('eligible',false);
  $store->mutate(function(&$s){$s['settings']['waitlistEnabled']=false;});$this->getJson($query)->assertJsonPath('eligible',false);
 }
}
