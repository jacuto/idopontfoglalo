<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('sms_wallets',function(Blueprint $t){$t->unsignedInteger('provider_id')->primary();$t->bigInteger('balance')->default(0);$t->unsignedBigInteger('reserved')->default(0);$t->unsignedBigInteger('alert_message_id')->nullable();$t->timestamps();});
  Schema::create('sms_ledger',function(Blueprint $t){$t->id();$t->unsignedInteger('provider_id')->index();$t->string('reference',180)->unique();$t->string('kind',30);$t->bigInteger('amount');$t->text('details')->nullable();$t->timestamps();});
  Schema::create('sms_reservations',function(Blueprint $t){$t->unsignedBigInteger('delivery_id')->primary();$t->unsignedInteger('provider_id')->index();$t->uuid('batch_id')->index();$t->unsignedInteger('generation')->default(1);$t->unsignedInteger('segments');$t->unsignedInteger('unit_price');$t->unsignedInteger('amount');$t->string('status',20);$t->longText('payload');$t->string('provider_reference',100)->nullable();$t->timestamps();});
  Schema::create('sms_topups',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedInteger('provider_id')->index();$t->uuid('request_id');$t->unsignedInteger('amount');$t->unsignedInteger('refunded')->default(0);$t->string('status',20);$t->string('session_id',200)->nullable()->unique();$t->text('checkout_url')->nullable();$t->string('payment_intent',200)->nullable()->index();$t->string('invoice_number',100)->nullable();$t->date('invoice_issued_on')->nullable();$t->timestamps();$t->unique(['provider_id','request_id']);});
  Schema::create('marketing_connections',function(Blueprint $t){$t->id();$t->unsignedInteger('provider_id')->unique();$t->longText('credentials');$t->timestamps();});
  Schema::create('marketing_consents',function(Blueprint $t){$t->id();$t->unsignedInteger('provider_id')->index();$t->string('client_id',80)->nullable();$t->unsignedBigInteger('guest_id')->nullable();$t->string('email',200);$t->string('name',150)->default('');$t->string('source',500);$t->timestamp('consented_at')->nullable();$t->timestamp('unsubscribed_at')->nullable();$t->timestamps();$t->unique(['provider_id','email']);});
  Schema::create('marketing_campaigns',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedInteger('provider_id')->index();$t->string('engine',20);$t->string('subject',200);$t->longText('html');$t->string('status',24)->default('draft');$t->string('remote_id',100)->nullable();$t->string('remote_list_id',100)->nullable();$t->longText('recipients')->nullable();$t->unsignedInteger('recipient_count')->default(0);$t->timestamp('scheduled_at')->nullable()->index();$t->timestamp('submitted_at')->nullable();$t->text('error')->nullable();$t->timestamps();});
 }
 public function down():void{throw new RuntimeException('Pénzügyi és hozzájárulási előzményt nem törlünk visszavonással.');}
};
