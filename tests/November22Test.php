<?php
namespace Tests;
use App\Services\{SalonStore,Clients,ContactImport,CalendarImport};
use Illuminate\Support\Facades\{Artisan,Cache,DB,Http};
class November22Test extends \Illuminate\Foundation\Testing\TestCase
{
 public function createApplication(){$a=require __DIR__.'/../bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $a;}
 protected function setUp():void{parent::setUp();Artisan::call('migrate',['--force'=>true]);Cache::flush();$s=SalonStore::initial();$s['clients']=[['id'=>'tamas','name'=>'Tóth Tamás','email'=>'tamas@example.com','phone'=>'06702355412','guestId'=>null],['id'=>'other','name'=>'Másik Vendég','email'=>'other@example.com','phone'=>'06702355412','guestId'=>null]];DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode($s)]);$this->withSession(['admin'=>true,'provider_id'=>1]);Http::preventStrayRequests();}
 private function record(array $v=[]):array{return array_replace(['name'=>'XY','phone'=>'06702355412','email'=>''],$v);}
 public function test_email_is_case_insensitive_unique_but_phone_is_shared_and_blank_emails_are_allowed():void{
  $this->postJson('/api/clients',$this->record(['email'=>'TAMAS@example.com']))->assertUnprocessable()->assertJsonPath('code','client_email_conflict')->assertJsonPath('matches.0.id','tamas');
  $this->postJson('/api/clients',$this->record())->assertCreated();$this->postJson('/api/clients',$this->record(['name'=>'Családtag']))->assertCreated();$this->assertCount(4,app(SalonStore::class)->read()['clients']);
 }
 public function test_live_matches_and_search_work_independently_of_name_and_exclude_selected_record():void{
  $this->getJson('/api/clients/matches?email=TAMAS%40example.com&phone=%2B36702355412')->assertOk()->assertJsonPath('email.0.id','tamas')->assertJsonCount(2,'phone');
  $this->getJson('/api/clients/matches?email=tamas%40example.com&exclude=tamas')->assertOk()->assertJsonCount(0,'email');
  $this->getJson('/api/clients/search?q=Tam%C3%A1s%20T%C3%B3th')->assertOk()->assertJsonPath('0.id','tamas');$this->getJson('/api/clients/search?q=tamas%40')->assertJsonPath('0.id','tamas');$this->getJson('/api/clients/search?q=Ta')->assertExactJson([]);
 }
 public function test_contact_checks_do_not_expose_other_tenant_records_or_public_results():void{
  $this->withSession(['admin'=>false]);$this->getJson('/api/clients/matches?email=tamas%40example.com')->assertForbidden();$this->withSession(['admin'=>true,'provider_id'=>2]);$this->getJson('/api/clients/matches?provider=1&email=tamas%40example.com')->assertForbidden();
 }
 public function test_all_three_contact_policies_have_read_only_previews_and_explicit_commit():void{
  foreach(['fill','overwrite','skip'] as $policy){$before=app(SalonStore::class)->read();$preview=$this->postJson('/api/contact-import/preview',['source'=>'file','policy'=>$policy,'records'=>[['name'=>'Új név','email'=>'tamas@example.com','phone'=>'06305550101']]])->assertOk()->assertJsonPath('summary.matched',1)->json();$this->assertSame($before,app(SalonStore::class)->read());
   $result=$this->postJson('/api/contact-import/commit',['id'=>$preview['id'],'selected'=>[0]])->assertOk()->json();$after=app(SalonStore::class)->read();$this->assertSame($policy==='overwrite'?'Új név':$before['clients'][0]['name'],$after['clients'][0]['name']);$this->assertSame($policy==='overwrite'?'06305550101':$before['clients'][0]['phone'],$after['clients'][0]['phone']);
   $this->postJson('/api/contact-import/commit',['id'=>$preview['id'],'selected'=>[0]])->assertExactJson($result);
  }$this->assertDatabaseCount('deliveries',0);
 }
 public function test_shared_phone_import_requires_resolution_and_never_merges_two_profiles_implicitly():void{
  $p=$this->postJson('/api/contact-import/preview',['source'=>'phone','records'=>[$this->record()]])->assertOk()->assertJsonPath('items.0.status','conflict')->assertJsonCount(2,'items.0.candidates')->json();
  $this->postJson('/api/contact-import/commit',['id'=>$p['id'],'selected'=>[0]])->assertJsonPath('skipped',1);
  $p=$this->postJson('/api/contact-import/preview',['source'=>'phone','resolutions'=>['new'],'records'=>[$this->record()]])->assertOk()->assertJsonPath('items.0.status','new')->json();$this->postJson('/api/contact-import/commit',['id'=>$p['id'],'selected'=>[0]])->assertJsonPath('added',1);
 }
 public function test_import_overwrite_keeps_empty_cells_and_account_links_untouched():void{
  $before=app(SalonStore::class)->read();$p=ContactImport::preview($before['clients'],[['name'=>'','email'=>'tamas@example.com','phone'=>'']],'overwrite');$this->assertSame('same',$p[0]['status']);$this->assertSame([],$p[0]['changes']);
 }
 public function test_record_import_preview_does_not_save_and_policy_is_bound_to_staged_request():void{
  $before=app(SalonStore::class)->read();$preview=$this->postJson('/api/imports/clients/preview',['policy'=>'overwrite','records'=>[['id'=>'tamas','name'=>'Új megnevezés','phone'=>'','email'=>'tamas@example.com']]])->assertOk()->assertJsonPath('summary.matched',1)->json();$this->assertSame($before,app(SalonStore::class)->read());
  $result=$this->postJson('/api/imports/clients/commit',['id'=>$preview['id'],'selected'=>[0],'policy'=>'skip'])->assertOk()->assertJsonPath('updated',1)->json();$s=app(SalonStore::class)->read();$this->assertSame('Új megnevezés',$s['clients'][0]['name']);$this->assertSame('06702355412',$s['clients'][0]['phone']);$this->postJson('/api/imports/clients/commit',['id'=>$preview['id'],'selected'=>[0]])->assertExactJson($result);$this->assertDatabaseCount('deliveries',0);
 }
 public function test_record_import_concurrent_changes_are_not_overwritten():void{
  $p=$this->postJson('/api/imports/clients/preview',['policy'=>'overwrite','records'=>[['id'=>'tamas','name'=>'CSV','email'=>'tamas@example.com']]])->json();app(SalonStore::class)->mutate(function(&$s){$s['clients'][0]['name']='Közben javítva';});$this->postJson('/api/imports/clients/commit',['id'=>$p['id'],'selected'=>[0]])->assertJsonPath('skipped',1);$this->assertSame('Közben javítva',app(SalonStore::class)->read()['clients'][0]['name']);
 }
 public function test_calendar_policies_skip_existing_or_replace_only_tracked_imported_blocks():void{
  $s=app(SalonStore::class)->read();$event=['eventId'=>'one','title'=>'Eredeti','start'=>'2035-03-12T09:00:00Z','end'=>'2035-03-12T10:00:00Z','allDay'=>false];$choice=['index'=>0,'staff'=>'anna','mode'=>'block','contact'=>false];
  CalendarImport::apply($s,[$event],[$choice],'calendar','fill');$count=count($s['availability']['blocks']);$event['title']='Frissített';$r=CalendarImport::apply($s,[$event],[$choice],'calendar','skip');$this->assertSame(1,$r['skipped']);$this->assertSame($count,count($s['availability']['blocks']));
  CalendarImport::apply($s,[$event],[$choice],'calendar','overwrite');$this->assertSame($count,count($s['availability']['blocks']));$this->assertStringContainsString('Frissített',end($s['availability']['blocks'])['label']);
 }
 public function test_deep_workspace_routes_are_loadable_and_keep_authentication_required_for_data():void{
  foreach(['/munkaterulet/naptar?provider=1','/munkaterulet/beallitasok/alapbeallitasok/belepes-es-biztonsag?provider=1','/munkaterulet/beallitasok/integraciok/szamlazas?provider=1'] as $url)$this->get($url)->assertOk();$this->withSession(['admin'=>false]);$this->getJson('/api/state')->assertUnauthorized();
 }
 public function test_single_shared_phone_with_different_name_needs_a_choice_and_can_create_a_new_person():void{
  $clients=[['id'=>'one','name'=>'Tóth Tamás','email'=>'','phone'=>'06702355412']];
  $items=ContactImport::preview($clients,[$this->record()]);$this->assertSame('conflict',$items[0]['status']);$this->assertTrue($items[0]['canCreate']);
  $items=ContactImport::preview($clients,[$this->record()],'fill',['new']);$this->assertSame('new',$items[0]['status']);
  $s=app(SalonStore::class)->read();$s['clients']=$clients;
  $items=\App\Services\RecordImport::preview($s,'clients',[$this->record()],'fill');$this->assertSame('conflict',$items[0]['status']);
  $items=\App\Services\RecordImport::preview($s,'clients',[$this->record(['_newContact'=>true])],'fill');$this->assertSame('new',$items[0]['status']);
 }
 public function test_summary_only_contact_preview_is_read_only():void{
  $before=app(SalonStore::class)->read();$response=$this->postJson('/api/contact-import/preview',['source'=>'file','summaryOnly'=>true,'records'=>[$this->record(['email'=>'tamas@example.com','phone'=>''])]])->assertOk()->assertJsonPath('summary.matched',1);$this->assertArrayNotHasKey('id',$response->json());$this->assertSame($before,app(SalonStore::class)->read());
 }
 public function test_import_cannot_overwrite_a_booking_linked_to_a_payment():void{
  $s=app(SalonStore::class)->read();$s['bookings']=[['id'=>'paid','name'=>'Tóth Tamás','start'=>'2035-03-12T09:00:00Z']];$s['payments']=[['bookingId'=>'paid','status'=>'paid']];
  $items=\App\Services\RecordImport::preview($s,'bookings',[['id'=>'paid','name'=>'Megváltoztatva']],'overwrite');$this->assertSame('conflict',$items[0]['status']);$this->assertStringContainsString('Befizetéshez',$items[0]['message']);$this->assertSame('Tóth Tamás',$s['bookings'][0]['name']);
 }

}
