<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('waitlist_entries',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedInteger('provider_id')->index();$t->string('dedup',64)->index();$t->string('token_hash',64)->unique();$t->longText('payload');$t->string('status',24)->default('pending')->index();$t->date('first_date');$t->date('last_date')->index();$t->date('cursor_date')->nullable();$t->timestamp('checked_at')->nullable()->index();$t->timestamp('locked_at')->nullable();$t->timestamp('notified_at')->nullable();$t->timestamps();});
  Schema::create('provider_reminders',function(Blueprint $t){$t->uuid('id')->primary();$t->unsignedInteger('provider_id')->index();$t->string('staff_id',80);$t->string('title',150);$t->text('notes')->nullable();$t->dateTime('starts_at');$t->unsignedInteger('duration')->default(15);$t->string('frequency',12)->default('none');$t->date('until_date')->nullable();$t->unsignedInteger('occurrence_count')->default(0);$t->timestamps();});
  Schema::create('reminder_completions',function(Blueprint $t){$t->uuid('reminder_id');$t->date('occurrence_date');$t->timestamp('completed_at');$t->primary(['reminder_id','occurrence_date']);});
  Schema::create('system_issues',function(Blueprint $t){$t->id();$t->string('fingerprint',64)->unique();$t->string('category',40)->index();$t->unsignedInteger('provider_id')->nullable()->index();$t->string('code',80);$t->string('message',500);$t->unsignedSmallInteger('http_status')->nullable();$t->unsignedInteger('occurrences')->default(1);$t->boolean('resolved')->default(false);$t->timestamp('first_seen_at');$t->timestamp('last_seen_at')->index();});
 }
 public function down():void {throw new RuntimeException('Előzményeket nem törlünk visszafelé migrálással.');}
};
