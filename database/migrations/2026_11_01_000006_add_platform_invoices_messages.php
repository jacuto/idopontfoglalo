<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('platform_invoices',function(Blueprint $t){$t->id();$t->unsignedBigInteger('payment_id')->unique();$t->unsignedInteger('provider_id')->index();$t->string('status',20);$t->longText('buyer');$t->string('reference',100)->nullable();$t->string('number',100)->nullable();$t->timestamps();});
  Schema::create('platform_messages',function(Blueprint $t){$t->id();$t->string('recipient_type',20);$t->unsignedBigInteger('recipient_id');$t->string('sender',20);$t->text('text');$t->timestamp('read_at')->nullable();$t->timestamps();$t->index(['recipient_type','recipient_id']);});
 }
 public function down():void {throw new RuntimeException('Számla- és üzenetelőzményt nem törlünk.');}
};
