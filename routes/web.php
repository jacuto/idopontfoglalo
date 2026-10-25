<?php
use App\Http\Controllers\SalonController;
use App\Http\Middleware\ApiHeaders;
use Illuminate\Support\Facades\Route;
Route::any("/api/{path}", [SalonController::class, "handle"])
    ->where("path", ".*")
    ->middleware(ApiHeaders::class);
Route::view("/", "app");
Route::view("/foglalas", "app");
Route::view("/modositas", "app");
