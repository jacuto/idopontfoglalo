<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 150);
            $t->string('email', 200)->nullable();
            $t->string('password_hash');
            $t->boolean('active')->default(true);
            $t->timestamp('trial_started_at');
            $t->timestamp('trial_ends_at');
            $t->timestamps();
        });
        Schema::table('deliveries', fn(Blueprint $t) => $t->unsignedInteger('provider_id')->default(1)->index());
        Schema::table('guest_accounts', fn(Blueprint $t) => $t->string('avatar')->nullable());
    }
    // Forward-only: rolling back must never discard tenant data or ownership.
    public function down(): void { throw new RuntimeException('Adatmegőrző frissítés: automatikus visszavonás helyett állítsd vissza a teljes mentést.'); }
};
