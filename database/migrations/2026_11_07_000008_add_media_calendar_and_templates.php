<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('media_assets',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedBigInteger('provider_id')->index();$t->string('target',30);$t->string('entity_id',100)->nullable();$t->string('path');$t->timestamps();});
  Schema::create('mail_templates',function(Blueprint $t){$t->string('event',50)->primary();$t->string('subject',250);$t->text('html');$t->boolean('enabled')->default(true);$t->timestamps();});
  Schema::create('calendar_connections',function(Blueprint $t){$t->id();$t->string('owner_type',10);$t->string('owner_id',100);$t->unsignedBigInteger('provider_id')->default(0);$t->text('credentials');$t->string('calendar_id')->default('primary');$t->string('account_email')->nullable();$t->boolean('enabled')->default(true);$t->text('busy')->nullable();$t->timestamp('busy_until')->nullable();$t->timestamp('last_synced_at')->nullable();$t->timestamp('last_attempt_at')->nullable()->index();$t->string('last_error',250)->nullable();$t->timestamps();$t->unique(['owner_type','owner_id','provider_id'],'calendar_owner_unique');$t->index(['provider_id','enabled']);});
  Schema::create('calendar_tasks',function(Blueprint $t){$t->id();$t->unsignedBigInteger('connection_id');$t->unsignedBigInteger('provider_id');$t->string('booking_id',100);$t->string('version',100);$t->string('status',20)->default('pending');$t->unsignedInteger('attempts')->default(0);$t->timestamp('available_at')->nullable();$t->timestamp('locked_at')->nullable();$t->string('last_error',250)->nullable();$t->timestamps();$t->unique(['connection_id','provider_id','booking_id'],'calendar_task_booking_unique');$t->index(['status','available_at']);});
 }
 public function down():void {throw new \RuntimeException('Adatmegőrző frissítés: visszaállításhoz a mentést használd, ne töröld a kapcsolati és médiaadatokat.');}
};
