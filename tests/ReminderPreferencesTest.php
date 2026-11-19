<?php
namespace Tests;
use App\Services\{SalonStore,NotificationPreferences,Notifications};
use Illuminate\Support\Facades\{Artisan,DB,Http};
use Carbon\CarbonImmutable as Time;
class ReminderPreferencesTest extends \Illuminate\Foundation\Testing\TestCase
{
 public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
 protected function setUp():void{parent::setUp();Artisan::call('migrate',['--force'=>true]);Time::setTestNow('2036-05-05T08:00:00Z');$s=SalonStore::initial();$s['subscription']=['plan'=>'pro','status'=>'active'];$s['settings']['reminderGuest']=false;$s['settings']['reminder']=24;$s['settings']['channel']='email';$s['clients']=[['id'=>'one','name'=>'Teszt','email'=>'one@example.com','phone'=>'']];$s['bookings']=[['id'=>'booking','clientId'=>'one','staff'=>$s['staff'][0]['id'],'email'=>'one@example.com','phone'=>'','name'=>'Teszt','services'=>[],'start'=>now()->addDays(3)->toISOString(),'status'=>'confirmed','token'=>str_repeat('a',48),'updatedAt'=>'v1']];DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode($s)]);$this->withSession(['admin'=>true,'provider_id'=>1]);Http::preventStrayRequests();}
 protected function tearDown():void{Time::setTestNow();parent::tearDown();}
 public function test_individual_on_overrides_default_off_and_pause_overrides_individual_without_erasing_it():void{
  $this->postJson('/api/clients/one/reminder-preference',['preference'=>'on'])->assertOk();$s=app(SalonStore::class)->read();$b=NotificationPreferences::withClient($s['bookings'][0],$s);$this->assertTrue(NotificationPreferences::enabled($s['settings'],'guest','reminder','email',$b));$this->assertFalse(NotificationPreferences::enabled($s['settings'],'guest','reminder','sms',$b));$this->assertSame(1,DB::table('deliveries')->count());
  $this->putJson('/api/config',['settings'=>array_replace(app(SalonStore::class)->read()['settings'],['guestRemindersPaused'=>true])])->assertOk();$s=app(SalonStore::class)->read();$this->assertSame('on',$s['clients'][0]['reminderPreference']);$this->assertFalse(NotificationPreferences::enabled($s['settings'],'guest','reminder','email',$b));
  $this->putJson('/api/config',['settings'=>array_replace(app(SalonStore::class)->read()['settings'],['guestRemindersPaused'=>false])])->assertOk();$s=app(SalonStore::class)->read();$this->assertTrue(NotificationPreferences::enabled($s['settings'],'guest','reminder','email',$b));$this->postJson('/api/reminder-preferences/reset',['reset'=>true])->assertOk();$s=app(SalonStore::class)->read();$b=NotificationPreferences::withClient($s['bookings'][0],$s);$this->assertFalse(NotificationPreferences::enabled($s['settings'],'guest','reminder','email',$b));
 }
 public function test_opt_out_is_checked_again_for_queued_sms_and_other_events_are_unchanged():void{
  $s=app(SalonStore::class)->read();$s['settings']['channel']='mindkettő';$s['settings']['reminderGuest']=true;$b=$s['bookings'][0];$b['phone']='+36301234567';$p=['bookingId'=>'booking','event'=>'reminder','channel'=>'sms','audience'=>'guest','version'=>'v1','settingsVersion'=>1];$s['bookings'][0]=$b;$s['clients'][0]['reminderPreference']='off';$this->assertNull(app(Notifications::class)->smsContent($p,$s));$this->assertTrue(NotificationPreferences::enabled(['notifyGuest'=>true,'channel'=>'email','guestRemindersPaused'=>true],'guest','created','email',['reminderPreference'=>'off']));
 }
 public function test_invalid_and_unauthenticated_preferences_are_rejected():void{
  $this->postJson('/api/clients/one/reminder-preference',['preference'=>'bad'])->assertUnprocessable();$this->postJson('/api/clients/missing/reminder-preference',['preference'=>'off'])->assertNotFound();$this->withSession(['admin'=>false]);$this->postJson('/api/clients/one/reminder-preference',['preference'=>'off'])->assertForbidden();
 }
}
