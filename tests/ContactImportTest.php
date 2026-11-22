<?php
namespace Tests;

use App\Services\{ContactFiles,ContactImport,MailContacts,SalonStore,Tenant};
use Illuminate\Support\Facades\{Artisan,Cache,DB,Http};
use Illuminate\Http\UploadedFile;

class ContactImportTest extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
    protected function setUp():void{parent::setUp();Artisan::call('migrate',['--force'=>true]);Cache::flush();$s=SalonStore::initial();$s['clients']=[['id'=>'one','name'=>'Existing','phone'=>'06301234567','email'=>'','guestId'=>null],['id'=>'two','name'=>'Other','phone'=>'06701234567','email'=>'two@example.com','guestId'=>null]];DB::table('salon_state')->insert(['id'=>1,'data'=>json_encode($s)]);$this->withSession(['admin'=>true,'provider_id'=>1]);Http::preventStrayRequests();}
    public function test_preview_is_read_only_and_matches_normalized_contacts_without_exposing_global_guest_data():void
    {
        DB::table('guest_accounts')->insert(['id'=>90,'provider'=>'email','subject'=>'ninety','email'=>'new@example.com','name'=>'Private account name','email_verified'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $before=app(SalonStore::class)->read();$data=$this->postJson('/api/contact-import/preview',['source'=>'phone','resolutions'=>['one'],'records'=>[
            ['name'=>'Phone nickname','phone'=>'+36 30 123 4567','email'=>'existing@example.com'],
            ['name'=>'New person','email'=>'new@example.com','phone'=>''],
            ['name'=>'Conflict','phone'=>'06301234567','email'=>'two@example.com'],
            ['name'=>'Duplicate','email'=>'new@example.com'],
            ['name'=>'Invalid','phone'=>'123'],
        ]])->assertOk()->assertJsonPath('items.0.status','fill')->assertJsonPath('items.0.changes.email','existing@example.com')->assertJsonMissingPath('items.0.changes.name')->assertJsonPath('items.1.status','new')->assertJsonPath('items.2.status','conflict')->assertJsonPath('items.3.status','duplicate')->assertJsonPath('items.4.status','invalid')->json();
        $this->assertSame($before,app(SalonStore::class)->read());$this->assertStringNotContainsString('Private account name',json_encode($data));
        $result=$this->postJson('/api/contact-import/commit',['id'=>$data['id'],'selected'=>[0,1,2]])->assertOk()->assertJsonPath('added',1)->assertJsonPath('updated',1)->assertJsonPath('skipped',1)->json();
        $s=app(SalonStore::class)->read();$this->assertSame('Existing',$s['clients'][0]['name']);$this->assertSame('existing@example.com',$s['clients'][0]['email']);$this->assertNull($s['clients'][2]['guestId']);$this->assertSame('New person',$s['clients'][2]['name']);$this->assertDatabaseCount('deliveries',0);$this->assertNotEmpty($s['contactImport']['phone']['doneAt']);
        $this->postJson('/api/contact-import/commit',['id'=>$data['id'],'selected'=>[0,1,2]])->assertOk()->assertExactJson($result);$this->assertCount(3,app(SalonStore::class)->read()['clients']);
    }
    public function test_conflicting_contact_values_are_not_overwritten_and_concurrent_changes_are_rechecked():void
    {
        $a=ContactImport::inspect(app(SalonStore::class)->read()['clients'],['name'=>'Wrong','phone'=>'06301234567','email'=>'two@example.com']);$this->assertSame('conflict',$a['status']);
        $data=$this->postJson('/api/contact-import/preview',['source'=>'mail','records'=>[['name'=>'Existing','phone'=>'06301234567','email'=>'fill@example.com']]])->json();
        app(SalonStore::class)->mutate(function(&$s){$s['clients'][0]['email']='concurrent@example.com';});
        $this->postJson('/api/contact-import/commit',['id'=>$data['id'],'selected'=>[0]])->assertOk()->assertJsonPath('updated',0)->assertJsonPath('skipped',1);$this->assertSame('concurrent@example.com',app(SalonStore::class)->read()['clients'][0]['email']);
    }
    public function test_import_stages_are_scoped_to_provider_and_session_expire_and_cannot_set_account_or_notification_fields():void
    {
        $data=$this->postJson('/api/contact-import/preview',['source'=>'file','records'=>[['name'=>'Imported','email'=>'x@example.com','guestId'=>99,'reminderPreference'=>'on','consent'=>true]]])->assertOk()->json();
        $this->postJson('/api/contact-import/commit',['id'=>$data['id'],'selected'=>[2000]])->assertUnprocessable();
        DB::table('providers')->insert(['id'=>2,'name'=>'Other','password_hash'=>'x','trial_started_at'=>now(),'trial_ends_at'=>now()->addDays(60),'active'=>true,'created_at'=>now(),'updated_at'=>now()]);DB::table('salon_state')->insert(['id'=>2,'data'=>json_encode(SalonStore::initial())]);$this->withSession(['admin'=>true,'provider_id'=>2]);$this->postJson('/api/contact-import/commit',['id'=>$data['id'],'selected'=>[0]])->assertStatus(410);$this->withSession(['admin'=>true,'provider_id'=>1]);
        $this->postJson('/api/contact-import/commit',['id'=>$data['id'],'selected'=>[0]])->assertOk();$c=app(SalonStore::class)->read()['clients'][2];$this->assertNull($c['guestId']);$this->assertArrayNotHasKey('reminderPreference',$c);$this->assertArrayNotHasKey('consent',$c);
        $expired=$this->postJson('/api/contact-import/preview',['source'=>'file','records'=>[['name'=>'Later','email'=>'later@example.com']]])->json();$this->travel(21)->minutes();$this->postJson('/api/contact-import/commit',['id'=>$expired['id'],'selected'=>[0]])->assertStatus(410);$this->travelBack();
        $this->withSession(['admin'=>false]);$this->getJson('/api/contact-import/status')->assertForbidden();$this->postJson('/api/contact-import/preview',['source'=>'phone','records'=>[]])->assertForbidden();
    }
    public function test_dismissal_is_independent_and_profile_share_text_persists():void
    {
        $this->postJson('/api/contact-import/dismiss',['source'=>'phone'])->assertOk();$this->getJson('/api/contact-import/status')->assertOk()->assertJsonMissingPath('prompts.mail.snoozedUntil')->assertJsonStructure(['prompts'=>['phone'=>['snoozedUntil']],'configured'=>['google','microsoft']]);
        $this->putJson('/api/profile',['shareText'=>'Gyere hozzám!'])->assertOk();$this->assertSame('Gyere hozzám!',app(SalonStore::class)->read()['profile']['shareText']);$this->putJson('/api/profile',['shareText'=>str_repeat('x',501)])->assertUnprocessable();
    }
    public function test_vcard_and_quoted_csv_parsing_preserve_names_and_phone_without_email():void
    {
        $vcf="BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Kovács Éva\r\nTEL;TYPE=CELL:+36 30 123 4567\r\nCATEGORIES:Vendégek,VIP\r\nEND:VCARD\r\nBEGIN:VCARD\r\nVERSION:2.1\r\nFN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:T=C3=B3th=20Tam=C3=A1s\r\nTEL:06701234567\r\nEND:VCARD\r\n";
        $records=ContactFiles::vcard($vcf);$this->assertSame('Kovács Éva',$records[0]['name']);$this->assertSame(['Vendégek','VIP'],$records[0]['groups']);$this->assertSame('Tóth Tamás',$records[1]['name']);$this->assertSame('',$records[1]['email']);
        $csv="Name,Phone,E-mail\r\n\"Last, First\",06301234567,\r\n\"Multi\nLine\",06701234567,m@example.com\r\n";$data=ContactFiles::csv($csv);$this->assertSame('Last, First',$data['rows'][0][0]);$this->assertCount(2,$data['rows']);$this->assertSame('06301234567',$data['rows'][0][1]);
        $file=UploadedFile::fake()->createWithContent('contacts.vcf',$vcf);$this->post('/api/contact-import/file',['file'=>$file],['Accept'=>'application/json'])->assertOk()->assertJsonCount(2,'records');
    }
    public function test_xlsx_reads_first_sheet_and_shared_strings_without_running_formulas():void
    {
        $path=tempnam(sys_get_temp_dir(),'contact-test-').'.zip';$zip=new \PharData($path,0,null,\Phar::ZIP);$zip->addFromString('xl/sharedStrings.xml','<sst><si><t>Név</t></si><si><t>Telefon</t></si><si><t>Éva</t></si></sst>');$zip->addFromString('xl/worksheets/sheet1.xml','<worksheet><sheetData><row><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row><row><c r="A2" t="s"><v>2</v></c><c r="B2" t="inlineStr"><is><t>06301234567</t></is></c><c r="C2"><f>HYPERLINK("https://bad")</f><v>unsafe</v></c></row></sheetData></worksheet>');unset($zip);
        try{$file=UploadedFile::fake()->createWithContent('contacts.xlsx',file_get_contents($path));$data=ContactFiles::read($file);$this->assertSame(['Név','Telefon'],$data['headers']);$this->assertSame(['Éva','06301234567',''],$data['rows'][0]);}finally{unlink($path);}
    }
    public function test_oauth_scopes_are_read_only_state_is_checked_and_remote_groups_are_preserved():void
    {
        config(['contact-import.google_client_id'=>'google-id','contact-import.google_client_secret'=>'secret']);
        $response=$this->get('/auth/contact-import/google?provider=1')->assertRedirect();parse_str(parse_url($response->headers->get('Location'),PHP_URL_QUERY),$query);$this->assertSame('https://www.googleapis.com/auth/contacts.readonly',$query['scope']);$this->assertSame('S256',$query['code_challenge_method']);
        $this->get('/auth/contact-import/google/callback?state=wrong&code=x')->assertRedirect('/?provider=1&contactImport=error');Http::assertNothingSent();
        Http::fake(['people.googleapis.com/v1/contactGroups*'=>Http::response(['contactGroups'=>[['resourceName'=>'contactGroups/custom','name'=>'Vendégek']]]),'people.googleapis.com/v1/people/me/connections*'=>Http::response(['connections'=>[['names'=>[['displayName'=>'Remote']], 'phoneNumbers'=>[['value'=>'06301234567']],'memberships'=>[['contactGroupMembership'=>['contactGroupResourceName'=>'contactGroups/custom']]]]]])]);$rows=MailContacts::read('google','token');$this->assertSame(['Vendégek'],$rows[0]['groups']);$this->assertCount(2,app(SalonStore::class)->read()['clients']);
    }
    public function test_outlook_uses_contact_folders_and_categories_and_never_mail_endpoints():void
    {
        Http::fake(['graph.microsoft.com/v1.0/me/contactFolders?*'=>Http::response(['value'=>[['id'=>'guests','displayName'=>'Vendégek']]]),'graph.microsoft.com/v1.0/me/contacts?*'=>Http::response(['value'=>[]]),'graph.microsoft.com/v1.0/me/contactFolders/guests/contacts?*'=>Http::response(['value'=>[['id'=>'r','displayName'=>'Outlook Guest','mobilePhone'=>'06301234567','categories'=>['VIP']]]])]);$rows=MailContacts::read('microsoft','token');$this->assertSame(['Vendégek','VIP'],$rows[0]['groups']);Http::assertSentCount(3);Http::assertNotSent(fn($r)=>str_contains($r->url(),'/messages'));
    }
    public function test_successful_oauth_returns_to_an_uncommitted_group_review_without_persisting_tokens():void
    {
        config(['contact-import.google_client_id'=>'google-id','contact-import.google_client_secret'=>'secret']);
        Http::fake(['oauth2.googleapis.com/token'=>Http::response(['access_token'=>'temporary-token','refresh_token'=>'never-save-this']), 'people.googleapis.com/v1/contactGroups*'=>Http::response(['contactGroups'=>[]]),'people.googleapis.com/v1/people/me/connections*'=>Http::response(['connections'=>[['names'=>[['displayName'=>'Cloud Guest']],'emailAddresses'=>[['value'=>'cloud@example.com']]]]])]);
        $r=$this->get('/auth/contact-import/google?provider=1');parse_str(parse_url($r->headers->get('Location'),PHP_URL_QUERY),$q);
        $callback=$this->get('/auth/contact-import/google/callback?state='.$q['state'].'&code=test-code')->assertRedirect();parse_str(parse_url($callback->headers->get('Location'),PHP_URL_QUERY),$return);$this->assertTrue(\Illuminate\Support\Str::isUuid($return['contactImport']));
        $this->getJson('/api/contact-import/remote/'.$return['contactImport'])->assertOk()->assertJsonPath('records.0.name','Cloud Guest')->assertJsonMissingPath('access_token')->assertJsonMissingPath('refresh_token');$this->assertCount(2,app(SalonStore::class)->read()['clients']);$this->assertDatabaseCount('deliveries',0);$this->assertNull(session('contacts_oauth'));
        Http::assertSent(fn($request)=>str_contains($request->url(),'oauth2.googleapis.com/token')&&$request['code_verifier']&&$request['grant_type']==='authorization_code');
    }
    public function test_editor_cannot_use_contact_import_or_oauth():void
    {
        app(SalonStore::class)->mutate(function(&$s){$s['subscription']=['plan'=>'pro','status'=>'active'];});
        $member=$this->postJson('/api/provider-members',['staff_id'=>'eszter','email'=>'worker@example.com','role'=>'editor','password'=>'Worker-password-long','password_confirmation'=>'Worker-password-long'])->assertCreated()->json();
        $this->withSession(['provider_member_id'=>$member['id'],'provider_member_version'=>1,'admin'=>true,'provider_id'=>1]);
        $this->getJson('/api/contact-import/status')->assertForbidden();$this->postJson('/api/contact-import/preview',['source'=>'phone','records'=>[['name'=>'No','email'=>'no@example.com']]])->assertForbidden();$this->get('/auth/contact-import/google')->assertForbidden();
    }
    public function test_contacts_credentials_are_secret_and_cannot_be_changed_by_provider():void
    {
        $path=storage_path('framework/contacts-secret-'.bin2hex(random_bytes(6)));config(['operator.connections_path'=>$path,'integrations.path'=>$path.'-legacy']);
        try{$this->withSession(['platform_admin'=>true]);$keys=['contacts_google_client_id'=>'gid','contacts_google_client_secret'=>'google-secret','contacts_microsoft_client_id'=>'mid','contacts_microsoft_client_secret'=>'microsoft-secret'];$this->putJson('/api/platform/connections',$keys)->assertOk();$this->getJson('/api/platform/connections')->assertOk()->assertJsonPath('contacts_google_client_secret_configured',true)->assertJsonMissingPath('contacts_google_client_secret')->assertJsonMissingPath('contacts_microsoft_client_secret');$this->withSession(['platform_admin'=>false]);$this->getJson('/api/connections')->assertOk()->assertJsonMissingPath('contacts_google_client_id')->assertJsonMissingPath('contacts_microsoft_client_secret_configured');$this->putJson('/api/connections',$keys)->assertForbidden();}
        finally{foreach(glob($path.'*') as $file)unlink($file);}
    }

}
