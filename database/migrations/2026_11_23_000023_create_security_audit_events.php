<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('security_audit_events',function(Blueprint $t){$t->id();$t->string('event',40)->index();$t->string('actor_type',20)->index();$t->unsignedBigInteger('actor_id')->nullable();$t->unsignedBigInteger('provider_id')->nullable()->index();$t->string('subject_type',20)->nullable();$t->string('subject_id',100)->nullable();$t->string('ip',45)->nullable();$t->string('user_agent',300)->nullable();$t->timestamp('created_at')->index();});}
 public function down():void{Schema::dropIfExists('security_audit_events');}
};
