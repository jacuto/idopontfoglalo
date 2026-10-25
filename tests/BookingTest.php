<?php
namespace Tests;
use App\Services\BookingRules;
use App\Services\SalonStore;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BookingTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__ . "/../bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }
    protected function setUp(): void
    {
        parent::setUp();
        config([
            "raero.admin_password_hash" => Hash::make("Only-for-tests-123"),
        ]);
        if (
            config("database.default") !== "sqlite" &&
            !str_ends_with(
                config("database.connections.mysql.database"),
                "_test",
            )
        ) {
            throw new \RuntimeException(
                "Csak külön _test adatbázison futtatható.",
            );
        }
        $this->assertSame(
            0,
            \Illuminate\Support\Facades\Artisan::call("migrate", [
                "--force" => true,
            ]),
        );
        DB::table("salon_state")->delete();
        DB::table("salon_state")->insert([
            "id" => 1,
            "data" => json_encode(SalonStore::initial()),
        ]);
    }
    private function booking(array $overrides = []): array
    {
        return array_merge(
            [
                "name" => "Teszt Vendég",
                "phone" => "+36301234567",
                "email" => "",
                "start" => "2035-03-12T09:00:00Z",
                "staff" => "anna",
                "services" => ["cut"],
            ],
            $overrides,
        );
    }
    private function admin(): void
    {
        $this->withSession([
            "admin" => true,
            "_token" => str_repeat("x", 40),
        ])->withHeader("X-CSRF-TOKEN", str_repeat("x", 40));
    }
    public function test_private_data_is_protected(): void
    {
        $this->getJson("/api/state")->assertUnauthorized();
        $this->getJson("/api/public")
            ->assertOk()
            ->assertJsonMissingPath("clients")
            ->assertJsonMissingPath("bookings");
        $this->get("/")->assertOk()->assertSee("app.js");
        $this->get("/foglalas")->assertOk();
    }
    public function test_login_and_logout(): void
    {
        $this->postJson("/api/login", [
            "password" => "wrong",
        ])->assertUnauthorized();
        $this->postJson("/api/login", [
            "password" => "Only-for-tests-123",
        ])->assertOk();
        $this->getJson("/api/state")->assertOk();
        $this->postJson("/api/logout")->assertOk();
        $this->getJson("/api/state")->assertUnauthorized();
    }
    public function test_guest_cannot_force_confirmation_and_duplicate_is_rejected(): void
    {
        $this->postJson(
            "/api/bookings",
            $this->booking(["status" => "confirmed", "public" => false]),
        )
            ->assertCreated()
            ->assertJsonPath("status", "pending");
        $this->postJson("/api/bookings", $this->booking())
            ->assertUnprocessable()
            ->assertJsonPath(
                "error",
                "Ez az időpont már foglalt. Válassz másikat.",
            );
    }
    public function test_admin_approval_and_client_storage(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $this->admin();
        $this->patchJson("/api/bookings/" . $b["id"], ["status" => "confirmed"])
            ->assertOk()
            ->assertJsonPath("status", "confirmed");
        $this->getJson("/api/state")
            ->assertOk()
            ->assertJsonPath("clients.0.phone", "+36301234567");
    }
    public function test_slots_exclude_reserved_time_and_use_budapest_time(): void
    {
        $this->postJson("/api/bookings", $this->booking())->assertCreated();
        $slots = $this->getJson(
            "/api/slots?date=2035-03-12&services=cut&staff=anna",
        )
            ->assertOk()
            ->json();
        $this->assertNotEmpty($slots);
        $this->assertSame("08:00", $slots[0]["time"]);
        $this->assertSame("2035-03-12T07:00:00.000000Z", $slots[0]["start"]);
        $this->assertNotContains(
            "2035-03-12T09:00:00.000000Z",
            array_column($slots, "start"),
        );
    }
    public function test_secret_link_reschedule_cancel_and_invalid_token(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $this->getJson("/api/manage?token=bad")->assertNotFound();
        $this->getJson("/api/manage?token=" . $b["token"])
            ->assertOk()
            ->assertJsonPath("booking.id", $b["id"]);
        $this->patchJson("/api/manage", [
            "token" => $b["token"],
            "start" => "2035-03-13T09:00:00Z",
        ])
            ->assertOk()
            ->assertJsonPath("status", "pending");
        $this->patchJson("/api/manage", [
            "token" => $b["token"],
            "cancel" => true,
        ])
            ->assertOk()
            ->assertJsonPath("status", "cancelled");
        $this->patchJson("/api/manage", [
            "token" => $b["token"],
            "start" => "2035-03-14T09:00:00Z",
        ])->assertUnprocessable();
    }
    public function test_processing_interval_allows_parallel_work(): void
    {
        $this->admin();
        $state = app(SalonStore::class)->read();
        $state["services"][] = [
            "id" => "short",
            "name" => "Rövid kezelés",
            "duration" => 20,
            "price" => 1000,
            "staff" => ["anna"],
        ];
        $this->putJson("/api/config", [
            "services" => $state["services"],
        ])->assertOk();
        $this->postJson(
            "/api/bookings",
            $this->booking(["services" => ["color"]]),
        )->assertCreated();
        $this->postJson(
            "/api/bookings",
            $this->booking([
                "name" => "Másik",
                "services" => ["short"],
                "start" => "2035-03-12T09:35:00Z",
            ]),
        )->assertCreated();
        $this->postJson(
            "/api/bookings",
            $this->booking([
                "services" => ["short"],
                "start" => "2035-03-12T10:00:00Z",
            ]),
        )->assertUnprocessable();
    }
    public function test_invalid_configuration_and_staff_are_rejected(): void
    {
        $this->admin();
        $s = SalonStore::initial();
        $s["services"][1]["duration"] = 30;
        $this->putJson("/api/config", [
            "services" => $s["services"],
        ])->assertUnprocessable();
        $this->postJson(
            "/api/bookings",
            $this->booking(["staff" => "eszter"]),
        )->assertUnprocessable();
    }
    public function test_automatic_multiple_service_booking(): void
    {
        $s = SalonStore::initial();
        foreach ($s["services"] as &$v) {
            $v["automatic"] = true;
        }
        app(SalonStore::class)->mutate(function (&$state) use ($s) {
            $state["services"] = $s["services"];
        });
        $this->postJson(
            "/api/bookings",
            $this->booking(["services" => ["cut", "color"]]),
        )
            ->assertCreated()
            ->assertJsonPath("status", "confirmed");
    }
    public function test_client_import_payload_persists_and_bad_email_is_rejected(): void
    {
        $this->admin();
        $this->putJson("/api/config", [
            "clients" => [
                [
                    "id" => "one",
                    "name" => "Import Vendég",
                    "phone" => "123",
                    "email" => "a@example.com",
                ],
            ],
        ])->assertOk();
        $this->getJson("/api/state")->assertJsonPath(
            "clients.0.name",
            "Import Vendég",
        );
        $this->putJson("/api/config", [
            "clients" => [
                ["id" => "two", "name" => "Vendég", "email" => "invalid"],
            ],
        ])->assertUnprocessable();
    }
    public function test_external_origin_write_is_blocked(): void
    {
        $this->withHeader("Origin", "https://other.example")
            ->postJson("/api/bookings", $this->booking())
            ->assertForbidden();
    }
    public function test_cancelled_booking_releases_slot(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $this->patchJson("/api/manage", [
            "token" => $b["token"],
            "cancel" => true,
        ])->assertOk();
        $this->postJson("/api/bookings", $this->booking())->assertCreated();
    }
}
