<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('guest_accounts',fn(Blueprint $t)=>$t->timestamp('calendar_prompt_seen_at')->nullable());}
 public function down():void{Schema::table('guest_accounts',fn(Blueprint $t)=>$t->dropColumn('calendar_prompt_seen_at'));}
};
