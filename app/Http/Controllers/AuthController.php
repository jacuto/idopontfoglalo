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
            "apple" => AppleAuthController::ready($c),
            "google" =>
                app(\App\Services\PlatformConnections::class)->enabled("google") && !empty($c["google_client_id"]) &&
                !empty($c["google_client_secret"]),
            "facebook" =>
                app(\App\Services\PlatformConnections::class)->enabled("facebook") && !empty($c["facebook_client_id"]) &&
                !empty($c["facebook_client_secret"]),
        ]);
    }
    public function start(Request $r, string $provider)
    {
        abort_unless(in_array($provider, ["google", "facebook"], true), 404);
        $c = app(Connections::class)->all();
        abort_unless(
            app(\App\Services\PlatformConnections::class)->enabled($provider) && !empty($c[$provider . "_client_id"]) &&
                !empty($c[$provider . "_client_secret"]),
            503,
            "Ez a belépési mód még nincs beállítva.",
        );
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(
            strtr(base64_encode(random_bytes(48)), "+/", "-_"),
            "=",
        );
        $mode = in_array($r->query("mode"),["admin","provider","provider-link"],true)?$r->query("mode"):"guest";
        if($mode==='provider-link')abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(\App\Services\Tenant::class)->id,403);
        $photo=null;if($r->query('mode')==='photo'){
            abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(\App\Services\Tenant::class)->id,403);
            $target=$r->query('target');$entity=$r->query('entityId');abort_unless(in_array($target,['logo','staff'],true),422);if($target==='staff')abort_unless(is_string($entity)&&collect(app(\App\Services\SalonStore::class)->read()['staff'])->contains(fn($p)=>$p['id']===$entity&&empty($p['archived'])),404);$photo=['target'=>$target,'entity'=>$entity];$mode='photo';
        }
        $r->session()->put("oauth", [
            "photo"=>$photo,
            "provider" => $provider,
            "state" => $state,
            "verifier" => $verifier,
            "mode" => $mode,
            "returnTo" => $mode === "guest" && in_array($r->query("returnTo"), ["booking", "messages", "operatorMessages"], true) ? $r->query("returnTo") : null,
            "tenant" => app(\App\Services\Tenant::class)->id,
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
        app(\App\Services\Tenant::class)->id = (int) ($saved["tenant"] ?? 1);
        $target = in_array($saved["mode"],["provider","provider-link"],true)?"/belepes":($saved["mode"] === "admin" ? "/" : "/fiokom");
        $c = app(Connections::class)->all();
        abort_unless(app(\App\Services\PlatformConnections::class)->enabled($provider),403,'Ez a belépési mód ki van kapcsolva.');
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
                        "fields" => "id,name,email,picture.type(large)",
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
            if($saved['mode']==='photo'){
                abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(\App\Services\Tenant::class)->id,403);
                $photo=$saved['photo'];$picture=$provider==='google'?($user['picture']??null):(empty($user['picture']['data']['is_silhouette'])?($user['picture']['data']['url']??null):null);
                $ok=app(\App\Services\BusinessAvatar::class)->import(app(\App\Services\Tenant::class)->id,$photo['target'],$photo['entity'],$provider,$picture,true);
                return redirect('/?provider='.app(\App\Services\Tenant::class)->id.'&'.($photo['target']==='staff'?'staffPhotos':'profilePhotos').'=1&'.($ok?'photoUpdated=1':'photoError=unavailable'));
            }
            if(in_array($saved['mode'],['provider','provider-link'],true))return \App\Services\ProviderIdentity::complete($r,$provider,$id,filter_var($user['email']??'',FILTER_VALIDATE_EMAIL)?$user['email']:null,$user['name']??'',$saved['mode'],app(\App\Services\Tenant::class)->id);
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
                $allowed=$allowed&&app(\App\Services\Tenant::class)->id===1;
                if (!$allowed) {
                    return redirect("/?authError=denied");
                }
                \App\Services\ProviderIdentity::signIn($r,app(\App\Services\Tenant::class)->id);
                $picture=$provider==='google'?($user['picture']??null):(empty($user['picture']['data']['is_silhouette'])?($user['picture']['data']['url']??null):null);
                app(\App\Services\BusinessAvatar::class)->import(app(\App\Services\Tenant::class)->id,'logo',null,$provider,$picture);
                return redirect(app(\App\Services\Tenant::class)->id === 1 ? "/" : "/?provider=" . app(\App\Services\Tenant::class)->id);
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
            $r->session()->forget(["admin","platform_admin","guest_password_account","guest_password_version","provider_member_id","provider_member_version","provider_auth_method"]);$r->session()->put("guest_id", $account->id);
            $picture = $provider === 'google' ? ($user['picture'] ?? null) : (empty($user['picture']['data']['is_silhouette']) ? ($user['picture']['data']['url'] ?? null) : null);
            app(\App\Services\SocialAvatar::class)->import((int) $account->id, $provider, $picture);
            $tenant = app(\App\Services\Tenant::class)->id;
            if (($saved['returnTo'] ?? null) === 'booking') return redirect('/foglalas?provider=' . $tenant . '#public-form');
            if (in_array($saved['returnTo'] ?? null, ['messages', 'operatorMessages'], true)) return redirect('/uzenetek?provider=' . $tenant . ($saved['returnTo'] === 'operatorMessages' ? '&platformMessages=1' : ''));
            return redirect($tenant === 1 ? '/fiokom' : '/fiokom?provider=' . $tenant);
        } catch (\Throwable) {
            return redirect($target . "?authError=provider");
        }
    }
    public function guest(Request $r)
    {
        if($r->session()->get("admin")===true&&!$r->session()->get("guest_id"))\App\Services\ProviderGuest::ensure($r,(int)$r->session()->get("provider_id",1),$r->attributes->get("provider_member"));
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
                fn($b) => (int) ($b["guestId"] ?? 0) === (int) $account->id,
            ),
        );
        $providers = [];
        $allBookings = [];
        foreach (DB::table('salon_state')->get() as $row) {
            $salon = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
            $own = array_values(array_filter($salon['bookings'] ?? [], fn($b) => (int) ($b['guestId'] ?? 0) === (int) $account->id));
            $linked = collect($salon['clients'] ?? [])->contains(fn($c) => (int) ($c['guestId'] ?? 0) === (int) $account->id);
            if (!$own && !$linked) continue;
            $provider = ['id' => $row->id, 'name' => $salon['settings']['name'] ?? 'Szolgáltató'];
            $providers[] = $provider;
            foreach ($own as $booking) {
                $allBookings[] = array_merge(array_intersect_key($booking, array_flip(['id','start','status','attendance','token','serviceSnapshot','pricing','cancellationReason'])), ['provider' => $provider]);
            }
        }
        usort($allBookings, fn($a, $b) => strcmp($a['start'], $b['start']));
        return response()->json([
            "providerWorkspace"=>$r->session()->get("admin")?"/?provider=".$r->session()->get("provider_id",1):null,
            "passwordAccount"=>DB::table("guest_credentials")->where("guest_id",$account->id)->exists(),
            "providers" => $providers,
            "allBookings" => $allBookings,
            "id" => $account->id,
            "avatar" => !empty($account->avatar) ? "/api/guest/avatar/" . $account->id : null,
            "name" => $account->profile_name??$account->name,
            "email" => $account->contact_email ?? $account->email,
            "phone" => $account->phone ?? "",
            "bookings" => $bookings,
        ]);
    }
    public function logout(Request $r)
    {
        if($r->session()->get("admin")&&\App\Services\ProviderGuest::allowed($r))\App\Services\StaffAccess::clear($r);$r->session()->forget(["guest_id","guest_password_account","guest_password_version"]);
        return response()->json(["ok" => true]);
    }
}
