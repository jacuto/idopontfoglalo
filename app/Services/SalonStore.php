<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

class SalonStore
{
    public static function initial(): array
    {
        return [
            "settings" => [
                "name" => "Harmónia Szalon",
                "profession" => "Szépségszalon",
                "confirmation" => true,
                "open" => 8,
                "close" => 20,
                "reminder" => 24,
                "channel" => "email",
            ],
            "staff" => [
                ["id" => "anna", "name" => "Kovács Anna", "role" => "Fodrász"],
                [
                    "id" => "eszter",
                    "name" => "Nagy Eszter",
                    "role" => "Masszőr",
                ],
            ],
            "services" => [
                [
                    "id" => "cut",
                    "name" => "Női hajvágás",
                    "duration" => 45,
                    "price" => 8500,
                    "staff" => ["anna"],
                ],
                [
                    "id" => "color",
                    "name" => "Hajfestés",
                    "duration" => 95,
                    "price" => 18000,
                    "staff" => ["anna"],
                    "phases" => [30, 35, 30],
                ],
                [
                    "id" => "massage",
                    "name" => "Frissítő masszázs",
                    "duration" => 60,
                    "price" => 14000,
                    "staff" => ["eszter"],
                ],
            ],
            "clients" => [],
            "bookings" => [],
        ];
    }
    public function read(): array
    {
        $row = DB::table("salon_state")->where("id", 1)->first();
        if (!$row) {
            throw new \RuntimeException(
                "Az adatbázis nincs előkészítve. Futtasd: php artisan raero:install",
            );
        }
        return Settings::upgrade(
            json_decode($row->data, true, 512, JSON_THROW_ON_ERROR),
        );
    }
    public function mutate(callable $callback): mixed
    {
        // Lock one salon row for the whole read/check/write operation: prevents double bookings.
        return DB::transaction(function () use ($callback) {
            $row = DB::table("salon_state")
                ->where("id", 1)
                ->lockForUpdate()
                ->first();
            if (!$row) {
                throw new \RuntimeException("Hiányzik a szalon kezdeti adata.");
            }
            $state = Settings::upgrade(
                json_decode($row->data, true, 512, JSON_THROW_ON_ERROR),
            );
            $result = $callback($state);
            DB::table("salon_state")
                ->where("id", 1)
                ->update([
                    "data" => json_encode(
                        $state,
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    ),
                ]);
            return $result;
        }, 5);
    }
}
