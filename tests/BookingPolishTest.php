<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Artisan,DB,Http};
use App\Services\{SalonStore,Tenant,GuestBookingLinks,SubscriptionInvoiceItems};
use Carbon\CarbonImmutable as Time;
class BookingPolishTest extends TestCase
{
 public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
 protected function setUp():void{parent::setUp();if(config('database.default')!=='sqlite')throw new \RuntimeException('Test SQLite only');Artisan::call('migrate',['--force'=>true]);DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode(SalonStore::initial())]);$this->withSession(['admin'=>true,'provider_id'=>1]);Time::setTestNow('2036-05-05T08:00:00Z');Http::preventStrayRequests();}
 protected function tearDown():void{Time::setTestNow();parent::tearDown();}
 public function test_verified_login_links_matching_unclaimed_bookings_but_not_other_accounts_or_phone_matches():void{
  $store=app(SalonStore::class);$store->mutate(function(&$s){$s['clients']=[['id'=>'match','name'=>'One','email'=>'guest@example.com','phone'=>'06301234567'],['id'=>'other-owner','name'=>'Two','email'=>'guest@example.com','phone'=>'','guestId'=>22],['id'=>'phone','name'=>'Three','email'=>'other@example.com','phone'=>'06301234567']];$s['bookings']=[['id'=>'one','token'=>str_repeat('a',48),'clientId'=>'match','email'=>'GUEST@example.com','staff'=>'anna','services'=>['cut'],'serviceSnapshot'=>[['id'=>'cut','duration'=>45,'price'=>8500]],'name'=>'One','phone'=>'','start'=>now()->addDay()->toISOString(),'status'=>'pending'],['id'=>'two','clientId'=>'other-owner','email'=>'guest@example.com','guestId'=>22,'staff'=>'anna','services'=>['cut'],'serviceSnapshot'=>[['id'=>'cut','duration'=>45,'price'=>8500]],'start'=>now()->addDays(2)->toISOString(),'status'=>'pending']];});
  DB::table('guest_accounts')->insert(['id'=>10,'provider'=>'email','subject'=>'ten','email'=>'guest@example.com','email_verified'=>false,'name'=>'One','created_at'=>now(),'updated_at'=>now()]);$this->withSession(['admin'=>false,'guest_id'=>10]);$this->getJson('/api/guest')->assertOk()->assertJsonCount(0,'bookings');DB::table('guest_accounts')->where('id',10)->update(['email_verified'=>true]);$this->getJson('/api/guest')->assertOk()->assertJsonCount(1,'bookings');$s=$store->read();$this->assertSame(10,$s['clients'][0]['guestId']);$this->assertSame(22,$s['bookings'][1]['guestId']);$this->assertArrayNotHasKey('guestId',$s['clients'][2]);
  $this->getJson('/api/guest')->assertJsonCount(1,'bookings');
 }
 public function test_public_hours_follow_calendar_overrides_and_hiding_does_not_change_slots():void{
  $day=Time::now('Europe/Budapest')->startOfWeek()->toDateString();app(SalonStore::class)->mutate(function(&$s)use($day){$s['availability']['overrides']=[['date'=>$day,'closed'=>true,'openingTime'=>'08:00','closingTime'=>'20:00']];});$this->getJson('/api/public')->assertOk()->assertJsonCount(7,'openingHours')->assertJsonPath('openingHours.0.hours','Zárva')->assertJsonPath('openingHours.1.hours','08:00–20:00');
  $this->putJson('/api/profile',['showOpeningHours'=>'0','hours'=>'Csak bejelentkezéssel'])->assertOk();$this->getJson('/api/public')->assertJsonCount(0,'openingHours')->assertJsonPath('profile.hours','Csak bejelentkezéssel');$this->assertSame('08:00',app(SalonStore::class)->read()['settings']['openingTime']);
 }
 public function test_dormant_prepayment_can_be_saved_and_reports_not_operational():void{
  $s=app(SalonStore::class)->read();$this->putJson('/api/config',['settings'=>array_replace($s['settings'],['prepaymentEnabled'=>true,'prepaymentMode'=>'deposit','prepaymentMethods'=>[]])])->assertOk();$this->getJson('/api/state')->assertJsonPath('prepaymentSetup.enabled',true)->assertJsonPath('prepaymentSetup.ready',false);$this->getJson('/api/public')->assertJsonPath('prepaymentAvailable',false);
 }
 public function test_billingo_document_receives_separate_plan_and_calendar_lines():void{
  config(['integrations.platform_billingo.key'=>'fake-test-key','integrations.platform_billingo.block'=>1]);app(SalonStore::class)->mutate(function(&$s){$s['settings']=array_replace($s['settings'],['billingName'=>'Teszt','billingZip'=>'1111','billingCity'=>'Budapest','billingAddress'=>'Minta 1.']);});
  $state=app(SalonStore::class)->read();$state['subscription']=['plan'=>'pro','calendarAddons'=>[['quantity'=>2,'startsAt'=>'2036-05-01T00:00:00Z']]];$items=SubscriptionInvoiceItems::snapshot($state,'2036-05-05T00:00:00Z',10000);
  $id=DB::table('platform_payments')->insertGetId(['provider_id'=>1,'amount'=>10000,'reference'=>'test-lines','method'=>'transfer','paid_at'=>now(),'period_end'=>now()->addMonth(),'details'=>json_encode(['invoiceItems'=>$items]),'created_at'=>now(),'updated_at'=>now()]);
  Http::fake(['api.billingo.hu/v3/partners'=>Http::response(['id'=>77]),'api.billingo.hu/v3/documents'=>Http::response(['id'=>88,'invoice_number'=>'TEST-88'])]);$result=app(\App\Services\PlatformInvoices::class)->issue($id);$this->assertSame('issued',$result['status']);Http::assertSent(fn($r)=>str_ends_with($r->url(),'/documents')&&count($r['items'])===2&&$r['items'][0]['unit_price']===6000&&$r['items'][1]['quantity']===2&&$r['items'][1]['unit_price']===2000);
 }
 public function test_subscription_invoice_keeps_base_and_calendar_lines_from_payment_snapshot():void{
  $s=app(SalonStore::class)->read();$s['subscription']=['plan'=>'pro','calendarAddons'=>[['quantity'=>2,'startsAt'=>'2036-05-01T00:00:00Z']]];$items=SubscriptionInvoiceItems::snapshot($s,'2036-05-05T00:00:00Z',10000);$this->assertCount(2,$items);$this->assertSame(6000,$items[0]['unit_price']);$this->assertSame(2,$items[1]['quantity']);$this->assertSame(2000,$items[1]['unit_price']);
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']['plan']='basic';});$payment=(object)['id'=>1,'provider_id'=>1,'amount'=>10000,'details'=>json_encode(['invoiceItems'=>$items])];$this->assertSame($items,SubscriptionInvoiceItems::forPayment($payment));$legacy=(object)['provider_id'=>1,'amount'=>10000,'details'=>'{}'];$this->assertCount(1,SubscriptionInvoiceItems::forPayment($legacy));
 }
}
