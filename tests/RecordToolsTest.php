<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Artisan,DB,Hash,Http};
use App\Services\{SalonStore,Tenant,CatalogInsights};
class RecordToolsTest extends TestCase
{
 public function createApplication(){ $app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app; }
 protected function setUp():void{parent::setUp();if(config('database.default')!=='sqlite')throw new \RuntimeException('Külön SQLite szükséges.');Http::preventStrayRequests();Artisan::call('migrate',['--force'=>true]);foreach([1,2] as $id)DB::table('salon_state')->insert(['id'=>$id,'data'=>json_encode(SalonStore::initial())]);DB::table('providers')->insert(['id'=>2,'name'=>'Másik','password_hash'=>Hash::make('test-password-123'),'active'=>true,'trial_started_at'=>now(),'trial_ends_at'=>now()->addMonth(),'created_at'=>now(),'updated_at'=>now()]);$this->withSession(['admin'=>true,'provider_id'=>1]);app(SalonStore::class)->mutate(function(&$s){$s['clients']=[['id'=>'one','guestId'=>10,'name'=>'Vendég','phone'=>'','email'=>'']];$s['bookings']=[$this->booking()];$s['payments']=[['id'=>'manual-one','bookingId'=>'book-one','amount'=>3000,'method'=>'cash','paidAt'=>now()->toISOString(),'status'=>'paid','source'=>'manual']];});}
 private function booking(array $v=[]):array{return array_replace(['id'=>'book-one','clientId'=>'one','guestId'=>10,'name'=>'Vendég','phone'=>'','email'=>'','staff'=>'anna','services'=>['cut'],'serviceSnapshot'=>[['id'=>'cut','name'=>'Hajvágás','price'=>10000,'duration'=>45]],'status'=>'confirmed','start'=>now()->subDays(2)->setTime(10,0)->toISOString(),'pricing'=>['original'=>10000,'discount'=>0,'payable'=>10000]],$v);}
 public function test_manual_payment_edit_and_delete_are_atomic_audited_and_versioned():void{
  $v=['version'=>0,'amount'=>4000,'method'=>'transfer','paidAt'=>now()->toISOString(),'note'=>'Javítás'];$this->patchJson('/api/payments/manual-one',$v)->assertOk()->assertJsonPath('version',1);
  $this->patchJson('/api/payments/manual-one',$v)->assertConflict();$this->patchJson('/api/payments/manual-one',array_replace($v,['version'=>1,'amount'=>11000]))->assertUnprocessable();
  $s=app(SalonStore::class)->read();$this->assertSame(4000,$s['payments'][0]['amount']);$this->assertSame(3000,$s['payments'][0]['history'][0]['before']['amount']);$this->assertSame(6000,\App\Services\PaymentDues::rows($s)[0]['amount']);
  $this->deleteJson('/api/payments/manual-one',['version'=>1])->assertOk();$s=app(SalonStore::class)->read();$this->assertSame('void',$s['payments'][0]['status']);$this->assertCount(2,$s['payments'][0]['history']);$this->assertSame(10000,\App\Services\PaymentDues::rows($s)[0]['amount']);
 }
 public function test_gateway_invoice_and_other_tenant_payments_cannot_be_rewritten():void{
  $v=['version'=>0,'amount'=>2000,'method'=>'cash','paidAt'=>now()->toISOString()];$this->patchJson('/api/payments/manual-one?provider=2',$v)->assertForbidden();
  app(SalonStore::class)->mutate(function(&$s){$s['payments'][0]['source']='stripe';});$this->patchJson('/api/payments/manual-one',$v+['source'=>'manual'])->assertUnprocessable();$this->deleteJson('/api/payments/manual-one',['version'=>0])->assertUnprocessable();
  app(SalonStore::class)->mutate(function(&$s){$s['payments'][0]['source']='manual';$s['invoiceRequests']=[['id'=>'invoice-one','paymentId'=>'manual-one','bookingId'=>'book-one','status'=>'issued']];});
  $this->patchJson('/api/payments/manual-one',$v)->assertUnprocessable();$this->deleteJson('/api/payments/manual-one',['version'=>0])->assertUnprocessable();$this->postJson('/api/payments/manual-one/void',[])->assertUnprocessable();$this->assertSame(3000,app(SalonStore::class)->read()['payments'][0]['amount']);
 }
 public function test_issued_pass_correction_and_revocation_preserve_use_and_block_new_reservations():void{
  $type=$this->postJson('/api/passes',['name'=>'Bérlet','price'=>5000,'visits'=>5,'days'=>30,'services'=>['cut'],'active'=>true])->assertCreated()->json();$p=$this->postJson('/api/client-passes',['clientId'=>'one','passId'=>$type['id']])->assertCreated()->json();$this->assertSame($type['id'],$p['passTypeId']);
  $this->patchJson('/api/client-passes/'.$p['id'],['version'=>0,'remaining'=>3,'expiresAt'=>$p['expiresAt']])->assertOk()->assertJsonPath('remaining',3)->assertJsonPath('version',1);
  $this->patchJson('/api/client-passes/'.$p['id'],['version'=>0,'remaining'=>4])->assertConflict();
  $future=$this->booking(['id'=>'future','start'=>now()->addDays(4)->setTime(10,0)->toISOString(),'passId'=>$p['id']]);app(SalonStore::class)->mutate(function(&$s)use($future,$p){$s['bookings'][]=$future;$s['clientPasses'][0]['reservations'][$future['id']]='reserved';});
  $this->deleteJson('/api/client-passes/'.$p['id'],['version'=>1])->assertUnprocessable();$this->patchJson('/api/client-passes/'.$p['id'],['version'=>1,'remaining'=>3,'expiresAt'=>now()->addDay()->toISOString()])->assertUnprocessable();
  app(SalonStore::class)->mutate(function(&$s){$s['bookings'][1]['status']='cancelled';});$this->deleteJson('/api/client-passes/'.$p['id'],['version'=>1])->assertOk();$s=app(SalonStore::class)->read();$this->assertSame(3,$s['clientPasses'][0]['remaining']);$this->assertNotEmpty($s['clientPasses'][0]['revokedAt']);$this->assertCount(2,$s['clientPasses'][0]['history']);
  $this->postJson('/api/quote',['services'=>['cut'],'clientId'=>'one','passId'=>$p['id']])->assertUnprocessable();
 }
 public function test_catalog_insights_use_snapshot_discount_and_do_not_invent_pass_revenue():void{
  app(SalonStore::class)->mutate(function(&$s){$s['coupons']=[['id'=>'coupon','name'=>'Tíz százalék']];$s['passes']=[['id'=>'type','name'=>'Öt alkalom','price'=>5000]];$s['bookings'][0]['pricing']=['original'=>10000,'discount'=>1000,'payable'=>9000,'couponId'=>'coupon'];$s['bookings'][]=$this->booking(['id'=>'used-pass','passId'=>'issued','pricing'=>['original'=>3000,'discount'=>3000,'payable'=>0]]);$s['clientPasses']=[['id'=>'issued','passTypeId'=>'type','clientId'=>'one','name'=>'Öt alkalom','price'=>5000,'remaining'=>3,'initialVisits'=>5,'reservations'=>['used-pass'=>'consumed','reserved-one'=>'reserved']]];});
  $d=$this->getJson('/api/catalog-insights')->assertOk()->json();$this->assertSame(1,$d['coupons'][0]['guests']);$this->assertSame(1000,$d['coupons'][0]['discount']);$this->assertSame(10,$d['coupons'][0]['discountPercent']);$this->assertSame(3000,$d['coupons'][0]['paid']);$this->assertSame(5000,$d['passes'][0]['value']);$this->assertSame(2000,$d['passes'][0]['discount']);$this->assertSame(1,$d['passes'][0]['consumed']);$this->assertSame(1,$d['passes'][0]['reserved']);
  $this->getJson('/api/catalog-insights?provider=2')->assertForbidden();
  app(SalonStore::class)->mutate(function(&$s){$s['clientPasses'][0]['revokedAt']=now()->toISOString();});$after=$this->getJson('/api/catalog-insights')->assertOk()->json();$this->assertSame(2000,$after['passes'][0]['discount']);$this->assertSame(1,$after['passes'][0]['usedGuests']);$this->assertSame(0,$after['passes'][0]['issued']);
  $this->withSession(['admin'=>false]);$this->getJson('/api/catalog-insights')->assertUnauthorized();
 }
 public function test_service_archive_keeps_old_booking_snapshot_and_resets_default():void{
  app(SalonStore::class)->mutate(function(&$s){$s['settings']['defaultServiceId']='cut';});$this->deleteJson('/api/services/cut')->assertOk();$s=app(SalonStore::class)->read();$this->assertTrue($s['services'][0]['archived']);$this->assertNull($s['settings']['defaultServiceId']);$this->assertSame(10000,$s['bookings'][0]['serviceSnapshot'][0]['price']);$this->getJson('/api/public')->assertJsonMissing(['id'=>'cut']);
 }
}
