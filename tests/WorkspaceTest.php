<?php
namespace Tests;
use App\Services\{SalonStore, Settings, Connections, Notifications, Payments};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{DB, Hash, Artisan, Http, Mail};
class WorkspaceTest extends TestCase
{
    private string $connectionsPath;
    public function createApplication()
    {
        $a = require __DIR__ . "/../bootstrap/app.php";
        $a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $a;
    }
    protected function setUp(): void
    {
        parent::setUp();
        $this->connectionsPath =
            sys_get_temp_dir() .
            "/raero-connection-" .
            bin2hex(random_bytes(8));
        config([
            "integrations.path" => $this->connectionsPath,
            "raero.admin_password_hash" => Hash::make("test-only-password"),
        ]);
        if (
            config("database.default") !== "sqlite" &&
            !str_ends_with(
                config("database.connections.mysql.database"),
                "_test",
            )
        ) {
            throw new \RuntimeException("Külön _test adatbázis szükséges.");
        }
        Artisan::call("migrate", ["--force" => true]);
        DB::table("salon_state")->delete();
        DB::table("deliveries")->delete();
        DB::table("guest_accounts")->delete();
        DB::table("salon_state")->insert([
            "id" => 1,
            "data" => json_encode(Settings::upgrade(SalonStore::initial())),
        ]);
        Http::preventStrayRequests();
    }
    protected function tearDown(): void
    {
        if (is_file($this->connectionsPath)) {
            unlink($this->connectionsPath);
        }
        parent::tearDown();
    }
    private function admin(): void
    {
        $this->withSession([
            "admin" => true,
            "_token" => str_repeat("t", 40),
        ])->withHeader("X-CSRF-TOKEN", str_repeat("t", 40));
    }
    private function state(): array
    {
        return app(SalonStore::class)->read();
    }
    private function booking(array $extra = []): array
    {
        return array_merge(
            [
                "name" => "Guest",
                "phone" => "+36301234567",
                "email" => "guest@example.com",
                "start" => "2035-03-12T09:00:00Z",
                "staff" => "anna",
                "services" => ["cut"],
            ],
            $extra,
        );
    }
    private function settings(array $v): void
    {
        app(SalonStore::class)->mutate(function (&$s) use ($v) {
            $s["settings"] = array_replace($s["settings"], $v);
        });
    }
    public function test_client_and_staff_edit_preserve_history(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())
            ->assertCreated()
            ->json();
        $this->admin();
        $id = $this->state()["clients"][0]["id"];
        $this->patchJson("/api/clients/" . $id, [
            "name" => "Changed",
            "email" => "changed@example.com",
            "phone" => "456",
        ])->assertOk();
        $this->assertSame("Changed", $this->state()["clients"][0]["name"]);
        $this->assertSame("Guest", $this->state()["bookings"][0]["name"]);
        $this->patchJson("/api/staff/anna", [
            "name" => "Anna Updated",
            "role" => "Fodrász",
            "email" => "anna@example.com",
        ])->assertOk();
        $this->deleteJson("/api/staff/anna")->assertUnprocessable();
        $this->patchJson("/api/bookings/" . $b["id"], [
            "status" => "cancelled",
        ])->assertOk();
        $this->deleteJson("/api/staff/anna")->assertOk();
        $this->assertTrue($this->state()["staff"][0]["archived"]);
        $this->getJson("/api/public")->assertJsonCount(1, "staff");
    }
    public function test_minute_opening_and_solo_public_projection(): void
    {
        $this->settings([
            "openingTime" => "08:15",
            "closingTime" => "09:05",
            "businessMode" => "solo",
            "primaryStaffId" => "anna",
        ]);
        $this->getJson("/api/public")
            ->assertJsonCount(1, "staff")
            ->assertJsonMissingPath("settings.providerEmail");
        $slots = $this->getJson(
            "/api/slots?date=2035-03-13&services=cut&staff=anna",
        )
            ->assertOk()
            ->json();
        $this->assertCount(1, $slots);
        $this->assertSame("08:15", $slots[0]["time"]);
    }
    public function test_imports_are_atomic_and_idempotent(): void
    {
        $this->admin();
        $rows = [
            [
                "id" => "new-person",
                "name" => "New Person",
                "role" => "Fodrász",
                "email" => "new@example.com",
            ],
        ];
        $this->postJson("/api/imports/staff", ["records" => $rows])
            ->assertOk()
            ->assertJsonPath("added", 1);
        $this->postJson("/api/imports/staff", ["records" => $rows])
            ->assertOk()
            ->assertJsonPath("updated", 1);
        $this->assertCount(3, $this->state()["staff"]);
        $this->postJson("/api/imports/staff", [
            "records" => [
                ["name" => "Good", "role" => "Masszőr"],
                ["name" => "Bad", "role" => ""],
            ],
        ])->assertUnprocessable();
        $this->assertCount(3, $this->state()["staff"]);
        $r = $this->booking([
            "id" => "imported",
            "start" => "2025-01-13T09:00:00Z",
        ]);
        $this->postJson("/api/imports/bookings", [
            "records" => [$r],
        ])->assertOk();
        $this->postJson("/api/imports/bookings", ["records" => [$r]])
            ->assertOk()
            ->assertJsonPath("updated", 1);
        $this->assertCount(1, $this->state()["bookings"]);
    }
    public function test_manual_payment_cannot_overpay_and_can_be_voided(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $this->admin();
        $v = [
            "bookingId" => $b["id"],
            "amount" => 8500,
            "method" => "transfer",
            "paidAt" => "2035-03-12T09:00:00Z",
        ];
        $p = $this->postJson("/api/payments", $v)->assertCreated()->json();
        $this->postJson("/api/payments", $v)->assertUnprocessable();
        $this->postJson("/api/payments/" . $p["id"] . "/void")->assertOk();
        $this->assertSame("void", $this->state()["payments"][0]["status"]);
    }
    public function test_email_toggles_enqueue_and_missing_smtp_blocks_without_losing_booking(): void
    {
        $this->settings([
            "notifyGuest" => true,
            "notifyProvider" => true,
            "providerEmail" => "owner@example.com",
        ]);
        $this->postJson("/api/bookings", $this->booking())->assertCreated();
        $this->assertSame(2, DB::table("deliveries")->count());
        $r = app(Notifications::class)->run();
        $this->assertSame(2, $r["blocked"]);
        $this->assertCount(1, $this->state()["bookings"]);
    }
    public function test_configured_email_jobs_are_sent_and_stale_reminders_skipped(): void
    {
        Mail::fake();
        app(Connections::class)->save([
            "smtp_host" => "smtp.example.com",
            "mail_from" => "sender@example.com",
        ]);
        $this->settings(["notifyGuest" => true, "reminderGuest" => true]);
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $r = app(Notifications::class)->run();
        $this->assertSame(1, $r["sent"]);
        $this->patchJson("/api/manage", [
            "token" => $b["token"],
            "cancel" => true,
        ])->assertOk();
        DB::table("deliveries")
            ->where("status", "pending")
            ->update(["available_at" => now()->subMinute()]);
        $r = app(Notifications::class)->run();
        $this->assertGreaterThanOrEqual(1, $r["skipped"]);
    }
    public function test_connection_secrets_are_encrypted_and_never_returned(): void
    {
        $this->admin();
        $this->putJson("/api/connections", [
            "stripe_key" => "sk_test_private",
            "google_client_secret" => "private",
        ])->assertOk();
        $this->assertStringNotContainsString(
            "sk_test_private",
            file_get_contents($this->connectionsPath),
        );
        $this->getJson("/api/connections")
            ->assertJsonMissingPath("stripe_key")
            ->assertJsonPath("stripe_key_configured", true);
        $this->getJson("/api/public")->assertJsonMissingPath("connections");
    }
    public function test_embed_cors_is_limited_to_configured_public_origin(): void
    {
        $this->settings(["embedOrigins" => ["https://salon.example"]]);
        $this->withHeader("Origin", "https://salon.example")
            ->getJson("/api/public")
            ->assertOk()
            ->assertHeader(
                "Access-Control-Allow-Origin",
                "https://salon.example",
            );
        $this->postJson("/api/bookings", $this->booking())->assertCreated();
        $this->getJson("/api/state")->assertUnauthorized();
        $this->withHeader("Origin", "https://attacker.example")
            ->postJson(
                "/api/bookings",
                $this->booking(["start" => "2035-03-13T09:00:00Z"]),
            )
            ->assertForbidden();
    }
    public function test_google_guest_signup_does_not_grant_admin_access(): void
    {
        app(Connections::class)->save([
            "google_client_id" => "client",
            "google_client_secret" => "secret",
        ]);
        Http::fake([
            "oauth2.googleapis.com/*" => Http::response([
                "access_token" => "token",
            ]),
            "openidconnect.googleapis.com/*" => Http::response([
                "sub" => "google-user",
                "name" => "Social Guest",
                "email" => "guest@example.com",
                "email_verified" => true,
            ]),
        ]);
        $r = $this->get("/auth/google");
        $r->assertRedirect();
        parse_str(parse_url($r->headers->get("Location"), PHP_URL_QUERY), $q);
        $this->get(
            "/auth/google/callback?state=" . $q["state"] . "&code=code",
        )->assertRedirect("/fiokom");
        $this->getJson("/api/guest")
            ->assertOk()
            ->assertJsonPath("name", "Social Guest");
        $this->getJson("/api/state")->assertUnauthorized();
        $this->assertSame(1, DB::table("guest_accounts")->count());
    }
    public function test_oauth_state_and_admin_allowlist_are_enforced(): void
    {
        $this->get("/auth/google/callback?state=bad&code=x")->assertRedirect(
            "/fiokom?authError=state",
        );
        app(Connections::class)->save([
            "google_client_id" => "client",
            "google_client_secret" => "secret",
        ]);
        Http::fake([
            "oauth2.googleapis.com/*" => Http::response([
                "access_token" => "token",
            ]),
            "openidconnect.googleapis.com/*" => Http::response([
                "sub" => "user",
                "name" => "Guest",
                "email" => "other@example.com",
                "email_verified" => true,
            ]),
        ]);
        $r = $this->get("/auth/google?mode=admin");
        parse_str(parse_url($r->headers->get("Location"), PHP_URL_QUERY), $q);
        $this->get(
            "/auth/google/callback?state=" . $q["state"] . "&code=x",
        )->assertRedirect("/?authError=denied");
        $this->getJson("/api/state")->assertUnauthorized();
    }
    public function test_stripe_signed_webhook_is_idempotent_and_amount_checked(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        app(Connections::class)->save([
            "stripe_key" => "sk_test",
            "stripe_webhook_secret" => "whsec_test",
        ]);
        Http::fake([
            "api.stripe.com/v1/checkout/sessions" => Http::response([
                "id" => "cs_test",
                "url" => "https://checkout.stripe.com/c/pay/test",
            ]),
        ]);
        $this->admin();
        $this->postJson("/api/checkout", ["bookingId" => $b["id"]])->assertOk();
        $event = [
            "id" => "evt_test",
            "type" => "checkout.session.completed",
            "data" => [
                "object" => [
                    "id" => "cs_test",
                    "payment_status" => "paid",
                    "currency" => "huf",
                    "amount_total" => 850000,
                    "payment_intent" => "pi_test",
                ],
            ],
        ];
        $body = json_encode($event);
        $t = time();
        $signature =
            "t=" .
            $t .
            ",v1=" .
            hash_hmac("sha256", $t . "." . $body, "whsec_test");
        $this->call(
            "POST",
            "/api/stripe/webhook",
            [],
            [],
            [],
            [
                "CONTENT_TYPE" => "application/json",
                "HTTP_STRIPE_SIGNATURE" => "bad",
            ],
            $body,
        )->assertStatus(400);
        for ($i = 0; $i < 2; $i++) {
            $this->call(
                "POST",
                "/api/stripe/webhook",
                [],
                [],
                [],
                [
                    "CONTENT_TYPE" => "application/json",
                    "HTTP_STRIPE_SIGNATURE" => $signature,
                ],
                $body,
            )->assertOk();
        }
        $this->assertCount(1, $this->state()["payments"]);
        $this->assertSame(8500, $this->state()["payments"][0]["amount"]);
    }
    public function test_billingo_invoice_creation_and_duplicate_protection(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $this->admin();
        $p = $this->postJson("/api/payments", [
            "bookingId" => $b["id"],
            "amount" => 8500,
            "method" => "card",
            "paidAt" => now()->toISOString(),
        ])->json();
        $this->settings(["invoiceProvider" => "billingo"]);
        app(Connections::class)->save([
            "billingo_key" => "test",
            "billingo_block" => 1,
        ]);
        Http::fake([
            "api.billingo.hu/v3/partners" => Http::response(["id" => 10]),
            "api.billingo.hu/v3/documents" => Http::response([
                "id" => 20,
                "invoice_number" => "TEST-1",
            ]),
        ]);
        $v = [
            "paymentId" => $p["id"],
            "buyer" => [
                "name" => "Buyer",
                "email" => "b@example.com",
                "country" => "HU",
                "zip" => "1111",
                "city" => "Budapest",
                "address" => "Teszt utca 1.",
            ],
        ];
        $this->postJson("/api/invoices", $v)
            ->assertOk()
            ->assertJsonPath("number", "TEST-1");
        $this->postJson("/api/invoices", $v)->assertUnprocessable();
        Http::assertSentCount(2);
    }

    public function test_facebook_guest_and_google_authorized_admin(): void
    {
        app(Connections::class)->save([
            "facebook_client_id" => "123",
            "facebook_client_secret" => "secret",
            "google_client_id" => "client",
            "google_client_secret" => "secret",
            "admin_google_emails" => "owner@example.com",
        ]);
        Http::fake([
            "graph.facebook.com/*/oauth/access_token" => Http::response([
                "access_token" => "fb-token",
            ]),
            "graph.facebook.com/*/me*" => Http::response([
                "id" => "fb-user",
                "name" => "Facebook Guest",
                "email" => "fb@example.com",
            ]),
            "oauth2.googleapis.com/*" => Http::response([
                "access_token" => "g-token",
            ]),
            "openidconnect.googleapis.com/*" => Http::response([
                "sub" => "owner",
                "name" => "Owner",
                "email" => "owner@example.com",
                "email_verified" => true,
            ]),
        ]);
        $r = $this->get("/auth/facebook");
        parse_str(parse_url($r->headers->get("Location"), PHP_URL_QUERY), $q);
        $this->get(
            "/auth/facebook/callback?state=" . $q["state"] . "&code=x",
        )->assertRedirect("/fiokom");
        $this->getJson("/api/guest")
            ->assertOk()
            ->assertJsonPath("name", "Facebook Guest");
        $this->getJson("/api/state")->assertUnauthorized();
        $r = $this->get("/auth/google?mode=admin");
        parse_str(parse_url($r->headers->get("Location"), PHP_URL_QUERY), $q);
        $this->get(
            "/auth/google/callback?state=" . $q["state"] . "&code=x",
        )->assertRedirect("/");
        $this->getJson("/api/state")->assertOk();
    }
    public function test_sms_notification_fallback_uses_twilio(): void
    {
        app(Connections::class)->save([
            "twilio_sid" => "ACtest",
            "twilio_token" => "token",
            "twilio_from" => "+36309999999",
        ]);
        $this->settings(["notifyGuest" => true, "channel" => "sms"]);
        Http::fake([
            "api.twilio.com/*" => Http::response(["sid" => "SMtest"], 201),
        ]);
        $this->postJson(
            "/api/bookings",
            $this->booking(["email" => ""]),
        )->assertCreated();
        $result = app(Notifications::class)->run();
        $this->assertSame(1, $result["sent"]);
        Http::assertSent(
            fn($r) => $r["To"] === "+36301234567" &&
                str_contains($r["Body"], "/fiokom"),
        );
    }
    public function test_szamlazz_xml_invoice_and_partial_payment_guard(): void
    {
        $b = $this->postJson("/api/bookings", $this->booking())->json();
        $this->admin();
        $p = $this->postJson("/api/payments", [
            "bookingId" => $b["id"],
            "amount" => 4000,
            "method" => "cash",
            "paidAt" => now()->toISOString(),
        ])->json();
        $this->settings(["invoiceProvider" => "szamlazz"]);
        app(Connections::class)->save(["szamlazz_key" => "agent-secret"]);
        $v = [
            "paymentId" => $p["id"],
            "buyer" => [
                "name" => "Buyer & Partner",
                "country" => "HU",
                "zip" => "1111",
                "city" => "Budapest",
                "address" => "Test 1",
            ],
        ];
        $this->postJson("/api/invoices", $v)->assertUnprocessable();
        Http::assertNothingSent();
        $this->postJson("/api/payments", [
            "bookingId" => $b["id"],
            "amount" => 4500,
            "method" => "cash",
            "paidAt" => now()->toISOString(),
        ])->assertCreated();
        Http::fake([
            "www.szamlazz.hu/szamla/" => Http::response(
                '<?xml version="1.0"?><xmlszamlavalasz><sikeres>true</sikeres><szamlaszam>TEST-2</szamlaszam></xmlszamlavalasz>',
                200,
            ),
        ]);
        $this->postJson("/api/invoices", $v)
            ->assertOk()
            ->assertJsonPath("number", "TEST-2");
    }
    public function test_enabling_and_retiming_reminders_reschedules_existing_bookings(): void
    {
        Mail::fake();
        app(Connections::class)->save([
            "smtp_host" => "smtp.example.com",
            "mail_from" => "sender@example.com",
        ]);
        $this->admin();
        $this->postJson("/api/bookings", $this->booking())->assertCreated();
        $settings = $this->state()["settings"];
        $settings["reminderGuest"] = true;
        $this->putJson("/api/config", ["settings" => $settings])->assertOk();
        $this->assertSame(1, DB::table("deliveries")->count());
        $settings = $this->state()["settings"];
        $settings["reminder"] = 48;
        $this->putJson("/api/config", ["settings" => $settings])->assertOk();
        $this->assertSame(2, DB::table("deliveries")->count());
        DB::table("deliveries")->update(["available_at" => now()->subMinute()]);
        $r = app(Notifications::class)->run();
        $this->assertSame(1, $r["skipped"]);
        $this->assertSame(1, $r["sent"]);
    }
    public function test_booking_import_preserves_service_snapshot_prices(): void
    {
        $this->admin();
        $row = $this->booking([
            "id" => "price-snapshot",
            "start" => "2025-02-12T09:00:00Z",
            "serviceSnapshot" => [
                [
                    "id" => "cut",
                    "name" => "Original price",
                    "price" => 5000,
                    "duration" => 45,
                    "staff" => ["anna"],
                ],
            ],
        ]);
        $this->postJson("/api/imports/bookings", [
            "records" => [$row],
        ])->assertOk();
        $this->assertSame(
            5000,
            $this->state()["bookings"][0]["serviceSnapshot"][0]["price"],
        );
    }

    public function test_uncertain_invoice_requires_explicit_reconciliation(): void
    {
        app(SalonStore::class)->mutate(function (&$state) {
            $state["invoiceRequests"][] = [
                "id" => "unknown-invoice",
                "paymentId" => "payment",
                "status" => "unknown",
            ];
        });
        $this->postJson("/api/invoices/unknown-invoice/reconcile", [
            "decision" => "not_created",
            "confirmed" => true,
        ])->assertUnauthorized();
        $this->admin();
        $this->postJson("/api/invoices/unknown-invoice/reconcile", [
            "decision" => "not_created",
            "confirmed" => false,
        ])->assertUnprocessable();
        $this->postJson("/api/invoices/unknown-invoice/reconcile", [
            "decision" => "issued",
            "confirmed" => true,
            "number" => "CHECKED-1",
        ])->assertOk();
        $this->assertSame(
            "issued",
            $this->state()["invoiceRequests"][0]["status"],
        );
    }

    public function test_reminder_preference_change_does_not_drop_unsent_booking_email(): void
    {
        Mail::fake();
        app(Connections::class)->save([
            "smtp_host" => "smtp.example.com",
            "mail_from" => "sender@example.com",
        ]);
        $this->settings(["notifyGuest" => true]);
        $this->postJson("/api/bookings", $this->booking())->assertCreated();
        $this->admin();
        $settings = $this->state()["settings"];
        $settings["reminder"] = 48;
        $this->putJson("/api/config", ["settings" => $settings])->assertOk();
        $r = app(Notifications::class)->run();
        $this->assertSame(1, $r["sent"]);
    }
}
