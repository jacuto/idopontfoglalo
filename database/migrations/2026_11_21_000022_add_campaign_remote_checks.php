<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('marketing_campaigns',function(Blueprint $t){$t->string('remote_status',40)->nullable();$t->timestamp('remote_checked_at')->nullable()->index();$t->string('remote_check_error',500)->nullable();});}
 public function down():void{Schema::table('marketing_campaigns',fn(Blueprint $t)=>$t->dropColumn(['remote_status','remote_checked_at','remote_check_error']));}
};
