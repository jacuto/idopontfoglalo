<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{
  Schema::create('provider_members',function(Blueprint $t){$t->id();$t->unsignedInteger('provider_id')->index();$t->string('staff_id',80);$t->string('email',200);$t->string('password_hash');$t->string('role',10)->default('editor');$t->boolean('active')->default(true);$t->unsignedInteger('version')->default(1);$t->timestamps();$t->unique(['provider_id','email']);$t->unique(['provider_id','staff_id']);});
  Schema::table('provider_password_tokens',fn(Blueprint $t)=>$t->unsignedBigInteger('member_id')->nullable()->index());
 }
 public function down():void{throw new RuntimeException('Adatmegőrző frissítés: teljes mentésből állítható vissza.');}
};
