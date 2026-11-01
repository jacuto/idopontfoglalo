<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::table('guest_accounts',function(Blueprint $t){$t->string('contact_email',200)->nullable();$t->string('phone',40)->nullable();}); Schema::table('platform_payments',function(Blueprint $t){$t->timestamp('period_start')->nullable();$t->longText('details')->nullable();}); }
 public function down():void {throw new RuntimeException('Vendégadatokat nem törlünk visszavonással.');}
};
