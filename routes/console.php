<?php
use App\Services\SalonStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command(
    "raero:install {--reset-password : Új adminjelszó beállítása}",
    function () {
        $envPath = base_path(".env");
        if (!file_exists($envPath)) {
            copy(base_path(".env.example"), $envPath);
        }
        $write = function (string $key, string $value) use ($envPath) {
            if (str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new RuntimeException(
                    "Sortörés nem lehet a beállításban.",
                );
            }
            $encoded =
                '"' .
                str_replace(["\\", '"', '$'], ["\\\\", '\\"', '\\$'], $value) .
                '"';
            $content = file_get_contents($envPath);
            $pattern = "/^" . preg_quote($key, "/") . "=.*/m";
            $line = $key . "=" . $encoded;
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn() => $line, $content)
                : $content . "\n" . $line . "\n";
            file_put_contents($envPath, $content, LOCK_EX);
            chmod($envPath, 0600);
        };
        if (!config("app.key")) {
            $this->call("key:generate", ["--force" => true]);
        }
        if (
            config("database.default") === "mysql" &&
            !config("database.connections.mysql.database")
        ) {
            if (!$this->input->isInteractive()) {
                $this->error(
                    "Add meg az adatbázist a .env fájlban, vagy futtasd interaktívan: php artisan raero:install",
                );
                return 1;
            }
            $values = [
                "DB_HOST" => $this->ask("MySQL kiszolgáló", "localhost"),
                "DB_DATABASE" => $this->ask(
                    "Adatbázis neve (a cPanel előtagjával)",
                ),
                "DB_USERNAME" => $this->ask(
                    "Adatbázis-felhasználó neve (a cPanel előtagjával)",
                ),
                "DB_PASSWORD" => $this->secret(
                    "Adatbázis jelszava (nem látható gépelés közben)",
                ),
            ];
            foreach ($values as $key => $value) {
                if ($value === null || $value === "") {
                    throw new RuntimeException(
                        "Minden adatbázisadat szükséges.",
                    );
                }
                $write($key, $value);
            }
            config([
                "database.connections.mysql.host" => $values["DB_HOST"],
                "database.connections.mysql.database" => $values["DB_DATABASE"],
                "database.connections.mysql.username" => $values["DB_USERNAME"],
                "database.connections.mysql.password" => $values["DB_PASSWORD"],
            ]);
            DB::purge("mysql");
        }
        $this->call("migrate", ["--force" => true]);
        DB::table("salon_state")->insertOrIgnore([
            "id" => 1,
            "data" => json_encode(
                SalonStore::initial(),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        ]);
        if (
            !config("raero.admin_password_hash") ||
            $this->option("reset-password")
        ) {
            if (!$this->input->isInteractive()) {
                $this->error(
                    "Adminjelszó beállításához futtasd interaktívan: php artisan raero:install",
                );
                return 1;
            }
            do {
                $password = $this->secret(
                    "Új szolgáltatói jelszó (legalább 12 karakter)",
                );
                if (mb_strlen($password ?? "") < 12) {
                    $this->error("Legalább 12 karakter szükséges.");
                    continue;
                }
                if ($this->secret("Jelszó ismét") !== $password) {
                    $this->error("A jelszavak nem egyeznek.");
                    continue;
                }
                break;
            } while (true);
            $write("ADMIN_PASSWORD_HASH", Hash::make($password));
        }
        $this->info(
            "Telepítés kész. A webcím dokumentumgyökere az alkalmazás public mappája legyen.",
        );
        $this->info(
            "A Node.js/Passenger alkalmazást állítsd le ennél az aldomainnél.",
        );
    },
)->purpose("Adatbázis előkészítése és adminjelszó biztonságos beállítása");
Artisan::command("raero:check", function () {
    $state = app(SalonStore::class)->read();
    $this->info(
        "Adatbázis elérhető. Szolgáltatások: " . count($state["services"]),
    );
})->purpose("A telepített adatbázis ellenőrzése");

Artisan::command("raero:deliver {--limit=50}", function () {
    $result = app(\App\Services\Notifications::class)->run(
        min(200, max(1, (int) $this->option("limit"))),
    );
    $this->info(json_encode($result, JSON_UNESCAPED_UNICODE));
})->purpose("Foglalási értesítők és esedékes emlékeztetők küldése");
\Illuminate\Support\Facades\Schedule::command("raero:deliver")
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command('raero:platform-password', function () {
 $password=$this->secret('Új főadminjelszó (legalább 12 karakter)');
 if(mb_strlen($password??'')<12||$this->secret('Jelszó ismét')!==$password){$this->error('Rövid vagy eltérő jelszavak.');return 1;}
 $path=base_path('.env');if(!is_file($path)){$this->error('Előbb készítsd el a .env fájlt.');return 1;}
 $line='PLATFORM_ADMIN_PASSWORD_HASH="'.str_replace('$','\$',Hash::make($password)).'"';$content=file_get_contents($path);
 $content=preg_match('/^PLATFORM_ADMIN_PASSWORD_HASH=.*/m',$content)?preg_replace_callback('/^PLATFORM_ADMIN_PASSWORD_HASH=.*/m',fn()=>$line,$content):$content."\n".$line."\n";
 file_put_contents($path,$content,LOCK_EX);chmod($path,0600);$this->info('Főadminjelszó beállítva. Következő parancs: php artisan config:clear');
})->purpose('Külön főadminjelszó beállítása rejtett helyi bevitellel');
Artisan::command('raero:upgrade',function(){
 if($this->call('migrate',['--force'=>true])!==0)return 1;
 foreach(DB::table('salon_state')->pluck('id') as $id)app(\App\Services\Tenant::class)->run((int)$id,function(){app(SalonStore::class)->mutate(function(&$s){
  if(empty($s['subscription']['status']))$s['subscription']=array_replace($s['subscription'],['status'=>'trialing','trialStartedAt'=>now()->toISOString(),'trialEndsAt'=>now()->addMonthsNoOverflow(2)->toISOString(),'price'=>4000,'calendarLimit'=>max(1,count(array_filter($s['staff'],fn($p)=>empty($p['archived']))))]);
 });});
 $this->info('Adatmegőrző frissítés kész.');
})->purpose('Additív migráció és meglévő adatok frissítése törlés nélkül');

Artisan::command('raero:newsletters {--limit=30}', function(){ $this->info(json_encode(app(\App\Services\Newsletters::class)->run(min(100,max(1,(int)$this->option('limit')))),JSON_UNESCAPED_UNICODE)); });
\Illuminate\Support\Facades\Schedule::command('raero:newsletters')->everyMinute()->withoutOverlapping();

Artisan::command('raero:subscriptions',function(){foreach(DB::table('salon_state')->pluck('id') as $id)app(\App\Services\Tenant::class)->run((int)$id,fn()=>app(SalonStore::class)->mutate(function(&$s){\App\Services\Subscriptions::refresh($s);}));$this->info('Havi díjtételek és hozzáférések frissítve.');});
\Illuminate\Support\Facades\Schedule::command('raero:subscriptions')->hourly()->withoutOverlapping();
Artisan::command('raero:sample-data {--provider=1}',function(){
 $id=filter_var($this->option('provider'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id){$this->error('Érvénytelen szolgáltatóazonosító.');return 1;}
 try{$result=app(\App\Services\Tenant::class)->run($id,fn()=>app(\App\Services\SampleData::class)->add());$this->info(json_encode($result,JSON_UNESCAPED_UNICODE));}catch(InvalidArgumentException $e){$this->error($e->getMessage());return 1;}
})->purpose('Mesterséges tesztadatok, kizárólag külön tesztadatbázisba');

Artisan::command('raero:calendar-sync {--limit=20}',function(){$this->info(json_encode(app(\App\Services\GoogleCalendar::class)->run(min(100,max(1,(int)$this->option('limit')))),JSON_UNESCAPED_UNICODE));});
\Illuminate\Support\Facades\Schedule::command('raero:calendar-sync')->everyMinute()->withoutOverlapping();

Artisan::command('raero:operator-import {file : Privát JSON-fájl teljes útvonala}',function(){
 $file=$this->argument('file');if(!is_file($file)||!is_readable($file)){$this->error('A megadott helyi fájl nem olvasható.');return 1;}
 try{$data=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);$rules=[];foreach(['name','country','zip','city','address','taxNumber','registrationNumber','bank','account','iban','bic'] as $key)$rules[$key]='required|string|max:250';$v=\Illuminate\Support\Facades\Validator::make($data,$rules)->validate();$v['vat']='AAM';DB::table('platform_settings')->updateOrInsert(['id'=>1],['data'=>json_encode($v,JSON_THROW_ON_ERROR)]);$this->info('Üzemeltetői adatok elmentve. A privát forrásfájlt ezután eltávolíthatod.');}
 catch(\Throwable){$this->error('A fájl nem megfelelő JSON, vagy hiányosak az üzemeltetői adatok.');return 1;}
})->purpose('Üzemeltető banki és számlázási adatainak privát helyi importja');

Artisan::command('raero:footer-import {file : A közzétenni kívánt láblécadatok privát JSON-fájlja}',function(){
 try{$data=json_decode(file_get_contents($this->argument('file')),true,32,JSON_THROW_ON_ERROR);$v=\Illuminate\Support\Facades\Validator::make($data,['name'=>'required|string|max:150','email'=>'nullable|email|max:200','phone'=>'nullable|string|max:40','address'=>'nullable|string|max:500','taxNumber'=>'nullable|string|max:40','website'=>'nullable|url:https|max:500','facebook'=>'nullable|url:https|max:500','contactUrl'=>'required|url:https|max:500'])->validate();$v=\App\Services\Contact::apply($v);DB::table('platform_settings')->updateOrInsert(['id'=>2],['data'=>json_encode($v,JSON_THROW_ON_ERROR)]);$this->info('Nyilvános láblécadatok elmentve. Ezek a vendégoldalon megjelennek.');}
 catch(\Throwable){$this->error('A láblécfájl hiányzik, vagy hibás/hiányos adatokat tartalmaz.');return 1;}
})->purpose('Nyilvános láblécadatok importja külön helyi fájlból, titkok nélkül');

Artisan::command('raero:group-check',function(){$this->info(json_encode(\App\Services\GroupMinimum::run(),JSON_UNESCAPED_UNICODE));})->purpose('Csoportos órák minimumlétszámának határidős ellenőrzése');
\Illuminate\Support\Facades\Schedule::command('raero:group-check')->everyMinute()->withoutOverlapping();
