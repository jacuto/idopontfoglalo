<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('guest_credentials',function(Blueprint $t){$t->id();$t->unsignedBigInteger('guest_id')->unique();$t->string('email',200)->unique();$t->string('password_hash');$t->timestamp('verified_at')->nullable();$t->unsignedInteger('version')->default(1);$t->string('locale',2)->default('hu');$t->timestamps();});
  Schema::create('guest_auth_tokens',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedBigInteger('credential_id')->index();$t->string('purpose',10);$t->string('token_hash',64)->unique();$t->text('payload');$t->timestamp('expires_at');$t->timestamp('used_at')->nullable();$t->timestamps();});
 }
 public function down():void{throw new RuntimeException('Adatmegőrző frissítés: a vendégjelszavakat nem töröljük automatikusan.');}
};
