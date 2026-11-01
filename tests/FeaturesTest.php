<?php
namespace Tests;
use App\Services\{SalonStore, Tenant};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Artisan, DB, Hash, Storage};
use Illuminate\Http\UploadedFile;
class FeaturesTest extends TestCase {
 public function createApplication(){ $app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app; }
 protected function setUp():void {
  parent::setUp();if(config('database.default')!=='sqlite'&&!str_ends_with((string)config('database.connections.mysql.database'),'_test'))throw new \RuntimeException('Csak külön tesztadatbázis használható.');config(['integrations.path'=>storage_path('framework/features-connections.enc')]);foreach(glob(storage_path('framework/features-connections.enc*')) as $file)unlink($file);Artisan::call('migrate',['--force'=>true]);foreach(['salon_state','providers','guest_accounts','deliveries','platform_payments','platform_settings','newsletter_consents','newsletters','newsletter_deliveries','platform_invoices','platform_messages'] as $table)DB::table($table)->delete();
  DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode(SalonStore::initial())]);
  DB::table('salon_state')->insert(['id'=>2,'data'=>json_encode(SalonStore::initial())]);
  DB::table('providers')->insert(['id'=>2,'name'=>'Másik','password_hash'=>Hash::make('test-password-123'),'active'=>true,'trial_started_at'=>now(),'trial_ends_at'=>now()->addMonths(2),'created_at'=>now(),'updated_at'=>now()]);
 }
 private function provider(int $id=1):void {$this->withSession(['admin'=>true,'provider_id'=>$id,'_token'=>str_repeat('x',40)])->withHeader('X-CSRF-TOKEN',str_repeat('x',40));}
 private function booking(array $v=[]):array {return array_replace(['name'=>'Vendég','phone'=>'+36 30 123-4567','email'=>'guest@example.com','services'=>['cut'],'staff'=>'anna','start'=>'2035-03-12T09:00:00Z'],$v);}
 private function guest(int $id=10):void {
  DB::table('guest_accounts')->insertOrIgnore(['id'=>$id,'name'=>'Vendég','email'=>'guest@example.com','email_verified'=>true,'provider'=>'google','subject'=>(string)$id,'created_at'=>now(),'updated_at'=>now()]);
  $this->withSession(['guest_id'=>$id,'admin'=>false]);
 }
 public function test_guest_account_loads_without_avatar_column_during_upgrade():void {
  $this->guest();
  \Illuminate\Support\Facades\Schema::table('guest_accounts', fn($table) => $table->dropColumn('avatar'));
  try {
   $this->getJson('/api/guest')->assertOk()->assertJsonPath('id',10)->assertJsonPath('avatar',null)->assertJsonCount(0,'bookings');
  } finally {
   \Illuminate\Support\Facades\Schema::table('guest_accounts', fn($table) => $table->string('avatar')->nullable());
  }
 }
 public function test_imported_client_search_is_normalized_and_scoped():void {
  $this->provider();$this->postJson('/api/imports/clients',['records'=>[['id'=>'imported','name'=>'Kiss Anna','phone'=>'+36 30 123-4567','email'=>'anna@example.com']]])->assertOk();
  $this->getJson('/api/clients/search?q=12')->assertExactJson([]);
  $this->getJson('/api/clients/search?q=30123')->assertOk()->assertJsonPath('0.id','imported');
  $this->getJson('/api/clients/search?provider=2&q=Anna')->assertForbidden();
  $this->getJson('/api/clients/imported/detail?provider=2')->assertForbidden();
  $this->provider(2);$this->getJson('/api/clients/search?q=Anna')->assertExactJson([]);
  $this->getJson('/api/clients/imported/detail')->assertNotFound();
 }
 public function test_selected_client_is_reused_without_creating_duplicate():void {
  $this->provider();app(SalonStore::class)->mutate(function(&$s){$s['clients'][]=['id'=>'one','name'=>'Régi vendég','phone'=>'12345','email'=>''];});
  $this->postJson('/api/bookings',$this->booking(['clientId'=>'one']))->assertCreated()->assertJsonPath('clientId','one')->assertJsonPath('name','Régi vendég');
  $this->assertCount(1,app(SalonStore::class)->read()['clients']);
 }
 public function test_guest_never_claims_bookings_by_email_or_client_id():void {
  $b=$this->postJson('/api/bookings',$this->booking())->assertCreated()->json();$this->guest();
  $this->getJson('/api/guest')->assertJsonCount(0,'bookings');
  $this->postJson('/api/bookings',$this->booking(['clientId'=>$b['clientId'],'start'=>'2035-03-13T09:00:00Z']))->assertCreated();
  $this->getJson('/api/guest')->assertJsonCount(1,'bookings');
  $this->assertCount(2,app(SalonStore::class)->read()['clients']);
 }
 public function test_expired_pending_has_actionable_error_and_never_attends_automatically():void {
  $b=$this->postJson('/api/bookings',$this->booking())->json();
  app(SalonStore::class)->mutate(function(&$s){$s['bookings'][0]['start']='2020-03-12T09:00:00Z';});$this->provider();
  $this->patchJson('/api/bookings/'.$b['id'],['status'=>'confirmed'])->assertUnprocessable()->assertJsonPath('error','Ez a függő időpont már elmúlt. Előbb adj meg új, jövőbeli időpontot, vagy válaszd az Elutasítás lehetőséget.');
  $this->getJson('/api/clients/'.$b['clientId'].'/detail')->assertJsonPath('stats.attended',0);
  $this->patchJson('/api/bookings/'.$b['id'],['attendance'=>'attended'])->assertUnprocessable();
  $this->patchJson('/api/bookings/'.$b['id'],['status'=>'rejected'])->assertOk();
 }
 public function test_reviews_require_attendance_and_are_unique_and_private_by_direction():void {
  $this->guest();$b=$this->postJson('/api/bookings',$this->booking())->json();
  $this->postJson('/api/guest/bookings/'.$b['id'].'/review',['score'=>5,'text'=>'Jó'])->assertUnprocessable();
  $this->provider();$this->patchJson('/api/bookings/'.$b['id'],['status'=>'confirmed'])->assertOk();
  app(SalonStore::class)->mutate(function(&$s){$s['bookings'][0]['start']='2020-03-12T09:00:00Z';});
  $this->patchJson('/api/bookings/'.$b['id'],['attendance'=>'attended'])->assertOk();
  $this->postJson('/api/bookings/'.$b['id'].'/review',['score'=>4,'text'=>'Belső vendégértékelés'])->assertCreated();
  $this->withSession(['admin'=>false,'guest_id'=>10]);
  $this->postJson('/api/guest/bookings/'.$b['id'].'/review',['score'=>5,'text'=>'Kiváló szolgáltató'])->assertCreated();
  $this->postJson('/api/guest/bookings/'.$b['id'].'/review',['score'=>1,'text'=>'Második'])->assertUnprocessable();
  $this->getJson('/api/public')->assertJsonCount(1,'reviews')->assertJsonPath('reviews.0.text','Kiváló szolgáltató')->assertJsonMissing(['text'=>'Belső vendégértékelés']);
 }
 private function coupon():array {return ['code'=>'ELSO','name'=>'Első','startsAt'=>now()->subDay()->toISOString(),'endsAt'=>now()->addDays(10)->toISOString(),'services'=>['cut'],'type'=>'percent','value'=>100,'active'=>true,'public'=>true,'firstVisit'=>true,'perClient'=>1,'limit'=>1];}
 public function test_coupon_is_server_priced_limited_and_snapshot_is_immutable():void {
  $this->provider();$c=$this->postJson('/api/coupons',$this->coupon())->assertCreated()->json();$this->guest();
  $b=$this->postJson('/api/bookings',$this->booking(['coupon'=>'ELSO','pricing'=>['payable'=>1]]))->assertCreated()->assertJsonPath('pricing.payable',0)->json();
  $this->postJson('/api/bookings',$this->booking(['coupon'=>'ELSO','start'=>'2035-03-13T09:00:00Z']))->assertUnprocessable();
  $this->provider();$this->postJson('/api/coupons',array_replace($c,['value'=>10]))->assertCreated();
  $this->assertSame(0,app(SalonStore::class)->read()['bookings'][0]['pricing']['payable']);
  $this->postJson('/api/coupons',array_replace($c,['code'=>'LEJART','id'=>null,'startsAt'=>'2020-01-01','endsAt'=>'2020-01-02']))->assertCreated();
  $this->postJson('/api/quote',['services'=>['cut'],'coupon'=>'LEJART'])->assertUnprocessable();
 }
 public function test_pass_reservation_cannot_be_double_spent_and_cancel_refunds_once():void {
  $this->provider();app(SalonStore::class)->mutate(function(&$s){$s['clients'][]=['id'=>'one','name'=>'Vendég','phone'=>'12345','email'=>''];});
  $p=$this->postJson('/api/passes',['name'=>'Bérlet','price'=>8000,'visits'=>1,'days'=>3650,'services'=>['cut'],'active'=>true])->assertCreated()->json();
  $pass=$this->postJson('/api/client-passes',['clientId'=>'one','passId'=>$p['id']])->assertCreated()->json();
  $b=$this->postJson('/api/bookings',$this->booking(['clientId'=>'one','passId'=>$pass['id']]))->assertCreated()->assertJsonPath('pricing.payable',0)->json();
  $this->postJson('/api/bookings',$this->booking(['clientId'=>'one','passId'=>$pass['id'],'start'=>'2035-03-13T09:00:00Z']))->assertUnprocessable();
  $this->patchJson('/api/bookings/'.$b['id'],['status'=>'cancelled'])->assertOk();
  $this->patchJson('/api/bookings/'.$b['id'],['status'=>'cancelled'])->assertOk();
  $this->assertSame(1,app(SalonStore::class)->read()['clientPasses'][0]['remaining']);
 }
 public function test_internal_messages_are_durable_and_other_guests_cannot_read_them():void {
  $this->guest();$this->postJson('/api/bookings',$this->booking())->assertCreated();
  $this->postJson('/api/messages',['text'=>'Kérdésem van'])->assertCreated();
  $this->provider();$this->getJson('/api/messages')->assertJsonCount(1)->assertJsonPath('0.readAt',null);
  $this->patchJson('/api/messages',['guestId'=>10])->assertOk();
  $this->guest(11);$this->getJson('/api/messages')->assertExactJson([]);
  $this->provider(2);$this->getJson('/api/messages')->assertExactJson([]);
  $this->assertCount(1,app(Tenant::class)->run(1,fn()=>app(SalonStore::class)->read()['messages']));
 }
 public function test_platform_is_separate_and_transfer_is_idempotent():void {
  $this->provider();$this->getJson('/api/platform/providers')->assertUnauthorized();
  $this->withSession(['platform_admin'=>true]);
  $created=$this->postJson('/api/platform/providers',['name'=>'Új vállalkozás','email'=>'owner@example.com','password'=>'long-enough-password'])->assertCreated()->json();
  $this->assertGreaterThan(2,$created['id']);
  $payment=['providerId'=>$created['id'],'reference'=>'bank-unique-123','paidAt'=>'2026-11-01'];
  $this->postJson('/api/platform/payments',$payment)->assertOk()->assertJsonPath('alreadyCredited',false);
  $this->postJson('/api/platform/payments',$payment)->assertOk()->assertJsonPath('alreadyCredited',true);
  $this->assertSame(1,DB::table('platform_payments')->count());
  $this->assertNotEmpty(app(Tenant::class)->run($created['id'],fn()=>app(SalonStore::class)->read()['subscription']['trialEndsAt']));
 }
 public function test_upgrade_is_repeatable_and_keeps_old_data():void {
  $original=app(SalonStore::class)->read();$this->assertSame(0,Artisan::call('raero:upgrade'));$once=app(SalonStore::class)->read();
  $this->assertSame($original['services'],$once['services']);$this->assertSame($original['staff'],$once['staff']);
  $this->assertSame(0,Artisan::call('raero:upgrade'));$this->assertSame($once,app(SalonStore::class)->read());
 }

 public function test_provider_connections_never_inherit_original_secrets():void {
  app(\App\Services\Connections::class)->save(['stripe_key'=>'sk_private_one']);
  $this->assertSame('sk_private_one', app(\App\Services\Connections::class)->all()['stripe_key']);
  $this->assertEmpty(app(Tenant::class)->run(2,fn()=>app(\App\Services\Connections::class)->all()['stripe_key']));
 }
 public function test_avatar_upload_rejects_scripts_and_other_people_cannot_read():void {
  Storage::fake('local');$this->guest();
  $this->post('/api/guest/avatar',['image'=>UploadedFile::fake()->createWithContent('attack.svg','<svg xmlns="http://www.w3.org/2000/svg"></svg>')],['Accept'=>'application/json'])->assertUnprocessable();
  $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6v8AAAAASUVORK5CYII=');
  $this->post('/api/guest/avatar',['image'=>UploadedFile::fake()->createWithContent('avatar.png',$png)],['Accept'=>'application/json'])->assertOk();
  $path=DB::table('guest_accounts')->where('id',10)->value('avatar');Storage::disk('local')->assertExists($path);
  $this->get('/api/guest/avatar/10')->assertOk();
  $this->guest(11);$this->get('/api/guest/avatar/10')->assertNotFound();
  $this->provider(2);$this->get('/api/guest/avatar/10?provider=1')->assertNotFound();
 }
 public function test_newsletter_requires_explicit_consent_and_unsubscribe_prevents_sending():void {
  $this->provider();$this->postJson('/api/imports/clients',['records'=>[['name'=>'Importált','email'=>'import@example.com']]])->assertOk();
  $this->assertSame(0,DB::table('newsletter_consents')->count());$this->guest();
  $this->putJson('/api/newsletter/consent',['subscribed'=>true])->assertOk();
  $consent=DB::table('newsletter_consents')->first();
  $url=\Illuminate\Support\Facades\URL::signedRoute('newsletter.unsubscribe',['id'=>$consent->id]);
  $this->get($url)->assertOk();$this->getJson('/api/newsletter/consent')->assertJsonPath('subscribed',false);
  $this->withSession(['platform_admin'=>true]);
  $n=$this->postJson('/api/platform/newsletters',['subject'=>'Hírlevél','content'=>'Tartalom','audience'=>'guest'])->assertCreated()->json();
  $this->postJson('/api/platform/newsletters/'.$n['id'].'/schedule',['scheduledAt'=>now()->addMinutes(2)->toISOString()])->assertOk();
  DB::table('newsletters')->where('id',$n['id'])->update(['scheduled_at'=>now()->subMinute()]);
  app(\App\Services\Newsletters::class)->run();$this->assertSame(0,DB::table('newsletter_deliveries')->count());
 }
 public function test_newsletter_queue_is_unique_and_records_smtp_acceptance():void {
  $this->guest();$this->putJson('/api/newsletter/consent',['subscribed'=>true])->assertOk();
  $n=DB::table('newsletters')->insertGetId(['audience'=>'guest','subject'=>'Hír','content'=>'Levél','status'=>'scheduled','scheduled_at'=>now()->subMinute(),'created_at'=>now(),'updated_at'=>now()]);
  $service=new class extends \App\Services\Newsletters { public int $calls=0; public function ready():bool{return true;} public function send(string $to,string $subject,string $content):void{$this->calls++;} };
  $this->app->instance(\App\Services\Newsletters::class,$service);
  $service->run();$service->run();$this->assertSame(1,$service->calls);$this->assertSame(1,DB::table('newsletter_deliveries')->count());
  $this->assertSame('accepted',DB::table('newsletter_deliveries')->value('status'));
 }
 public function test_operator_invoice_is_aam_separate_and_idempotent():void {
  $this->provider();app(SalonStore::class)->mutate(function(&$s){$s['settings']=array_replace($s['settings'],['billingName'=>'Vevő','billingCountry'=>'HU','billingZip'=>'1111','billingCity'=>'Budapest','billingAddress'=>'Teszt utca 1.']);});
  $this->withSession(['platform_admin'=>true]);$this->postJson('/api/platform/payments',['providerId'=>1,'reference'=>'bank-invoice','paidAt'=>now()->toISOString()])->assertOk();
  $id=DB::table('platform_payments')->value('id');config(['integrations.platform_billingo.key'=>'operator-key','integrations.platform_billingo.block'=>1]);
  \Illuminate\Support\Facades\Http::fake(['api.billingo.hu/v3/partners'=>\Illuminate\Support\Facades\Http::response(['id'=>12]),'api.billingo.hu/v3/documents'=>\Illuminate\Support\Facades\Http::response(['id'=>44,'invoice_number'=>'OP-44'])]);
  $this->postJson('/api/platform/invoices',['paymentId'=>$id])->assertOk()->assertJsonPath('number','OP-44');
  $this->postJson('/api/platform/invoices',['paymentId'=>$id])->assertOk()->assertJsonPath('alreadyIssued',true);
  \Illuminate\Support\Facades\Http::assertSentCount(2);
  \Illuminate\Support\Facades\Http::assertSent(fn($request)=>str_contains($request->url(),'/documents')&&$request->hasHeader('X-API-KEY','operator-key')&&$request['items'][0]['vat']==='AAM'&&$request['items'][0]['unit_price']===4000);
 }
 public function test_operator_messages_are_only_visible_to_the_recipient():void {
  $this->guest();$this->withSession(['platform_admin'=>true]);
  $this->postJson('/api/platform-messages',['recipientType'=>'guest','recipientId'=>10,'text'=>'Személyes üzenet'])->assertCreated();
  $this->withSession(['platform_admin'=>false]);$this->getJson('/api/platform-messages')->assertJsonCount(1);
  $this->guest(11);$this->getJson('/api/platform-messages')->assertExactJson([]);
  $this->provider(2);$this->getJson('/api/platform-messages')->assertExactJson([]);
 }

 public function test_signed_subscription_payment_replay_and_old_failure_do_not_double_credit():void {
  config(['integrations.platform_stripe.webhook_secret'=>'whsec_platform_test']);
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['id'=>'sub_test','status'=>'active'];});
  $send=function(array $event){$body=json_encode($event);$t=time();return $this->call('POST','/api/platform-stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>'t='.$t.',v1='.hash_hmac('sha256',$t.'.'.$body,'whsec_platform_test')],$body);};
  $event=['id'=>'evt_paid','created'=>100,'type'=>'invoice.paid','data'=>['object'=>['id'=>'in_test','subscription'=>'sub_test','paid'=>true,'currency'=>'huf','amount_paid'=>400000,'period_end'=>now()->addMonth()->timestamp]]];
  $send($event)->assertOk();$send($event)->assertOk();$this->assertSame(1,DB::table('platform_payments')->count());
  $event['id']='evt_old_failure';$event['type']='invoice.payment_failed';$event['created']=99;$send($event)->assertOk();
  $this->assertSame('active',app(SalonStore::class)->read()['subscription']['status']);
 }
 public function test_secret_booking_link_can_be_claimed_once_and_never_by_a_second_guest():void {
  $b=$this->postJson('/api/bookings',$this->booking())->assertCreated()->json();$this->guest();
  $this->postJson('/api/guest/claim',['token'=>str_repeat('a',48)])->assertNotFound();
  $this->postJson('/api/guest/claim',['token'=>$b['token']])->assertOk();$this->getJson('/api/guest')->assertJsonCount(1,'bookings');
  $this->guest(11);$this->postJson('/api/guest/claim',['token'=>$b['token']])->assertForbidden();$this->getJson('/api/guest')->assertJsonCount(0,'bookings');
 }
 public function test_no_show_consumes_one_pass_visit_and_never_allows_a_review():void {
  $this->provider();app(SalonStore::class)->mutate(function(&$s){$s['clients'][]=['id'=>'one','name'=>'Vendég','phone'=>'12345','email'=>''];});
  $p=$this->postJson('/api/passes',['name'=>'Bérlet','price'=>8000,'visits'=>1,'days'=>3650,'services'=>['cut'],'active'=>true])->json();
  $pass=$this->postJson('/api/client-passes',['clientId'=>'one','passId'=>$p['id']])->json();
  $b=$this->postJson('/api/bookings',$this->booking(['clientId'=>'one','passId'=>$pass['id']]))->assertCreated()->json();
  app(SalonStore::class)->mutate(function(&$s){$s['bookings'][0]['start']='2020-03-12T09:00:00Z';});
  $this->patchJson('/api/bookings/'.$b['id'],['attendance'=>'no_show'])->assertOk();
  $this->getJson('/api/clients/one/detail')->assertJsonPath('stats.no_show',1)->assertJsonPath('stats.attended',0);
  $this->assertSame(0,app(SalonStore::class)->read()['clientPasses'][0]['remaining']);
  $this->postJson('/api/bookings/'.$b['id'].'/review',['score'=>1,'text'=>'Nem jött el'])->assertUnprocessable();
  $this->patchJson('/api/bookings/'.$b['id'],['status'=>'cancelled'])->assertUnprocessable();
 }
 public function test_new_base_plan_blocks_extra_calendars_in_direct_api_and_import():void {
  $this->provider();app(SalonStore::class)->mutate(function(&$s){$s['staff']=[$s['staff'][0]];$s['subscription']=['status'=>'active','calendarLimit'=>1];});
  $this->postJson('/api/imports/staff',['records'=>[['id'=>'extra','name'=>'Második','role'=>'Szakember']]])->assertUnprocessable();
  $people=app(SalonStore::class)->read()['staff'];$people[]=['id'=>'extra','name'=>'Második','role'=>'Szakember'];
  $this->putJson('/api/config',['staff'=>$people])->assertUnprocessable();
  $this->assertCount(1,app(SalonStore::class)->read()['staff']);
 }

 public function test_operator_secrets_are_masked_and_not_available_to_provider():void {
  $path=storage_path('framework/features-platform.enc');config(['operator.connections_path'=>$path]);if(is_file($path))unlink($path);
  $this->provider();$this->getJson('/api/platform/connections')->assertUnauthorized();$this->withSession(['platform_admin'=>true]);
  $this->putJson('/api/platform/connections',['stripe_key'=>'operator-test-key','billingo_key'=>'operator-billingo-key'])->assertOk();
  $this->getJson('/api/platform/connections')->assertJsonPath('stripe_key_configured',true)->assertJsonMissingPath('stripe_key')->assertJsonMissingPath('billingo_key');
  $this->assertStringNotContainsString('operator-test-key',file_get_contents($path));
  $this->withSession(['platform_admin'=>false]);$this->getJson('/api/platform/connections')->assertUnauthorized();
  $this->assertNotSame('operator-test-key',app(\App\Services\Connections::class)->all()['stripe_key']);unlink($path);if(is_file($path.'.lock'))unlink($path.'.lock');
 }

 public function test_provider_cannot_inject_account_link_when_saving_clients():void {
  $this->provider();$this->putJson('/api/config',['clients'=>[['id'=>'unlinked','name'=>'Importált','phone'=>'123456','email'=>'guest@example.com','guestId'=>10]]])->assertOk();
  $this->assertArrayNotHasKey('guestId',app(SalonStore::class)->read()['clients'][0]);
  $this->putJson('/api/config',['clients'=>[['id'=>'second','name'=>'Második','phone'=>'999','email'=>'']]])->assertOk();
  $this->assertCount(2,app(SalonStore::class)->read()['clients']);
 }

 public function test_manual_delivery_run_cannot_process_another_provider_queue():void {
  DB::table('deliveries')->insert(['provider_id'=>2,'dedup'=>str_repeat('a',64),'payload'=>\Illuminate\Support\Facades\Crypt::encryptString(json_encode(['kind'=>'message','messageId'=>'absent'])),'status'=>'pending','attempts'=>0,'available_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
  $this->provider();$this->postJson('/api/deliveries/run')->assertOk();$this->assertSame('pending',DB::table('deliveries')->value('status'));
 }

 protected function tearDown():void {\Carbon\Carbon::setTestNow();\Carbon\CarbonImmutable::setTestNow();parent::tearDown();}
 private function clock(string $date):void {\Carbon\Carbon::setTestNow($date);\Carbon\CarbonImmutable::setTestNow($date);}
 public function test_registered_contacts_are_guest_owned_across_all_provider_write_routes():void {
  $this->guest();$a=$this->postJson('/api/bookings',$this->booking())->assertCreated()->json();$this->postJson('/api/bookings?provider=2',$this->booking())->assertCreated();
  $this->patchJson('/api/guest/profile',['email'=>'new@example.com','phone'=>'+36 20 222 3333'])->assertOk();
  $this->provider();$this->getJson('/api/state')->assertJsonPath('clients.0.email','new@example.com')->assertJsonPath('clients.0.phone','+36 20 222 3333');
  $change=['name'=>'Vendég','email'=>'attacker@example.com','phone'=>'999'];
  $this->patchJson('/api/clients/'.$a['clientId'],$change)->assertUnprocessable();
  $this->putJson('/api/config',['clients'=>[['id'=>$a['clientId']]+$change]])->assertUnprocessable();
  $this->postJson('/api/imports/clients',['records'=>[['id'=>$a['clientId']]+$change]])->assertUnprocessable();
  $this->getJson('/api/state')->assertJsonPath('bookings.0.email','guest@example.com');
  $this->provider(2);$this->getJson('/api/state')->assertJsonPath('clients.0.email','new@example.com');
  $this->assertSame('guest@example.com',DB::table('guest_accounts')->where('id',10)->value('email'));
 }
 public function test_removing_client_only_hides_current_provider_relationship_and_keeps_history():void {
  $this->guest();$a=$this->postJson('/api/bookings',$this->booking())->assertCreated()->json();$this->postJson('/api/bookings?provider=2',$this->booking())->assertCreated();
  $this->provider();$this->deleteJson('/api/clients/'.$a['clientId'])->assertOk();
  $this->getJson('/api/state')->assertJsonCount(0,'clients')->assertJsonCount(1,'bookings');
  $this->getJson('/api/clients/search?q=Vend')->assertExactJson([]);$this->getJson('/api/clients/'.$a['clientId'].'/detail')->assertNotFound();
  $this->assertSame(1,DB::table('guest_accounts')->count());$this->provider(2);$this->getJson('/api/state')->assertJsonCount(1,'clients')->assertJsonCount(1,'bookings');
 }
 public function test_trial_has_two_zero_charges_and_no_invoice_or_payment():void {
  $this->clock('2026-11-01 10:00:00');$this->provider();
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['status'=>'trialing','trialStartedAt'=>'2026-11-01T10:00:00+01:00','trialEndsAt'=>'2027-01-01T10:00:00+01:00'];});
  $d=$this->getJson('/api/subscription/info')->assertOk()->assertJsonCount(2,'subscription.charges')->assertJsonPath('subscription.calendarBlocked',false)->json();
  $this->assertSame([0,0],array_column($d['subscription']['charges'],'amount'));$this->assertSame(0,DB::table('platform_payments')->count());$this->assertSame(0,DB::table('platform_invoices')->count());
 }
 public function test_21_day_deadline_blocks_calendar_but_allows_login_billing_and_settlement():void {
  $this->clock('2026-10-11 10:00:00');
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['status'=>'trialing','trialStartedAt'=>'2026-07-01T10:00:00+02:00','trialEndsAt'=>'2026-09-01T10:00:00+02:00'];});
  config(['raero.admin_password_hash'=>Hash::make('calendar-test-password')]);
  $this->postJson('/api/login',['password'=>'calendar-test-password'])->assertOk();$this->provider();
  $this->postJson('/api/bookings',$this->booking())->assertStatus(423);
  $d=$this->getJson('/api/subscription/info')->assertOk()->assertJsonPath('subscription.calendarBlocked',true)->json();
  $monthly=array_values(array_filter($d['subscription']['charges'],fn($e)=>$e['kind']==='monthly'));$this->assertSame(['overdue','pending'],array_column($monthly,'status'));
  $this->withSession(['platform_admin'=>true]);
  $this->postJson('/api/platform/payments',['providerId'=>1,'reference'=>'bank-new-period','paidAt'=>'2026-10-11','chargeId'=>$monthly[1]['id']])->assertOk();
  $this->getJson('/api/subscription/info')->assertJsonPath('subscription.calendarBlocked',true); // Paying a newer month must not erase older debt.
  $this->postJson('/api/platform/payments',['providerId'=>1,'reference'=>'bank-new-period','paidAt'=>'2026-10-11','chargeId'=>$monthly[0]['id']])->assertUnprocessable();
  $this->postJson('/api/platform/payments',['providerId'=>1,'reference'=>'bank-old-period','paidAt'=>'2026-10-11','chargeId'=>$monthly[0]['id']])->assertOk();
  $this->getJson('/api/subscription/info')->assertJsonPath('subscription.calendarBlocked',false);
  $this->postJson('/api/platform/payments',['providerId'=>1,'reference'=>'another-bank-reference','paidAt'=>'2026-10-11','chargeId'=>$monthly[0]['id']])->assertUnprocessable();
  $this->assertSame(2,DB::table('platform_payments')->count());$this->postJson('/api/bookings',$this->booking())->assertCreated();
 }
 public function test_public_booking_lock_does_not_disclose_provider_financial_data():void {
  $this->clock('2026-10-11 10:00:00');app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['trialStartedAt'=>'2026-07-01T10:00:00+02:00','trialEndsAt'=>'2026-09-01T10:00:00+02:00'];});
  $this->postJson('/api/bookings',$this->booking())->assertStatus(423)->assertJsonPath('message','A szolgáltató online foglalása jelenleg szünetel. Időpontért keresd közvetlenül a szolgáltatót.');
 }
 public function test_stripe_payment_settles_pending_charge_and_keeps_transaction_metadata_on_replay():void {
  $this->clock('2026-09-10 10:00:00');config(['integrations.platform_stripe.webhook_secret'=>'whsec_refinement_test']);
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['id'=>'sub_refinement','trialStartedAt'=>'2026-07-01T10:00:00+02:00','trialEndsAt'=>'2026-09-01T10:00:00+02:00'];});
  $start=\Carbon\CarbonImmutable::parse('2026-09-01T10:00:00+02:00')->timestamp;$end=\Carbon\CarbonImmutable::parse('2026-10-01T10:00:00+02:00')->timestamp;
  $event=['id'=>'evt_refinement','created'=>time(),'type'=>'invoice.paid','data'=>['object'=>['id'=>'in_refinement','subscription'=>'sub_refinement','customer'=>'cus_refinement','payment_intent'=>['id'=>'pi_refinement','client_secret'=>'never-return-this-test-string'],'paid'=>true,'currency'=>'huf','amount_paid'=>400000,'lines'=>['data'=>[['period'=>['start'=>$start,'end'=>$end]]]]]]];
  $body=json_encode($event);$t=time();$signature='t='.$t.',v1='.hash_hmac('sha256',$t.'.'.$body,'whsec_refinement_test');
  for($i=0;$i<2;$i++)$this->call('POST','/api/platform-stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>$signature],$body)->assertOk();
  $this->assertSame(1,DB::table('platform_payments')->count());$this->provider();$d=$this->getJson('/api/subscription/info')->assertOk()->json();
  $charge=collect($d['subscription']['charges'])->firstWhere('kind','monthly');$this->assertSame('paid',$charge['status']);
  $meta=json_decode($d['payments'][0]['details'],true);$this->assertSame('in_refinement',$meta['stripeInvoice']);$this->assertSame('pi_refinement',$meta['paymentIntent']);$this->assertStringNotContainsString('never-return-this-test-string',$d['payments'][0]['details']);
 }
 public function test_offer_availability_flags_do_not_expose_private_codes_or_measurement_ids():void {
  $this->getJson('/api/public')->assertJsonPath('hasCoupons',false)->assertJsonPath('hasPasses',false)->assertJsonMissingPath('settings.googleAnalyticsId');
  $this->provider();$c=$this->coupon();$c['public']=false;$this->postJson('/api/coupons',$c)->assertCreated();
  $this->getJson('/api/public')->assertJsonPath('hasCoupons',true)->assertJsonCount(0,'offers')->assertJsonMissing(['code'=>'ELSO']);
 }
 public function test_month_end_charge_periods_are_stable_at_deadline_boundary():void {
  $this->clock('2026-04-21 10:00:00');$s=\App\Services\Settings::upgrade(SalonStore::initial());$s['subscription']=['trialStartedAt'=>'2026-01-31T10:00:00+01:00','trialEndsAt'=>'2026-03-31T10:00:00+02:00'];
  \App\Services\Subscriptions::refresh($s);$once=$s;\App\Services\Subscriptions::refresh($s);$this->assertSame($once,$s);$this->assertFalse($s['subscription']['calendarBlocked']);
  $this->clock('2026-04-21 10:00:01');\App\Services\Subscriptions::refresh($s);$this->assertTrue($s['subscription']['calendarBlocked']);
 }

 public function test_zero_trial_entry_is_never_invoiced():void {
  config(['integrations.platform_billingo.key'=>'zero-test-key','integrations.platform_billingo.block'=>1]);$this->withSession(['platform_admin'=>true]);
  $id=DB::table('platform_payments')->insertGetId(['provider_id'=>1,'reference'=>'legacy-zero','amount'=>0,'method'=>'transfer','paid_at'=>now(),'period_end'=>now()->addMonth(),'created_at'=>now(),'updated_at'=>now()]);
  \Illuminate\Support\Facades\Http::fake();$this->postJson('/api/platform/invoices',['paymentId'=>$id])->assertUnprocessable()->assertJsonPath('error','A díjmentes próbaidőről nem állítunk ki számlát.');\Illuminate\Support\Facades\Http::assertNothingSent();
 }
 public function test_sample_seed_is_guarded_and_real_test_fixture_is_repeatable():void {
  $this->app['env']='production';try{app(\App\Services\SampleData::class)->add();$this->fail('Production seed must fail');}catch(\InvalidArgumentException){$this->assertCount(0,app(SalonStore::class)->read()['bookings']);}
  $this->app['env']='testing';$original=config('database.default');$file=base_path('work/sample-test-'.bin2hex(random_bytes(6)).'.sqlite');if(!is_dir(dirname($file)))mkdir(dirname($file),0700,true);touch($file);
  config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$file]);DB::purge('sqlite');
  try{Artisan::call('migrate',['--force'=>true]);DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode(SalonStore::initial())]);
   $first=app(\App\Services\SampleData::class)->add();$second=app(\App\Services\SampleData::class)->add();$this->assertGreaterThan(60,$first['addedBookings']);$this->assertSame(0,$second['addedBookings']);$this->assertGreaterThan(30,count(app(SalonStore::class)->read()['payments']));$this->assertSame(0,DB::table('deliveries')->count());$this->assertSame(0,DB::table('platform_payments')->count());
  }finally{DB::purge('sqlite');config(['database.default'=>$original]);unlink($file);}
 }

 public function test_early_stripe_checkout_event_is_retried_without_losing_subscription_link():void {
  config(['integrations.platform_stripe.webhook_secret'=>'whsec_race_test']);
  $event=['id'=>'evt_early','created'=>time(),'type'=>'checkout.session.completed','data'=>['object'=>['id'=>'cs_early','subscription'=>'sub_early','customer'=>'cus_early','payment_status'=>'paid','metadata'=>['provider_id'=>'1']]]];
  $send=function()use($event){$body=json_encode($event);$t=time();return $this->call('POST','/api/platform-stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>'t='.$t.',v1='.hash_hmac('sha256',$t.'.'.$body,'whsec_race_test')],$body);};
  $send()->assertStatus(409);$this->assertNotContains('evt_early',app(SalonStore::class)->read()['stripeEvents']??[]);
  app(SalonStore::class)->mutate(function(&$s){$s['checkouts']['cs_early']=['type'=>'subscription'];});$send()->assertOk();$this->assertSame('sub_early',app(SalonStore::class)->read()['subscription']['id']);$this->assertSame(0,DB::table('platform_payments')->count());
 }

 public function test_overdue_stripe_invoice_is_reused_instead_of_creating_second_subscription():void {
  config(['integrations.platform_stripe.key'=>'sk_subscription_test','integrations.platform_stripe.price'=>'price_test']);$this->provider();
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['id'=>'sub_pending','status'=>'past_due'];});
  \Illuminate\Support\Facades\Http::fake(['api.stripe.com/v1/subscriptions/sub_pending*'=>\Illuminate\Support\Facades\Http::response(['status'=>'past_due','latest_invoice'=>['status'=>'open','amount_remaining'=>400000,'hosted_invoice_url'=>'https://invoice.stripe.com/i/test_existing']])]);
  $this->postJson('/api/subscription/checkout')->assertOk()->assertJsonPath('url','https://invoice.stripe.com/i/test_existing');
  \Illuminate\Support\Facades\Http::assertSentCount(1);\Illuminate\Support\Facades\Http::assertSent(fn($r)=>$r->method()==='GET');
 }
 public function test_last_48_hours_of_trial_never_sends_invalid_stripe_trial_request():void {
  $this->clock('2026-12-31 10:00:00');config(['integrations.platform_stripe.key'=>'sk_trial_test','integrations.platform_stripe.price'=>'price_test']);$this->provider();
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['trialStartedAt'=>'2026-11-01T10:00:00+01:00','trialEndsAt'=>'2027-01-01T10:00:00+01:00'];});
  \Illuminate\Support\Facades\Http::fake();$this->postJson('/api/subscription/checkout')->assertUnprocessable();\Illuminate\Support\Facades\Http::assertNothingSent();
 }
 public function test_social_login_returns_to_selected_provider_and_remembers_owned_contacts():void {
  \Illuminate\Support\Facades\Http::preventStrayRequests();
  app(Tenant::class)->run(2,fn()=>app(\App\Services\Connections::class)->save(['google_client_id'=>'client','google_client_secret'=>'secret','facebook_client_id'=>'client','facebook_client_secret'=>'secret']));
  \Illuminate\Support\Facades\Http::fake([
   'oauth2.googleapis.com/*'=>\Illuminate\Support\Facades\Http::response(['access_token'=>'token']),
   'openidconnect.googleapis.com/*'=>\Illuminate\Support\Facades\Http::response(['sub'=>'owned-google','name'=>'Közösségi Vendég','email'=>'social@example.com','email_verified'=>true]),
   'graph.facebook.com/*/oauth/access_token'=>\Illuminate\Support\Facades\Http::response(['access_token'=>'token']),
   'graph.facebook.com/*/me*'=>\Illuminate\Support\Facades\Http::response(['id'=>'owned-facebook','name'=>'Közösségi Vendég','email'=>'social@example.com']),
  ]);
  foreach(['google','facebook'] as $social){
   $r=$this->get('/auth/'.$social.'?provider=2&returnTo=booking');parse_str(parse_url($r->headers->get('Location'),PHP_URL_QUERY),$q);
   $this->get('/auth/'.$social.'/callback?state='.$q['state'].'&code=code')->assertRedirect('/foglalas?provider=2#public-form');
   $this->getJson('/api/guest?provider=2')->assertOk()->assertJsonPath('name','Közösségi Vendég')->assertJsonPath('email','social@example.com')->assertJsonPath('phone','');
   $this->patchJson('/api/guest/profile?provider=2',['email'=>'contact@example.com','phone'=>'+36 30 111 2222'])->assertOk();
   $r=$this->get('/auth/'.$social.'?provider=2&returnTo=https://example.org');parse_str(parse_url($r->headers->get('Location'),PHP_URL_QUERY),$q);
   $this->get('/auth/'.$social.'/callback?state='.$q['state'].'&code=code')->assertRedirect('/fiokom?provider=2');
   $this->getJson('/api/guest?provider=2')->assertJsonPath('phone','+36 30 111 2222')->assertJsonPath('email','contact@example.com');
   $this->getJson('/api/state?provider=2')->assertUnauthorized();
  }
 }
 public function test_guest_history_lists_own_bookings_across_providers_without_other_guest_data():void {
  $this->guest();$one=$this->postJson('/api/bookings',$this->booking())->assertCreated()->json();
  $two=$this->postJson('/api/bookings?provider=2',$this->booking())->assertCreated()->json();
  $this->guest(11);$other=$this->postJson('/api/bookings?provider=2',$this->booking(['start'=>'2035-03-13T09:00:00Z']))->assertCreated()->json();
  $this->withSession(['guest_id'=>10]);
  $result=$this->getJson('/api/guest')->assertOk()->assertJsonCount(2,'allBookings')->assertJsonCount(1,'bookings')->assertJsonCount(2,'providers')->json();
  $this->assertEqualsCanonicalizing([$one['id'],$two['id']],array_column($result['allBookings'],'id'));
  $this->assertStringNotContainsString($other['token'],json_encode($result));
  $this->getJson('/api/guest?provider=2')->assertJsonCount(2,'allBookings')->assertJsonPath('bookings.0.id',$two['id']);
  $this->withSession(['guest_id'=>null]);$this->getJson('/api/guest')->assertUnauthorized();
 }
 public function test_social_login_imports_google_and_facebook_photos_privately():void {
  Storage::fake('local');
  app(\App\Services\Connections::class)->save(['google_client_id'=>'client','google_client_secret'=>'secret','facebook_client_id'=>'client','facebook_client_secret'=>'secret']);
  $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aY1cAAAAASUVORK5CYII=');
  \Illuminate\Support\Facades\Http::preventStrayRequests();
  \Illuminate\Support\Facades\Http::fake([
   'oauth2.googleapis.com/*'=>\Illuminate\Support\Facades\Http::response(['access_token'=>'token']),
   'openidconnect.googleapis.com/*'=>\Illuminate\Support\Facades\Http::response(['sub'=>'google-photo','name'=>'Képes Vendég','picture'=>'https://lh3.googleusercontent.com/test-photo']),
   'graph.facebook.com/*/oauth/access_token'=>\Illuminate\Support\Facades\Http::response(['access_token'=>'token']),
   'graph.facebook.com/*/me*'=>\Illuminate\Support\Facades\Http::response(['id'=>'facebook-photo','name'=>'Képes Vendég','picture'=>['data'=>['url'=>'https://example.fbcdn.net/test-photo','is_silhouette'=>false]]]),
   'lh3.googleusercontent.com/*'=>\Illuminate\Support\Facades\Http::response($png,200,['Content-Type'=>'image/png']),
   'example.fbcdn.net/*'=>\Illuminate\Support\Facades\Http::response($png,200,['Content-Type'=>'image/png']),
  ]);
  foreach(['google','facebook'] as $social){
   $r=$this->get('/auth/'.$social);parse_str(parse_url($r->headers->get('Location'),PHP_URL_QUERY),$q);
   $this->get('/auth/'.$social.'/callback?state='.$q['state'].'&code=code')->assertRedirect('/fiokom');
   $guest=$this->getJson('/api/guest')->assertOk()->json();
   $this->assertSame('/api/guest/avatar/'.$guest['id'],$guest['avatar']);
   $path=DB::table('guest_accounts')->where('id',$guest['id'])->value('avatar');Storage::disk('local')->assertExists($path);
   $this->assertSame($png,Storage::disk('local')->get($path));$this->get($guest['avatar'])->assertOk();
  }
  $this->withSession(['guest_id'=>null]);$this->get($guest['avatar'])->assertNotFound();
 }
 public function test_social_avatar_never_overwrites_uploaded_picture_or_fetches_untrusted_urls():void {
  Storage::fake('local');$this->guest();\Illuminate\Support\Facades\Http::fake();
  $service=app(\App\Services\SocialAvatar::class);
  foreach(['http://lh3.googleusercontent.com/a','https://127.0.0.1/a','https://lh3.googleusercontent.com.evil.invalid/a','https://user:pass@lh3.googleusercontent.com/a','https://lh3.googleusercontent.com:8080/a'] as $url)$service->import(10,'google',$url);
  DB::table('guest_accounts')->where('id',10)->update(['avatar'=>'avatars/own.png']);Storage::disk('local')->put('avatars/own.png','own');
  $service->import(10,'google','https://lh3.googleusercontent.com/photo');
  \Illuminate\Support\Facades\Http::assertNothingSent();$this->assertSame('avatars/own.png',DB::table('guest_accounts')->where('id',10)->value('avatar'));Storage::disk('local')->assertExists('avatars/own.png');
 }
 public function test_social_avatar_rejects_invalid_large_or_redirecting_images_and_network_failure():void {
  Storage::fake('local');$this->guest();
  $responses=[\Illuminate\Support\Facades\Http::response('<svg></svg>',200),\Illuminate\Support\Facades\Http::response(str_repeat('x',2097153),200),\Illuminate\Support\Facades\Http::response('',302,['Location'=>'http://127.0.0.1/private'])];
  \Illuminate\Support\Facades\Http::fake(function()use(&$responses){if(!$responses)throw new \Illuminate\Http\Client\ConnectionException('Unavailable');return array_shift($responses);});
  for($i=0;$i<3;$i++){
   app(\App\Services\SocialAvatar::class)->import(10,'google','https://lh3.googleusercontent.com/photo');
   $this->assertNull(DB::table('guest_accounts')->where('id',10)->value('avatar'));
  }
  app(\App\Services\SocialAvatar::class)->import(10,'google','https://lh3.googleusercontent.com/photo');
  $this->getJson('/api/guest')->assertOk()->assertJsonPath('avatar',null);
  $this->assertSame([],Storage::disk('local')->allFiles('avatars'));
 }
}
