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
}
