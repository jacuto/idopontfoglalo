<?php
namespace Tests;
use App\Services\{NewsletterDesign,MarketingConnections};
use Illuminate\Support\Facades\{Artisan,DB,Http};
class NewsletterDesignTest extends \Illuminate\Foundation\Testing\TestCase
{
 public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
 protected function setUp():void{parent::setUp();Artisan::call('migrate',['--force'=>true]);Http::preventStrayRequests();$this->withSession(['platform_admin'=>true]);MarketingConnections::save(0,['engine'=>'brevo','apiKey'=>'test','senderName'=>'Teszt']);}
 public function test_layout_sanitizes_content_and_preserves_accessible_links_and_unsubscribe():void{
  foreach(['{{ unsubscribe }}','*|UNSUB|*'] as $token){$html=NewsletterDesign::render('<h2>Hír</h2><p onclick="bad()">Szöveg <a href="https://example.com">Foglalás</a></p><script>bad()</script><img src=x><a href="javascript:bad()">rossz</a>',['company'=>'A & B','address'=>'<test>'],$token);$this->assertStringContainsString('role="presentation"',$html);$this->assertStringContainsString('max-width:600px',$html);$this->assertStringContainsString('A &amp; B',$html);$this->assertStringContainsString('href="'.$token.'"',$html);$this->assertStringContainsString('href="https://example.com"',$html);$this->assertStringNotContainsString('onclick',$html);$this->assertStringNotContainsString('<script',$html);$this->assertStringNotContainsString('<img',$html);$this->assertStringNotContainsString('javascript:',$html);}
 }
 public function test_gateway_submits_email_layout_and_plain_text_to_both_engines_without_sending():void{
  foreach(['brevo','mailchimp'] as $engine){
   Http::fake(fn($request)=>Http::response(['id'=>123]));
   $keys=['engine'=>$engine,'apiKey'=>'fake','dc'=>'us21','folderId'=>1,'senderName'=>'Teszt','senderEmail'=>'sender@example.com','company'=>'Cég'];
   (new \App\Services\MarketingGateway($keys))->create((object)['id'=>'campaign','provider_id'=>0,'subject'=>'Hír','html'=>'<h2>Hír</h2><p><a href="https://example.com">Foglalás</a></p>'],'1',[['email'=>'guest@example.com']]);
   Http::assertSent(function($request)use($engine){$data=$request->data();$html=$data[$engine==='brevo'?'htmlContent':'html']??'';$text=$data[$engine==='brevo'?'textContent':'plain_text']??'';return str_contains($html,'role="presentation"')&&str_contains($html,'Leiratkozás')&&str_contains($text,'https://example.com')&&!str_contains($text,'<h2>');});
   Http::assertNotSent(fn($request)=>str_contains($request->url(),'sendNow')||str_contains($request->url(),'/actions/send'));
  }
 }
 private function campaign(string $status,int $provider=0):string{$id=(string)\Illuminate\Support\Str::uuid();DB::table('marketing_campaigns')->insert(['id'=>$id,'provider_id'=>$provider,'engine'=>'brevo','subject'=>'Régi','html'=>'<p>Tartalom</p>','status'=>$status,'remote_id'=>'remote','recipient_count'=>1,'created_at'=>now(),'updated_at'=>now()]);return $id;}
 public function test_edit_ready_resets_preparation_but_sent_and_scheduled_are_protected():void{
  $id=$this->campaign('ready');$this->postJson('/api/platform/marketing/campaigns/'.$id,['subject'=>'Új','html'=>'<h2>Új</h2>'])->assertOk();$this->assertDatabaseHas('marketing_campaigns',['id'=>$id,'status'=>'draft','remote_id'=>null,'recipient_count'=>0]);foreach(['submitted','scheduled','processing'] as $status){$id=$this->campaign($status);$this->postJson('/api/platform/marketing/campaigns/'.$id,['subject'=>'Új','html'=>'x'])->assertUnprocessable();}
 }
 public function test_delete_is_tenant_scoped_and_keeps_audit_record():void{
  $id=$this->campaign('submitted');$this->postJson('/api/platform/marketing/campaigns/'.$id.'/delete')->assertOk();$this->assertNotNull(DB::table('marketing_campaigns')->where('id',$id)->value('deleted_at'));$this->assertSame('submitted',DB::table('marketing_campaigns')->where('id',$id)->value('status'));$this->getJson('/api/platform/marketing/campaigns')->assertJsonCount(0,'campaigns');$other=$this->campaign('draft',2);$this->postJson('/api/platform/marketing/campaigns/'.$other.'/delete')->assertNotFound();foreach(['scheduled','processing','unknown'] as $status){$id=$this->campaign($status);$this->postJson('/api/platform/marketing/campaigns/'.$id.'/delete')->assertUnprocessable();}
 }
}
