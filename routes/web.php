<?php
use App\Http\Controllers\SalonController;
use App\Http\Middleware\ApiHeaders;
use Illuminate\Support\Facades\Route;
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
Route::get("/auth/{provider}/callback", [
    \App\Http\Controllers\AuthController::class,
    "callback",
])->whereIn("provider", ["google", "facebook"]);
Route::get("/auth/{provider}", [
    \App\Http\Controllers\AuthController::class,
    "start",
])->whereIn("provider", ["google", "facebook"]);
Route::view("/fiokom", "app");
Route::view("/foadmin", "app");
Route::match(['GET','PUT'], '/api/newsletter/consent', [\App\Http\Controllers\NewsletterController::class,'consent']);
Route::get('/hirlevel/leiratkozas/{id}', [\App\Http\Controllers\NewsletterController::class,'unsubscribe'])->name('newsletter.unsubscribe')->whereNumber('id');
Route::match(['GET','POST'], '/api/platform/newsletters/{id?}/{action?}', [\App\Http\Controllers\NewsletterController::class,'handle'])->whereNumber('id')->whereIn('action',['test','schedule']);
Route::match(['GET','POST'], '/api/platform-messages', [\App\Http\Controllers\PlatformMessagesController::class,'handle']);
Route::patch('/api/platform-messages/read', [\App\Http\Controllers\PlatformMessagesController::class,'read']);
Route::any("/api/platform/{path}", [\App\Http\Controllers\PlatformController::class, "handle"])->where("path", ".*");
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
Route::match(['GET', 'POST', 'PATCH'], '/api/messages', [\App\Http\Controllers\MessagesController::class, 'handle']);
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
