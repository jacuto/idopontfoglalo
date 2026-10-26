<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("deliveries", function (Blueprint $t) {
            $t->id();
            $t->string("dedup", 64)->unique();
            $t->text("payload");
            $t->string("status")->default("pending");
            $t->unsignedInteger("attempts")->default(0);
            $t->timestamp("available_at");
            $t->timestamp("locked_at")->nullable();
            $t->string("error", 200)->nullable();
            $t->timestamps();
            $t->index(["status", "available_at"]);
        });
        Schema::create("guest_accounts", function (Blueprint $t) {
            $t->id();
            $t->string("provider", 20);
            $t->string("subject", 200);
            $t->string("name", 150);
            $t->string("email", 200)->nullable();
            $t->boolean("email_verified")->default(false);
            $t->timestamps();
            $t->unique(["provider", "subject"]);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists("deliveries");
        Schema::dropIfExists("guest_accounts");
    }
};
