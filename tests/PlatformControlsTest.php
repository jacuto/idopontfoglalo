<?php
namespace Tests;
use App\Services\{SalonStore,PlatformConnections,Connections,Subscriptions,PaidFeatures};
use Carbon\CarbonImmutable as Time;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Artisan,DB,Http};
final class PlatformControlsTest extends TestCase {
 private string $path;
 public function createApplication(){$a=require __DIR__.'/../bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $a;}
 protected function setUp():void{parent::setUp();if(config('database.default')!=='sqlite'||config('database.connections.sqlite.database')!==':memory:')throw new \RuntimeException('In-memory test database required');Artisan::call('migrate',['--force'=>true]);foreach(['salon_state','providers','platform_settings','platform_payments','platform_invoices','provider_security','provider_members'] as $t)DB::table($t)->delete();$this->path=storage_path('framework/control-test-'.bin2hex(random_bytes(5)));config(['operator.connections_path'=>$this->path,'integrations.path'=>$this->path.'-provider']);$s=SalonStore::initial();$s['subscription']=['plan'=>'basic','trialStartedAt'=>'2030-01-01T00:00:00Z','trialEndsAt'=>'2030-03-01T00:00:00Z','status'=>'active','calendarLimit'=>1];DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode($s)]);Time::setTestNow('2030-03-05T10:00:00Z');$this->withSession(['platform_admin'=>true,'_token'=>str_repeat('c',40)])->withHeader('X-CSRF-TOKEN',str_repeat('c',40));Http::preventStrayRequests();}
 protected function tearDown():void{foreach(glob($this->path.'*') as $p)if(is_file($p))unlink($p);Time::setTestNow();parent::tearDown();}
 private function payment():int{return DB::table('platform_payments')->insertGetId(['provider_id'=>1,'reference'=>'test-payment','amount'=>4000,'method'=>'transfer','paid_at'=>now(),'period_end'=>now()->addMonth(),'created_at'=>now()]);}
 public function test_social_switches_preserve_keys_and_calendar_and_cannot_be_bypassed():void{
  $keys=['google_client_id'=>'google-id','google_client_secret'=>'google-secret','facebook_client_id'=>'fb-id','facebook_client_secret'=>'fb-secret','apple_client_id'=>'apple-id','apple_team_id'=>'team','apple_key_id'=>'key','apple_private_key'=>'private'];app(PlatformConnections::class)->save($keys);
  $this->getJson('/api/auth/providers')->assertJsonPath('google',true)->assertJsonPath('facebook',true)->assertJsonPath('apple',true);
  $this->putJson('/api/platform/connections',['google_enabled'=>false,'facebook_enabled'=>false,'apple_enabled'=>false])->assertOk();
  $this->getJson('/api/platform/connections')->assertJsonPath('google_enabled',false)->assertJsonPath('google_client_secret_configured',true)->assertJsonMissingPath('google_client_secret');
  $this->getJson('/api/auth/providers')->assertJsonPath('google',false)->assertJsonPath('facebook',false)->assertJsonPath('apple',false);
  $this->get('/auth/google')->assertStatus(503);$this->get('/auth/facebook')->assertStatus(503);$this->get('/auth/apple')->assertStatus(503);$this->post('/auth/apple/callback',['state'=>'x'])->assertForbidden();
  $this->assertSame('google-secret',app(Connections::class)->all()['google_client_secret']);
  $this->putJson('/api/platform/connections',['google_enabled'=>true,'google_client_secret'=>''])->assertOk();$this->get('/auth/google')->assertRedirect();
  $this->withSession(['platform_admin'=>false,'admin'=>true]);$this->putJson('/api/platform/connections',['google_enabled'=>false])->assertUnauthorized();Http::assertNothingSent();
 }
 public function test_billingo_switch_does_not_disable_stripe_or_manual_invoice_records():void{
  config(['integrations.platform_stripe.key'=>'sk_test_only','integrations.platform_billingo.key'=>'billing-key','integrations.platform_billingo.block'=>1]);$id=$this->payment();
  $this->putJson('/api/platform/connections',['billingo_enabled'=>false])->assertOk();$this->postJson('/api/platform/invoices',['paymentId'=>$id])->assertUnprocessable();
  $this->assertSame('sk_test_only',config('integrations.platform_stripe.key'));$this->assertSame(0,DB::table('platform_invoices')->count());
  $this->putJson('/api/platform/invoices/manual',['paymentId'=>$id,'number'=>'MAN-1','issuedOn'=>'2030-03-05'])->assertOk();$this->assertSame('manual',DB::table('platform_invoices')->value('source'));
  app(SalonStore::class)->mutate(function(&$s){$s['settings']['invoiceProvider']='billingo';$s['settings']['autoInvoice']=true;});
  $this->assertSame(['issued'=>0,'waiting'=>0],app(\App\Services\AutomaticInvoices::class)->run());
  try{app(\App\Services\Invoices::class)->issue(['paymentId'=>'test']);$this->fail('Disabled provider Billingo must reject issuing');}catch(\InvalidArgumentException $e){$this->assertStringContainsString('ki van kapcsolva',$e->getMessage());}
  Http::assertNothingSent();
 }
 public function test_free_and_suspended_access_survive_refresh_without_back_billing():void{
  $id=$this->payment();$payment=DB::table('platform_payments')->find($id);
  $this->patchJson('/api/platform/providers/1/subscription',['mode'=>'free'])->assertOk();$s=app(SalonStore::class)->read();$this->assertSame('free',$s['subscription']['status']);$this->assertTrue(PaidFeatures::enabled($s));$this->assertSame(0,Subscriptions::monthlyAmount($s));
  $this->patchJson('/api/platform/providers/1/subscription',['mode'=>'free','calendarLimit'=>3])->assertOk();$this->assertSame(3,app(SalonStore::class)->read()['subscription']['calendarLimit']);
  Time::setTestNow('2031-04-05T10:00:00Z');$s=app(SalonStore::class)->read();$this->assertFalse($s['subscription']['calendarBlocked']);$this->assertCount(1,$s['subscription']['accessPeriods']);
  $this->patchJson('/api/platform/providers/1/subscription',['mode'=>'suspended'])->assertOk();$s=app(SalonStore::class)->read();$this->assertTrue($s['subscription']['calendarBlocked']);$this->assertFalse(PaidFeatures::enabled($s));$this->getJson('/api/slots?date=2031-04-10&services[]=cut')->assertStatus(423);
  Time::setTestNow('2031-05-05T10:00:00Z');$this->patchJson('/api/platform/providers/1/subscription',['mode'=>'active'])->assertOk();$s=app(SalonStore::class)->read();$this->assertSame('active',$s['subscription']['accessMode']);$this->assertSame('active',$s['subscription']['status']);
  $unpaid=collect($s['subscription']['charges'])->filter(fn($e)=>$e['kind']==='monthly'&&in_array($e['status'],['pending','overdue'],true));$this->assertCount(0,$unpaid);$this->assertEquals($payment,DB::table('platform_payments')->find($id));Http::assertNothingSent();
 }
 public function test_free_access_requires_resolving_existing_stripe_without_mutating_it():void{
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']['id']='sub_live';});config(['integrations.platform_stripe.key'=>'sk_test_only']);$remoteStatus='active';Http::fake(function()use(&$remoteStatus){return Http::response(['id'=>'sub_live','status'=>$remoteStatus]);});
  $this->patchJson('/api/platform/providers/1/subscription',['mode'=>'free'])->assertUnprocessable();$this->assertSame('active',app(SalonStore::class)->read()['subscription']['accessMode']??'active');Http::assertSentCount(1);Http::assertSent(fn($r)=>$r->method()==='GET');
  $remoteStatus='canceled';$this->patchJson('/api/platform/providers/1/subscription',['mode'=>'free'])->assertOk();Http::assertSent(fn($r)=>$r->method()==='GET');
 }
 public function test_public_documents_have_footer_and_no_private_configuration():void{
  DB::table('platform_settings')->insert(['id'=>1,'data'=>json_encode(['name'=>'Teszt Üzemeltető','taxNumber'=>'TEST-TAX','account'=>'PRIVATE-BANK','legal'=>['privacyEmail'=>'privacy@example.com','hostingName'=>'Test Host','hostingAddress'=>'Host Address','scope'=>'all']])]);
  foreach(['/adatvedelem','/aszf','/adattorles','/sugo'] as $url){$r=$this->get($url)->assertOk()->assertSee('href="/adattorles"',false)->assertSee('href="/gyik"',false);$r->assertDontSee('PRIVATE-BANK')->assertDontSee('platform_admin');}
  $this->get('/adatvedelem')->assertSee('Test Host')->assertSee('privacy@example.com');$this->get('/aszf')->assertSee('14 napon')->assertSee('magánszemély');$this->get('/sugo')->assertSee('35 perc hatóidő')->assertSee('95 perc');
 }
 public function test_legal_settings_are_private_escaped_and_preserved_when_operator_changes():void{
  $v=['privacyEmail'=>'privacy@example.com','hostingName'=>'<script>alert(1)</script>','hostingAddress'=>'Address','hostingPrivacyUrl'=>'https://example.com/privacy','scope'=>'all','retentionNotes'=>'Test retention'];
  $this->putJson('/api/platform/legal',$v)->assertOk();$this->get('/adatvedelem')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
  $operator=array_fill_keys(['name','country','zip','city','address','taxNumber','registrationNumber','bank','account','iban','bic'],'Test');$this->putJson('/api/platform/operator',$operator)->assertOk();$this->getJson('/api/platform/legal')->assertJsonPath('privacyEmail','privacy@example.com');
  $this->withSession(['platform_admin'=>false,'admin'=>true]);$this->getJson('/api/platform/legal')->assertUnauthorized();$this->putJson('/api/platform/legal',$v)->assertUnauthorized();$this->patchJson('/api/platform/providers/1/subscription',['mode'=>'free'])->assertUnauthorized();
 }
}
