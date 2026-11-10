<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{Schema::table('provider_members',fn(Blueprint $t)=>$t->boolean('password_pending')->default(false));Schema::table('provider_password_tokens',fn(Blueprint $t)=>$t->string('purpose',12)->default('reset'));}
 public function down():void{throw new RuntimeException('Adatmegőrző frissítés: mentésből állítható vissza.');}
};
