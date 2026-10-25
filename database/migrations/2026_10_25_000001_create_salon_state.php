<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create("salon_state", function (Blueprint $table) {
            $table->unsignedInteger("id")->primary();
            $table->longText("data");
        });
    }
    public function down(): void
    {
        Schema::dropIfExists("salon_state");
    }
};
