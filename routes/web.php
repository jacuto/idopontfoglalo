<?php
use App\Http\Controllers\SalonController;
use App\Http\Middleware\ApiHeaders;
use Illuminate\Support\Facades\Route;
Route::post('/api/bookings/{id}/review-request',[\App\Http\Controllers\ReviewRequestController::class,'request']);
Route::post('/api/manage/review',[\App\Http\Controllers\ReviewRequestController::class,'review'])->middleware('throttle:10,1');
Route::patch('/api/clients/{id}/blacklist',[\App\Http\Controllers\BookingToolsController::class,'blacklist']);
Route::post('/api/booking-series/{id}/cancel',[\App\Http\Controllers\BookingToolsController::class,'cancelSeries']);
Route::post('/api/guest/auth/{action}',[\App\Http\Controllers\GuestPasswordController::class,'handle'])->whereIn('action',['register','login','forgot','resend','verify','reset','change'])->middleware('throttle:12,1');
Route::get('/api/provider-social',[\App\Http\Controllers\ProviderSecurityController::class,'pending']);
Route::post('/api/provider-password/{action}',[\App\Http\Controllers\ProviderSecurityController::class,'handle'])->whereIn('action',['forgot','reset','change'])->middleware('throttle:12,1');
Route::match(['GET','POST','PATCH'],'/api/provider-members/{id?}',[\App\Http\Controllers\MemberController::class,'handle'])->whereNumber('id');
Route::post('/api/provider-workspace',[\App\Http\Controllers\MemberController::class,'activate'])->middleware('throttle:12,1');
Route::post('/api/provider-login',[\App\Http\Controllers\ProviderRegistrationController::class,'login'])->middleware('throttle:5,1');
Route::post('/api/provider-register',[\App\Http\Controllers\ProviderRegistrationController::class,'store'])->middleware('throttle:3,60');
Route::get('/api/providers',[\App\Http\Controllers\ProviderDirectoryController::class,'index'])->middleware('throttle:60,1');
Route::get('/meghivo/{token}',[\App\Http\Controllers\InvitationController::class,'open'])->where('token','[a-f0-9]{64}');
Route::post('/api/guest/invitations/claim',[\App\Http\Controllers\InvitationController::class,'claim']);
Route::match(['GET','PUT'],'/api/guest/billing',[\App\Http\Controllers\GuestFeaturesController::class,'billing']);
Route::get('/api/clients/{id}/billing',[\App\Http\Controllers\GuestFeaturesController::class,'clientBilling']);
Route::match(['GET','POST'],'/api/group-sessions',[\App\Http\Controllers\GroupSessionController::class,'handle']);
Route::match(['GET','POST'],'/api/guest/calendar-prompt',[\App\Http\Controllers\GuestFeaturesController::class,'calendarPrompt']);
Route::match(['GET','PATCH','DELETE','POST'],'/api/calendar/connection',[\App\Http\Controllers\CalendarConnectionController::class,'handle']);
Route::get('/auth/google-calendar',[\App\Http\Controllers\CalendarConnectionController::class,'start']);
Route::get('/auth/google-calendar/callback',[\App\Http\Controllers\CalendarConnectionController::class,'callback']);
Route::get('/api/exports/{kind}',[\App\Http\Controllers\ExportController::class,'handle'])->whereIn('kind',['statistics','calendar']);
Route::delete('/api/media',[\App\Http\Controllers\MediaController::class,'remove']);
Route::post('/api/media',[\App\Http\Controllers\MediaController::class,'upload']);
Route::get('/media/{id}',[\App\Http\Controllers\MediaController::class,'image'])->whereUuid('id');
Route::post('/api/platform/templates/preview',[\App\Http\Controllers\TemplateController::class,'handle']);
Route::match(['GET','PUT'],'/api/platform/templates',[\App\Http\Controllers\TemplateController::class,'handle']);
Route::get("/api/auth/providers", [
    \App\Http\Controllers\AuthController::class,
    "providers",
]);
Route::get("/api/guest", [
    \App\Http\Controllers\AuthController::class,
    "guest",
])->middleware(ApiHeaders::class);
Route::post("/api/guest/logout", [
    \App\Http\Controllers\AuthController::class,
    "logout",
]);
Route::get('/auth/apple', [\App\Http\Controllers\AppleAuthController::class,'start']);
Route::get('/auth/apple/callback',[\App\Http\Controllers\AppleAuthController::class,'callback']);
Route::post('/auth/apple/callback',[\App\Http\Controllers\AppleAuthController::class,'callback'])->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \App\Http\Middleware\TenantContext::class,
]);
Route::get("/auth/{provider}/callback", [
    \App\Http\Controllers\AuthController::class,
    "callback",
])->whereIn("provider", ["google", "facebook"]);
Route::get("/auth/{provider}", [
    \App\Http\Controllers\AuthController::class,
    "start",
])->whereIn("provider", ["google", "facebook"]);
Route::match(["GET", "PUT"], "/api/availability", [\App\Http\Controllers\AvailabilityController::class, "handle"])->middleware(ApiHeaders::class);
Route::view("/fiokom", "app");
Route::view("/uzenetek", "app");
Route::view("/foadmin", "app");
Route::match(['GET','PUT'], '/api/newsletter/consent', [\App\Http\Controllers\NewsletterController::class,'consent']);
Route::get('/hirlevel/leiratkozas/{id}', [\App\Http\Controllers\NewsletterController::class,'unsubscribe'])->name('newsletter.unsubscribe')->whereNumber('id');
Route::match(['GET','POST'], '/api/platform/newsletters/{id?}/{action?}', [\App\Http\Controllers\NewsletterController::class,'handle'])->whereNumber('id')->whereIn('action',['test','schedule']);
Route::match(['GET','POST'], '/api/platform-messages', [\App\Http\Controllers\PlatformMessagesController::class,'handle']);
Route::patch('/api/platform-messages/read', [\App\Http\Controllers\PlatformMessagesController::class,'read']);
Route::get('/api/waitlist/availability',[\App\Http\Controllers\WaitlistController::class,'availability'])->middleware('throttle:60,1');
Route::post('/api/waitlist/join',[\App\Http\Controllers\WaitlistController::class,'join'])->middleware('throttle:3,60');
Route::match(['GET','POST'],'/api/waitlist/token/{token}',[\App\Http\Controllers\WaitlistController::class,'token'])->where('token','[a-f0-9]{64}')->middleware('throttle:30,1');
Route::match(['GET','DELETE'],'/api/waitlist/{id?}',[\App\Http\Controllers\WaitlistController::class,'manage'])->whereUuid('id');
Route::view('/varolista/{token}','app')->where('token','[a-f0-9]{64}');
Route::match(['GET','POST','PUT','PATCH','DELETE'],'/api/provider-reminders/{id?}',[\App\Http\Controllers\ProviderToolsController::class,'reminders'])->whereUuid('id');
Route::post('/api/clients/{id}/invite',[\App\Http\Controllers\ProviderToolsController::class,'invite'])->middleware('throttle:10,1');
Route::match(['GET','PATCH'],'/api/platform/issues',[\App\Http\Controllers\ProviderToolsController::class,'issues']);
Route::post('/api/client-issue',[\App\Http\Controllers\ProviderToolsController::class,'clientIssue'])->middleware('throttle:5,1');
Route::post('/api/sms-wallet/transfer',[\App\Http\Controllers\SmsWalletController::class,'handle']);
Route::post('/api/platform/sms-wallet/credit',[\App\Http\Controllers\SmsWalletController::class,'credit']);
Route::match(['GET','POST'],'/api/sms-wallet',[\App\Http\Controllers\SmsWalletController::class,'handle']);
Route::post('/api/sms-wallet/deliveries/{id}/resolve',[\App\Http\Controllers\SmsWalletController::class,'resolve'])->whereNumber('id');
Route::match(['GET','POST'],'/api/platform/sms-wallet',[\App\Http\Controllers\SmsWalletController::class,'operator']);
foreach(['/api/marketing','/api/platform/marketing'] as $prefix){
 Route::match(['GET','PUT','POST','DELETE'],$prefix.'/connection',[\App\Http\Controllers\MarketingController::class,'connection']);
 Route::match(['GET','POST'],$prefix.'/campaigns/{id?}/{action?}',[\App\Http\Controllers\MarketingController::class,'campaigns'])->whereUuid('id')->whereIn('action',['prepare','send','test','report','cancel']);
 Route::match(['GET','POST'],$prefix.'/consents',[\App\Http\Controllers\MarketingController::class,'consents']);
}
Route::match(['GET','PUT'],'/api/guest/marketing-consent',[\App\Http\Controllers\MarketingController::class,'guestConsent']);
Route::match(['GET','POST'],'/api/marketing/webhook/{providerId}/{engine}/{token}',fn(\Illuminate\Http\Request $r,int $providerId,string $engine,string $token)=>app(\App\Http\Controllers\MarketingController::class)->webhook($r,$providerId,$engine,$token))->whereNumber('providerId')->whereIn('engine',['brevo','mailchimp'])->where('token','[a-f0-9]{64}');
Route::any("/api/platform/{path}", [\App\Http\Controllers\PlatformController::class, "handle"])->where("path", ".*");
Route::post('/api/barion/callback',fn(\Illuminate\Http\Request $r)=>app(\App\Services\Barion::class)->callback($r))->middleware('throttle:60,1');
Route::post('/api/simplepay/szep-ipn',fn(\Illuminate\Http\Request $r)=>app(\App\Services\SimplePay::class)->ipn($r,true));
Route::get('/simplepay/szep-visszateres',fn(\Illuminate\Http\Request $r)=>app(\App\Services\SimplePay::class)->returned($r,true));
Route::post('/api/simplepay/ipn',fn(\Illuminate\Http\Request $r)=>app(\App\Services\SimplePay::class)->ipn($r));
Route::get('/simplepay/visszateres',fn(\Illuminate\Http\Request $r)=>app(\App\Services\SimplePay::class)->returned($r));
Route::post(
    "/api/stripe/webhook",
    fn(\Illuminate\Http\Request $r) => app(
        \App\Services\Payments::class,
    )->webhook($r),
);
Route::post(
    "/api/platform-stripe/webhook",
    fn(\Illuminate\Http\Request $r) => app(
        \App\Services\Payments::class,
    )->webhook($r, true),
);
Route::get('/api/message-recipients',[\App\Http\Controllers\MessagesController::class,'recipients']);
Route::get('/api/public/discounts', [\App\Http\Controllers\FeaturesController::class, 'publicDiscounts']);
Route::match(['GET', 'POST', 'PATCH', 'DELETE'], '/api/messages', [\App\Http\Controllers\MessagesController::class, 'handle']);
Route::patch('/api/guest/profile', [\App\Http\Controllers\GuestFeaturesController::class, 'profile']);
Route::get('/api/guest/passes', [\App\Http\Controllers\GuestFeaturesController::class, 'passes']);
Route::post('/api/guest/claim', [\App\Http\Controllers\GuestFeaturesController::class, 'claim']);
Route::post('/api/guest/avatar', [\App\Http\Controllers\GuestFeaturesController::class, 'avatar']);
Route::get('/api/guest/avatar/{id}', [\App\Http\Controllers\GuestFeaturesController::class, 'image'])->whereNumber('id');
Route::post('/api/guest/bookings/{id}/review', [\App\Http\Controllers\GuestFeaturesController::class, 'review']);
Route::any("/api/{path}", [SalonController::class, "handle"])
    ->where("path", ".*")
    ->middleware(ApiHeaders::class);
Route::view("/", "app");
Route::view("/foglalas", "app");
Route::view("/modositas", "app");

foreach(['/szolgaltatok','/szolgaltato','/belepes','/arak','/kapcsolat'] as $page)Route::view($page,'app');

foreach(['adatvedelem'=>'privacy','aszf'=>'terms','adattorles'=>'deletion','sugo'=>'help'] as $slug=>$document)Route::get('/'.$slug,fn()=>app(\App\Services\PublicDocuments::class)->page($document));
Route::view('/foadmin/{section}','app')->where('section','hibanaplo|sms-egyenlegek|kapcsolatok|szamlak|szamla-rogzitese|szamla-egyeztetes|levelek|jogosultsagok|uzenetek|hirlevelek|jogi-oldalak|elofizetes|szolgaltatok|befizetesek|uzemelteto');

Route::get('/gyik',fn()=>app(\App\Services\PublicDocuments::class)->page('help'));
