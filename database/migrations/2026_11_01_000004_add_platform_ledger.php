<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('platform_payments',function(Blueprint $t){$t->id();$t->unsignedInteger('provider_id')->index();$t->string('reference',150)->unique();$t->unsignedInteger('amount');$t->string('method',20);$t->timestamp('paid_at');$t->timestamp('period_end');$t->timestamps();});
  Schema::create('platform_settings',function(Blueprint $t){$t->unsignedInteger('id')->primary();$t->longText('data');});
 }
 public function down():void {throw new RuntimeException('Pénzügyi előzményt nem törlünk visszavonással.');}
};
