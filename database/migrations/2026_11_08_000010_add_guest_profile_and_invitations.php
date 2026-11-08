<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::table('guest_accounts',function(Blueprint $t){$t->string('profile_name',150)->nullable();$t->text('billing_data')->nullable();});
  Schema::create('guest_invitations',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedBigInteger('provider_id')->index();$t->string('client_id',80);$t->string('contact_hash',64);$t->string('token_hash',64)->unique();$t->text('payload');$t->timestamp('expires_at');$t->timestamp('claimed_at')->nullable();$t->unsignedBigInteger('guest_id')->nullable();$t->timestamps();$t->unique(['provider_id','client_id']);$t->unique(['provider_id','contact_hash']);});
 }
 public function down():void{throw new \RuntimeException('Adatmegőrző frissítés: visszaállításhoz a mentést használd.');}
};
