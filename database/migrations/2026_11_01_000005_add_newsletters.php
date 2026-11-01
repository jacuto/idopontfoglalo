<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('newsletter_consents',function(Blueprint $t){$t->id();$t->string('audience',20);$t->unsignedBigInteger('subject_id');$t->string('email',200);$t->timestamp('consented_at')->nullable();$t->timestamp('unsubscribed_at')->nullable();$t->timestamps();$t->unique(['audience','subject_id']);});
  Schema::create('newsletters',function(Blueprint $t){$t->id();$t->string('audience',20);$t->string('subject',200);$t->text('content');$t->string('status',20)->default('draft');$t->timestamp('scheduled_at')->nullable();$t->timestamps();});
  Schema::create('newsletter_deliveries',function(Blueprint $t){$t->id();$t->unsignedBigInteger('newsletter_id')->index();$t->unsignedBigInteger('consent_id');$t->string('email',200);$t->string('status',20)->default('pending');$t->string('error',200)->nullable();$t->timestamps();$t->unique(['newsletter_id','email']);});
 }
 public function down():void {throw new RuntimeException('Hozzájárulási és küldési előzményt nem törlünk.');}
};
