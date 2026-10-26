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
Route::any("/api/{path}", [SalonController::class, "handle"])
    ->where("path", ".*")
    ->middleware(ApiHeaders::class);
Route::view("/", "app");
Route::view("/foglalas", "app");
Route::view("/modositas", "app");
