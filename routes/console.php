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
