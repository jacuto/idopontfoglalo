<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Artisan,DB,Hash};
use App\Services\{SalonStore,Tenant};
class InterfaceUpdatesTest extends TestCase
{
 public function createApplication(){ $app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app; }
 protected function setUp():void{
  parent::setUp();if(config('database.default')!=='sqlite')throw new \RuntimeException('Külön SQLite teszt szükséges.');Artisan::call('migrate',['--force'=>true]);DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode(SalonStore::initial())]);DB::table('salon_state')->insert(['id'=>2,'data'=>json_encode(SalonStore::initial())]);DB::table('providers')->insert(['id'=>2,'name'=>'Másik','password_hash'=>Hash::make('test-password-123'),'active'=>true,'trial_started_at'=>now(),'trial_ends_at'=>now()->addMonth(),'created_at'=>now(),'updated_at'=>now()]);
  foreach([10,11] as $id)DB::table('guest_accounts')->insert(['id'=>$id,'provider'=>'email','subject'=>(string)$id,'name'=>'Vendég '.$id,'profile_name'=>'Teszt '.$id,'email'=>'guest'.$id.'@example.com','email_verified'=>true,'created_at'=>now(),'updated_at'=>now()]);
  app(SalonStore::class)->mutate(function(&$s){$s['clients']=[['id'=>'client-one','guestId'=>10,'name'=>'Vendég 10','email'=>'guest10@example.com','phone'=>''],['id'=>'client-two','guestId'=>11,'name'=>'Vendég 11','email'=>'guest11@example.com','phone'=>'']];});
 }
 private function asProvider(int $id=1):void{$this->withSession(['admin'=>true,'provider_id'=>$id,'guest_id'=>null]);}
 private function asGuest(int $id=10):void{$this->withSession(['admin'=>false,'guest_id'=>$id]);}
 private function coupon():array{return ['id'=>'test-coupon-existing','code'=>'SECRET','name'=>'Titkos','startsAt'=>now()->subDay()->toISOString(),'endsAt'=>now()->addMonth()->toISOString(),'services'=>['cut'],'type'=>'percent','value'=>10,'active'=>true,'public'=>false,'firstVisit'=>false,'perClient'=>5,'limit'=>100];}
 public function test_discount_visibility_is_selection_aware_and_does_not_disclose_secret_codes_or_other_guests_passes():void{
  app(SalonStore::class)->mutate(function(&$s){$s['coupons']=[$this->coupon()];$s['clientPasses']=[['id'=>'owned-pass','clientId'=>'client-one','name'=>'Saját bérlet','services'=>['cut'],'remaining'=>2,'price'=>2000,'terms'=>'','validityMode'=>'assigned','days'=>30,'startsAt'=>now()->subDay()->toISOString(),'expiresAt'=>now()->addDays(20)->toISOString(),'reservations'=>[]]];});
  $this->getJson('/api/public/discounts?services[]=cut')->assertOk()->assertJsonPath('hasCoupon',true)->assertJsonCount(0,'passes')->assertJsonMissing(['code'=>'SECRET']);
  $this->getJson('/api/public/discounts')->assertExactJson(['hasCoupon'=>false,'passes'=>[]]);
  $this->asGuest();$this->getJson('/api/public/discounts?services[]=cut')->assertJsonPath('passes.0.id','owned-pass');
  $this->getJson('/api/public/discounts?services[]=massage')->assertExactJson(['hasCoupon'=>false,'passes'=>[]]);
  $this->getJson('/api/public/discounts?services[]=cut&start='.urlencode(now()->addDays(40)->toISOString()))->assertJsonCount(0,'passes');
  $this->asGuest(11);$this->getJson('/api/public/discounts?services[]=cut')->assertJsonCount(0,'passes');
  $this->asGuest();$this->getJson('/api/public/discounts?provider=2&services[]=cut')->assertExactJson(['hasCoupon'=>false,'passes'=>[]]);
 }
 public function test_catalog_deletion_preserves_issued_pass_and_old_booking_price_but_blocks_reactivation():void{
  $this->asProvider();$coupon=$this->coupon();$this->postJson('/api/coupons',$coupon)->assertCreated();
  $pass=$this->postJson('/api/passes',['name'=>'Öt alkalom','price'=>5000,'visits'=>5,'days'=>30,'services'=>['cut'],'active'=>true])->assertCreated()->json();
  $issued=$this->postJson('/api/client-passes',['clientId'=>'client-one','passId'=>$pass['id']])->assertCreated()->json();
  app(SalonStore::class)->mutate(function(&$s){$s['bookings'][]=['id'=>'old','name'=>'Régi','clientId'=>'client-one','guestId'=>10,'phone'=>'','email'=>'','start'=>now()->subDay()->toISOString(),'services'=>['cut'],'serviceSnapshot'=>[['id'=>'cut','price'=>8500,'duration'=>45]],'staff'=>'anna','status'=>'confirmed','pricing'=>['original'=>8500,'discount'=>850,'payable'=>7650,'couponId'=>'test-coupon-existing']];});
  $this->asProvider(2);$this->deleteJson('/api/coupons/test-coupon-existing?provider=2')->assertNotFound();$this->asGuest();$this->deleteJson('/api/coupons/test-coupon-existing')->assertUnauthorized();$this->asProvider();
  $this->deleteJson('/api/coupons/test-coupon-existing')->assertOk();$this->deleteJson('/api/passes/'.$pass['id'])->assertOk();
  $state=app(SalonStore::class)->read();$this->assertSame(7650,$state['bookings'][0]['pricing']['payable']);$this->assertSame($issued,$state['clientPasses'][0]);
  $this->postJson('/api/coupons',$coupon)->assertUnprocessable();$this->postJson('/api/client-passes',['clientId'=>'client-one','passId'=>$pass['id']])->assertNotFound();
  $this->asGuest();$this->getJson('/api/public/discounts?services[]=cut')->assertJsonPath('hasCoupon',false)->assertJsonPath('passes.0.id',$issued['id']);
  $this->postJson('/api/quote',['services'=>['cut'],'passId'=>$issued['id']])->assertOk()->assertJsonPath('payable',0);
 }
 public function test_team_to_solo_saves_after_loading_legacy_sample_notification_rules_without_losing_staff():void{
  app(SalonStore::class)->mutate(function(&$s){$rules=['guest'=>[],'provider'=>[]];foreach(['guest','provider'] as $audience)foreach([...\App\Services\NotificationPreferences::EVENTS,'reminder'] as $event)$rules[$audience][$event]=['email'=>false,'sms'=>false];$s['settings']['notificationRules']=$rules;});
  $this->asProvider();$state=$this->getJson('/api/state')->assertOk()->json();$this->assertArrayNotHasKey('reminder',$state['settings']['notificationRules']['provider']);
  $settings=$state['settings'];$settings['businessMode']='solo';$this->putJson('/api/config',['settings'=>$settings])->assertOk();
  $s=app(SalonStore::class)->read();$this->assertSame('solo',$s['settings']['businessMode']);$this->assertCount(2,$s['staff']);$this->assertFalse($s['settings']['notificationRules']['guest']['reminder']['sms']);
 }
 public function test_message_deletion_is_private_to_viewer_read_receipts_and_authorization_are_preserved():void{
  app(SalonStore::class)->mutate(function(&$s){$s['messages']=[['id'=>'m-one','clientId'=>'client-one','guestId'=>10,'sender'=>'provider','text'=>'Privát','createdAt'=>now()->toISOString(),'readAt'=>null]];});
  $this->asGuest(11);$this->deleteJson('/api/messages',['id'=>'m-one'])->assertNotFound();
  $this->asGuest();$this->patchJson('/api/messages',[])->assertOk();$this->getJson('/api/messages')->assertJsonCount(1)->assertJsonPath('0.senderName','Harmónia Szalon');
  $this->asProvider();$this->getJson('/api/messages')->assertJson(fn($json)=>$json->has(1)->where('0.readAt',fn($v)=>is_string($v))->etc());
  $this->deleteJson('/api/messages',['id'=>'m-one'])->assertOk();$this->getJson('/api/messages')->assertExactJson([]);$this->getJson('/api/state')->assertJsonCount(0,'messages');
  $this->asGuest();$this->getJson('/api/messages')->assertJsonCount(1);$this->deleteJson('/api/messages',['id'=>'m-one'])->assertOk();$this->getJson('/api/messages')->assertExactJson([]);
  $this->asProvider(2);$this->deleteJson('/api/messages?provider=2',['id'=>'m-one'])->assertNotFound();
  $state=app(Tenant::class)->run(1,fn()=>app(SalonStore::class)->read());$this->assertSame('Privát',$state['messages'][0]['text']);$this->assertNotEmpty($state['messages'][0]['providerDeletedAt']);$this->assertNotEmpty($state['messages'][0]['guestDeletedAt']);
 }
}
