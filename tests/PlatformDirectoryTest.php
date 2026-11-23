<?php
namespace Tests;
use App\Services\SalonStore;
use Illuminate\Support\Facades\{Artisan,DB,Http};
class PlatformDirectoryTest extends \Illuminate\Foundation\Testing\TestCase
{
 public function createApplication(){$a=require __DIR__.'/../bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $a;}
 protected function setUp():void{parent::setUp();Artisan::call('migrate',['--force'=>true]);$s=SalonStore::initial();$s['settings']['name']='Tóth Tamás';DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode($s)]);$this->withSession(['platform_admin'=>true]);Http::preventStrayRequests();}
 public function test_recipient_lookup_is_admin_only_bounded_and_accepts_names_emails_and_exact_ids():void{
  for($i=1;$i<=35;$i++)DB::table('guest_accounts')->insert(['id'=>$i,'name'=>'Guest '.$i,'email'=>'guest'.$i.'@example.com','provider'=>'google','subject'=>(string)$i,'created_at'=>now(),'updated_at'=>now()]);
  $this->getJson('/api/platform/recipients?q=Gu')->assertOk()->assertJsonCount(0,'items');$this->getJson('/api/platform/recipients?q=Guest')->assertJsonCount(10,'items');$this->getJson('/api/platform/recipients?q=guest35%40')->assertJsonPath('items.0.id','guest:35');$this->getJson('/api/platform/recipients?q=Toth')->assertJsonPath('items.0.id','provider:1');$this->getJson('/api/platform/recipients?q=%231')->assertJsonCount(2,'items');
  $this->getJson('/api/platform/recipients?q[]=bad')->assertUnprocessable();$this->withSession(['platform_admin'=>false]);$this->getJson('/api/platform/recipients?q=Guest')->assertUnauthorized();
 }
 public function test_issue_paging_reaches_records_past_200_and_size_is_fixed_at_30():void{
  for($i=1;$i<=211;$i++)DB::table('system_issues')->insert(['fingerprint'=>hash('sha256',(string)$i),'category'=>'application','provider_id'=>1,'code'=>'Error','message'=>'Example '.$i,'first_seen_at'=>now(),'last_seen_at'=>now()->subSeconds($i)]);
  $this->getJson('/api/platform/issues?page=1&perPage=999')->assertOk()->assertJsonCount(30,'issues')->assertJsonPath('pagination.total',211)->assertJsonPath('pagination.perPage',30);$this->getJson('/api/platform/issues?page=8')->assertJsonCount(1,'issues')->assertJsonPath('issues.0.message','Example 211');$this->getJson('/api/platform/issues?page=999')->assertJsonPath('pagination.page',8);$this->getJson('/api/platform/issues?page=0')->assertUnprocessable();$this->withSession(['platform_admin'=>false]);$this->getJson('/api/platform/issues?page=1')->assertForbidden();
 }
}
