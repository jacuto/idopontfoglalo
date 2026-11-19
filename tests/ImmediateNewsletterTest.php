<?php
namespace Tests;
use App\Services\{MarketingConnections,MarketingCampaigns,MarketingGateway,MarketingException};
use Illuminate\Support\Facades\{Artisan,DB,Http};
use Carbon\CarbonImmutable as Time;
class ImmediateNewsletterTest extends \Illuminate\Foundation\Testing\TestCase
{
 public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
 protected function setUp():void{parent::setUp();Artisan::call('migrate',['--force'=>true]);Time::setTestNow('2036-05-05T08:00:00Z');$this->withSession(['platform_admin'=>true]);MarketingConnections::save(0,['engine'=>'brevo','apiKey'=>'test','senderName'=>'Teszt','senderEmail'=>'sender@example.com','company'=>'Teszt','listId'=>'1','folderId'=>1]);DB::table('newsletter_consents')->insert(['audience'=>'provider','subject_id'=>1,'email'=>'guest@example.com','consented_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);Http::preventStrayRequests();Http::fake(fn()=>Http::response(['id'=>123]));}
 protected function tearDown():void{Time::setTestNow();parent::tearDown();}
 private function ready():string{$id=$this->postJson('/api/platform/marketing/campaigns',['subject'=>'Hír','html'=>'<p>Teszt</p>'])->assertCreated()->json('id');$this->postJson('/api/platform/marketing/campaigns/'.$id.'/prepare',['recipientIds'=>[1],'interactive'=>true])->assertOk()->assertJsonPath('status','ready');return $id;}
 public function test_interactive_prepare_and_immediate_send_run_without_cron_and_duplicate_click_does_not_resend():void{
  $id=$this->ready();Http::assertNotSent(fn($r)=>str_contains($r->url(),'/sendNow'));$url='/api/platform/marketing/campaigns/'.$id.'/send';$this->postJson($url,['mode'=>'now','confirm'=>false])->assertUnprocessable();$this->postJson($url,['mode'=>'now','confirm'=>true])->assertOk()->assertJsonPath('status','submitted');$this->postJson($url,['mode'=>'now','confirm'=>true])->assertOk()->assertJsonPath('status','submitted');$this->assertCount(1,Http::recorded(fn($r)=>str_contains($r->url(),'/sendNow')));
 }
 public function test_scheduled_send_waits_for_due_time_and_worker_handles_it_once():void{
  $id=$this->ready();$url='/api/platform/marketing/campaigns/'.$id.'/send';$this->postJson($url,['mode'=>'scheduled','confirm'=>true])->assertUnprocessable();$this->postJson($url,['mode'=>'scheduled','confirm'=>true,'scheduledAt'=>now()->addHour()->toISOString()])->assertOk();MarketingCampaigns::run();Http::assertNotSent(fn($r)=>str_contains($r->url(),'/sendNow'));Time::setTestNow(Time::now()->addHours(2));MarketingCampaigns::run();MarketingCampaigns::run();$this->assertCount(1,Http::recorded(fn($r)=>str_contains($r->url(),'/sendNow')));
 }
 public function test_targeted_run_never_prepares_another_campaign_and_uncertain_send_is_not_retried():void{
  $other=$this->postJson('/api/platform/marketing/campaigns',['subject'=>'Other','html'=>'x'])->json('id');$this->postJson('/api/platform/marketing/campaigns/'.$other.'/prepare',['recipientIds'=>[1]])->assertOk();$id=$this->ready();$this->assertDatabaseHas('marketing_campaigns',['id'=>$other,'status'=>'preparing']);Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(fn()=>Http::response(['detail'=>'Sender domain is not verified'],400));$url='/api/platform/marketing/campaigns/'.$id.'/send';$this->postJson($url,['mode'=>'now','confirm'=>true])->assertOk()->assertJsonPath('status','unknown');$this->assertStringContainsString('hitelesítve',DB::table('marketing_campaigns')->find($id)->error);$this->postJson($url,['mode'=>'now','confirm'=>true])->assertUnprocessable();$this->assertDatabaseHas('marketing_campaigns',['id'=>$other,'status'=>'preparing']);
 }
 public function test_gateway_reports_safe_specific_error_without_echoing_credentials():void{
  foreach([[401,'API key invalid','API-kulcs'],[400,'Monthly sending limit reached','keret'],[403,'Account suspended','jogosultsága'],[400,'Invalid HTML content','tartalmát']] as [$status,$detail,$expected]){Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(fn()=>Http::response(['detail'=>$detail.' secret-not-for-display'],$status));try{(new MarketingGateway(['engine'=>'mailchimp','apiKey'=>'secret','dc'=>'us21']))->call('GET','account');$this->fail('Expected error');}catch(MarketingException $e){$this->assertStringContainsString($expected,$e->getMessage());$this->assertStringContainsString('HTTP '.$status,$e->getMessage());$this->assertStringNotContainsString('secret-not-for-display',$e->getMessage());}}
 }
}
