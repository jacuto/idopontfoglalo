<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('platform_invoices',function(Blueprint $t){$t->string('source',20)->default('billingo');$t->date('issued_on')->nullable();});}
 public function down():void {throw new RuntimeException('Adatmegőrző migráció: a számlaadatok automatikus visszavonása nem támogatott.');}
};
