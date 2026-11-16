<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('sms_topups',function(Blueprint $t){$t->string('method',20)->default('card');$t->string('bank_reference',160)->nullable()->unique();$t->timestamp('credited_at')->nullable();$t->string('credited_by',100)->nullable();});}
 public function down():void{throw new RuntimeException('A befizetési ellenőrzési nyomvonalat nem töröljük automatikusan.');}
};
