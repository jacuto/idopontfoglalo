<?php
namespace App\Http\Controllers;
use App\Services\{Connections, SalonStore};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http, RateLimiter};
class AuthController
{
    public function providers()
    {
        $c = app(Connections::class)->all();
        return response()->json([
            "google" =>
                !empty($c["google_client_id"]) &&
                !empty($c["google_client_secret"]),
            "facebook" =>
                !empty($c["facebook_client_id"]) &&
                !empty($c["facebook_client_secret"]),
        ]);
    }
    public function start(Request $r, string $provider)
    {
        abort_unless(in_array($provider, ["google", "facebook"], true), 404);
        $c = app(Connections::class)->all();
        abort_unless(
            !empty($c[$provider . "_client_id"]) &&
                !empty($c[$provider . "_client_secret"]),
            503,
            "Ez a belépési mód még nincs beállítva.",
        );
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(
            strtr(base64_encode(random_bytes(48)), "+/", "-_"),
            "=",
        );
        $mode = $r->query("mode") === "admin" ? "admin" : "guest";
        $r->session()->put("oauth", [
            "provider" => $provider,
            "state" => $state,
            "verifier" => $verifier,
            "mode" => $mode,
            "expires" => time() + 600,
        ]);
        $redirect =
            rtrim(config("app.url"), "/") . "/auth/" . $provider . "/callback";
        if ($provider === "google") {
            return redirect()->away(
                "https://accounts.google.com/o/oauth2/v2/auth?" .
                    http_build_query([
                        "client_id" => $c["google_client_id"],
                        "redirect_uri" => $redirect,
                        "response_type" => "code",
                        "scope" => "openid email profile",
                        "state" => $state,
                        "code_challenge" => rtrim(
                            strtr(
                                base64_encode(hash("sha256", $verifier, true)),
                                "+/",
                                "-_",
                            ),
                            "=",
                        ),
                        "code_challenge_method" => "S256",
                    ]),
            );
        }
        return redirect()->away(
            "https://www.facebook.com/" .
                config("integrations.facebook_version") .
                "/dialog/oauth?" .
                http_build_query([
                    "client_id" => $c["facebook_client_id"],
                    "redirect_uri" => $redirect,
                    "state" => $state,
                    "scope" => "email,public_profile",
                    "response_type" => "code",
                ]),
        );
    }
    public function callback(Request $r, string $provider)
    {
        $saved = $r->session()->pull("oauth");
        if (
            !$saved ||
            $saved["provider"] !== $provider ||
            $saved["expires"] < time() ||
            !is_string($r->input("state")) ||
            !hash_equals($saved["state"], $r->input("state")) ||
            !$r->filled("code")
        ) {
            return redirect("/fiokom?authError=state");
        }
        $target = $saved["mode"] === "admin" ? "/" : "/fiokom";
        $c = app(Connections::class)->all();
        $uri =
            rtrim(config("app.url"), "/") . "/auth/" . $provider . "/callback";
        try {
            if ($provider === "google") {
                $token = Http::timeout(15)
                    ->asForm()
                    ->post("https://oauth2.googleapis.com/token", [
                        "code" => $r->input("code"),
                        "client_id" => $c["google_client_id"],
                        "client_secret" => $c["google_client_secret"],
                        "redirect_uri" => $uri,
                        "grant_type" => "authorization_code",
                        "code_verifier" => $saved["verifier"],
                    ])
                    ->throw()
                    ->json();
                $user = Http::timeout(15)
                    ->withToken($token["access_token"])
                    ->get("https://openidconnect.googleapis.com/v1/userinfo")
                    ->throw()
                    ->json();
                $id = $user["sub"];
                $verified = ($user["email_verified"] ?? false) === true;
            } else {
                $base =
                    "https://graph.facebook.com/" .
                    config("integrations.facebook_version");
                $token = Http::timeout(15)
                    ->asForm()
                    ->post($base . "/oauth/access_token", [
                        "client_id" => $c["facebook_client_id"],
                        "client_secret" => $c["facebook_client_secret"],
                        "redirect_uri" => $uri,
                        "code" => $r->input("code"),
                    ])
                    ->throw()
                    ->json();
                $user = Http::timeout(15)
                    ->withToken($token["access_token"])
                    ->get($base . "/me", [
                        "fields" => "id,name,email",
                        "appsecret_proof" => hash_hmac(
                            "sha256",
                            $token["access_token"],
                            $c["facebook_client_secret"],
                        ),
                    ])
                    ->throw()
                    ->json();
                $id = $user["id"];
                $verified = false;
            }
            if (!$id || !is_string($id)) {
                throw new \RuntimeException();
            }
            if ($saved["mode"] === "admin") {
                $allowed =
                    $provider === "google"
                        ? $verified &&
                            in_array(
                                mb_strtolower($user["email"] ?? ""),
                                array_filter(
                                    array_map(
                                        fn($v) => mb_strtolower(trim($v)),
                                        explode(
                                            ",",
                                            $c["admin_google_emails"] ?? "",
                                        ),
                                    ),
                                ),
                                true,
                            )
                        : in_array(
                            $id,
                            array_map(
                                "trim",
                                explode(",", $c["admin_facebook_ids"] ?? ""),
                            ),
                            true,
                        );
                if (!$allowed) {
                    return redirect("/?authError=denied");
                }
                $r->session()->regenerate();
                $r->session()->put("admin", true);
                return redirect("/");
            }
            $account = DB::table("guest_accounts")
                ->where("provider", $provider)
                ->where("subject", $id)
                ->first();
            $values = [
                "name" => mb_substr($user["name"] ?? "Vendég", 0, 150),
                "email" => filter_var(
                    $user["email"] ?? "",
                    FILTER_VALIDATE_EMAIL,
                )
                    ? $user["email"]
                    : null,
                "email_verified" => $verified,
                "updated_at" => now(),
            ];
            if (!$account) {
                DB::table("guest_accounts")->insertOrIgnore(
                    array_merge($values, [
                        "provider" => $provider,
                        "subject" => $id,
                        "created_at" => now(),
                    ]),
                );
                $account = DB::table("guest_accounts")
                    ->where("provider", $provider)
                    ->where("subject", $id)
                    ->first();
            } else {
                DB::table("guest_accounts")
                    ->where("id", $account->id)
                    ->update($values);
            }
            $r->session()->regenerate();
            $r->session()->put("guest_id", $account->id);
            return redirect("/fiokom");
        } catch (\Throwable) {
            return redirect($target . "?authError=provider");
        }
    }
    public function guest(Request $r)
    {
        $account = DB::table("guest_accounts")->find(
            $r->session()->get("guest_id"),
        );
        if (!$account) {
            return response()->json(
                ["error" => "Vendégbelépés szükséges."],
                401,
            );
        }
        $bookings = array_values(
            array_filter(
                app(SalonStore::class)->read()["bookings"],
                fn($b) => ($b["guestId"] ?? null) === $account->id ||
                    ($account->email_verified &&
                        !empty($account->email) &&
                        mb_strtolower($b["email"] ?? "") ===
                            mb_strtolower($account->email)),
            ),
        );
        return response()->json([
            "name" => $account->name,
            "email" => $account->email,
            "bookings" => $bookings,
        ]);
    }
    public function logout(Request $r)
    {
        $r->session()->forget("guest_id");
        return response()->json(["ok" => true]);
    }
}
