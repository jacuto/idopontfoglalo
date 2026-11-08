<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('provider_identities',function(Blueprint $t){$t->id();$t->unsignedInteger('provider_id')->index();$t->string('network',20);$t->string('subject',255);$t->unique(['network','subject']);$t->timestamps();});
  Schema::create('provider_security',function(Blueprint $t){$t->unsignedInteger('provider_id')->primary();$t->string('password_hash')->nullable();$t->unsignedInteger('version')->default(1);$t->timestamps();});
  Schema::create('provider_password_tokens',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedInteger('provider_id')->index();$t->string('email',200);$t->string('token_hash',64)->unique();$t->text('payload');$t->timestamp('expires_at');$t->timestamp('used_at')->nullable();$t->timestamps();});
 }
 public function down():void{throw new RuntimeException('Adatmegőrző frissítés: teljes mentésből állítható vissza.');}
};
