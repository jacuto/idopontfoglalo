<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='sqlite'||!str_starts_with((string)config('database.connections.sqlite.database'),base_path('work/')))throw new RuntimeException('A böngészőfixture kizárólag külön work/ alatti SQLite tesztadatbázison futtatható.');
\Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
\Illuminate\Support\Facades\DB::table('salon_state')->insertOrIgnore(['id'=>1,'data'=>json_encode(\App\Services\SalonStore::initial())]);
app(\App\Services\SalonStore::class)->mutate(function(&$s){$s['clients']=[['id'=>'import-browser','name'=>'Importált Anna','phone'=>'+36 30 123-4567','email'=>'anna@example.com'],['id'=>'import-browser2','name'=>'Importált Anna','phone'=>'+36 20 999-8877','email'=>'anna2@example.com']];});
