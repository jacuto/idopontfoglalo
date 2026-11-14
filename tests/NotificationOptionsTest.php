<?php
namespace Tests;
use App\Services\{SalonStore,NotificationPreferences,Notifications,ClientInvitations,Clients};
use Illuminate\Foundation\Testing\TestCase;
use Carbon\CarbonImmutable as Time;
use Illuminate\Support\Facades\{Artisan,DB,Crypt,Http};
final class NotificationOptionsTest extends TestCase
{
 public function createApplication(){$a=require __DIR__.'/../bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $a;}
 protected function setUp():void{parent::setUp();if(config('database.default')!=='sqlite'&&!str_ends_with((string)config('database.connections.mysql.database'),'_test'))throw new \RuntimeException('Külön tesztadatbázis szükséges.');Artisan::call('migrate',['--force'=>true]);foreach(['guest_invitations','deliveries','calendar_tasks','calendar_connections','guest_accounts','providers','salon_state'] as $table)DB::table($table)->delete();DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode(SalonStore::initial())]);Time::setTestNow('2035-03-05T08:00:00Z');Http::preventStrayRequests();$this->withSession(['admin'=>true,'provider_id'=>1,'_token'=>str_repeat('n',40)])->withHeader('X-CSRF-TOKEN',str_repeat('n',40));}
 protected function tearDown():void{Time::setTestNow();parent::tearDown();}
 private function settings(array $settings){return $this->putJson('/api/config',['settings'=>array_replace(app(SalonStore::class)->read()['settings'],$settings)]);}
 private function rules():array{return NotificationPreferences::choices(['notifyGuest'=>false,'notifyProvider'=>false,'reminderGuest'=>false]);}
 private function booking(array $v=[]):array{return array_replace(['name'=>'Teszt Vendég','phone'=>'06301234567','email'=>'guest@example.com','services'=>['cut'],'staff'=>'anna','start'=>'2035-03-12T09:00:00Z'],$v);}
 private function payloads():array{return DB::table('deliveries')->get()->map(fn($j)=>json_decode(Crypt::decryptString($j->payload),true))->all();}
 public function test_independent_event_channels_and_one_notice_for_auto_confirmation():void{
  $rules=$this->rules();$rules['guest']['created']['email']=true;$rules['guest']['confirmed']['sms']=true;$rules['provider']['created']['sms']=true;
  $this->settings(['notificationRules'=>$rules,'providerPhone'=>'+36 70 123-4567'])->assertOk();
  $this->assertSame('06701234567',app(SalonStore::class)->read()['settings']['providerPhone']);
  $this->postJson('/api/bookings',$this->booking())->assertCreated();$p=$this->payloads();$this->assertCount(2,$p);$this->assertSame(['created','created'],array_column($p,'event'));$this->assertSame(['email','sms'],array_column($p,'channel'));$this->assertSame('06701234567',$p[1]['recipient']);Http::assertNothingSent();
 }
 public function test_acceptance_is_separate_from_other_changes():void{
  $rules=$this->rules();$rules['guest']['confirmed']['sms']=true;
  $this->settings(['notificationRules'=>$rules])->assertOk();$this->withSession(['admin'=>false]);
  $booking=$this->postJson('/api/bookings',$this->booking())->assertCreated()->json();$this->assertSame('pending',$booking['status']);$this->assertCount(0,$this->payloads());
  $this->withSession(['admin'=>true]);$this->patchJson('/api/bookings/'.$booking['id'],['status'=>'confirmed'])->assertOk();$p=$this->payloads();$this->assertCount(1,$p);$this->assertSame('confirmed',$p[0]['event']);
  $this->patchJson('/api/bookings/'.$booking['id'],['start'=>'2035-03-12T10:00:00Z'])->assertOk();$this->assertCount(1,$this->payloads());
 }
 public function test_reminder_uses_its_own_channel_and_cannot_be_enabled_on_basic_plan():void{
  $rules=$this->rules();$rules['guest']['reminder']['sms']=true;
  $this->settings(['notificationRules'=>$rules,'reminder'=>12])->assertOk();
  $this->postJson('/api/bookings',$this->booking())->assertCreated();$p=$this->payloads();$this->assertCount(1,$p);$this->assertSame('reminder',$p[0]['event']);$this->assertSame('sms',$p[0]['channel']);$this->assertSame('2035-03-11 21:00:00',DB::table('deliveries')->value('available_at'));
  app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['plan'=>'basic','status'=>'active'];});$this->settings(['notificationRules'=>$rules])->assertStatus(402);
 }
 public function test_disabling_sms_skips_already_queued_messages_and_rules_are_strictly_validated():void{
  $rules=$this->rules();$rules['guest']['created']['sms']=true;$this->settings(['notificationRules'=>$rules])->assertOk();$this->postJson('/api/bookings',$this->booking())->assertCreated();
  $rules['guest']['created']['sms']=false;$this->settings(['notificationRules'=>$rules])->assertOk();$this->assertSame(1,app(Notifications::class)->run()['skipped']);Http::assertNothingSent();
  $rules['guest']['created']['sms']='yes';$this->settings(['notificationRules'=>$rules])->assertUnprocessable();$this->settings(['notificationRules'=>['guest'=>[]]])->assertUnprocessable();
 }
 public function test_sms_invitation_is_once_per_salon_contact_and_can_be_disabled_before_sending():void{
  $this->settings(['guestInvitationSmsEnabled'=>true])->assertOk();$this->postJson('/api/bookings',$this->booking())->assertCreated();$this->postJson('/api/bookings',$this->booking(['start'=>'2035-03-13T09:00:00Z']))->assertCreated();
  $this->assertSame(1,DB::table('guest_invitations')->count());$this->assertCount(1,$this->payloads());$this->assertSame('sms',$this->payloads()[0]['channel']);
  $this->settings(['guestInvitationSmsEnabled'=>false])->assertOk();$this->assertSame(1,app(Notifications::class)->run()['skipped']);Http::assertNothingSent();
 }
 public function test_registered_phone_does_not_receive_an_invitation_and_old_settings_keep_opt_ins():void{
  DB::table('guest_accounts')->insert(['provider'=>'email','subject'=>'test-registered','name'=>'Guest','email'=>'different@example.com','phone'=>'06301234567','created_at'=>now(),'updated_at'=>now()]);$this->settings(['guestInvitationSmsEnabled'=>true])->assertOk();$this->postJson('/api/bookings',$this->booking())->assertCreated();$this->assertSame(0,DB::table('guest_invitations')->count());
  $legacy=NotificationPreferences::choices(['notifyGuest'=>true,'notifyProvider'=>true,'reminderGuest'=>true,'channel'=>'sms']);$this->assertTrue($legacy['guest']['updated']['sms']);$this->assertFalse($legacy['provider']['updated']['sms']);$this->assertTrue($legacy['provider']['updated']['email']);$this->assertTrue($legacy['guest']['reminder']['sms']);
 }
 public function test_provider_group_cancellation_sends_one_sms_and_uses_no_guest_manage_token():void{
  $path=storage_path('framework/notification-options-'.bin2hex(random_bytes(6)));config(['operator.connections_path'=>$path,'integrations.path'=>$path.'-provider']);
  try{app(\App\Services\PlatformConnections::class)->save(['bulkgate_application_id'=>'12345','bulkgate_application_token'=>'fake-token','bulkgate_sender_type'=>'gSystem']);Http::fake(['portal.bulkgate.com/*'=>Http::response(['data'=>['status'=>'accepted','sms_id'=>'group-sms-test']])]);
   $rules=$this->rules();$rules['provider']['group_missed']['sms']=true;$this->settings(['notificationRules'=>$rules,'providerPhone'=>'06701234567'])->assertOk();
   $group=['id'=>'group-notice-test','staff'=>'anna','cancelled'=>true,'minimumCheckedAt'=>now()->toISOString(),'start'=>now()->addDay()->toISOString(),'serviceSnapshot'=>['name'=>'Csoportos óra']];app(SalonStore::class)->mutate(function(&$s)use($group){$s['groupSessions']=[$group];},false);$s=app(SalonStore::class)->read();app(Notifications::class)->groupNotice($group,$s);app(Notifications::class)->groupNotice($group,$s);$this->assertCount(1,$this->payloads());$this->assertSame(1,app(Notifications::class)->run()['sent']);Http::assertSentCount(1);Http::assertSent(fn($r)=>$r['number']==='36701234567'&&!str_contains($r['text'],'token='));$this->assertSame('group-sms-test',DB::table('deliveries')->value('provider_reference'));
  }finally{foreach(glob($path.'*') as $file)if(is_file($file))unlink($file);}
 }
 public function test_search_orders_by_booking_count_before_truncation_and_ignores_cancelled():void{
  $s=app(SalonStore::class)->read();$s['clients']=[];$s['bookings']=[];for($i=0;$i<25;$i++)$s['clients'][]=['id'=>'c'.$i,'name'=>'Teszt '.$i,'phone'=>'','email'=>''];
  foreach([['c24','confirmed'],['c24','confirmed'],['c2','pending'],['c1','cancelled'],['c1','cancelled'],['c1','rejected']] as $i=>[$id,$status])$s['bookings'][]=['clientId'=>$id,'status'=>$status,'start'=>'2035-03-12T09:00:00Z'];
  $found=Clients::search($s,'teszt');$this->assertCount(20,$found);$this->assertSame('c24',$found[0]['id']);$this->assertSame('c2',$found[1]['id']);$this->assertSame([],Clients::search($s,'te'));
 }
 public function test_statistics_export_obeys_staff_filter_and_has_staff_breakdown():void{
  app(SalonStore::class)->mutate(function(&$s){foreach(['anna','eszter'] as $i=>$staff){$id='b'.$i;$s['bookings'][]=['id'=>$id,'name'=>'Vendég '.$staff,'staff'=>$staff,'status'=>'confirmed','start'=>'2035-03-04T09:00:00Z','serviceSnapshot'=>[['name'=>'Teszt','price'=>1000,'duration'=>30]]];$s['payments'][]=['id'=>'p'.$i,'bookingId'=>$id,'paidAt'=>'2035-03-05T08:00:00Z','status'=>'paid','amount'=>1000+$i*2000,'refundedAmount'=>0,'method'=>'cash'];}});
  $r=$this->get('/api/exports/statistics?from=2035-03-01&to=2035-03-31&staff=eszter')->assertOk();$file=$r->baseResponse->getFile()->getPathname();$zip=new \PharData($file);$this->assertStringContainsString('Munkatársak',$zip['xl/workbook.xml']->getContent());$xml=$zip['xl/worksheets/sheet4.xml']->getContent();$this->assertStringContainsString('Vendég eszter',$xml);$this->assertStringNotContainsString('Vendég anna',$xml);$this->assertStringContainsString('<v>3000</v>',$zip['xl/worksheets/sheet5.xml']->getContent());unset($zip);unlink($file);
 }
}
