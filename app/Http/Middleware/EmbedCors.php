<?php
namespace App\Http\Middleware;
use App\Services\SalonStore;
use Closure;
use Illuminate\Http\Request;
class EmbedCors
{
    public function handle(Request $r, Closure $next)
    {
        $origin = $r->header("Origin");
        $public = in_array(
            "/" . $r->path(),
            ["/api/public", "/api/slots", "/api/slots/next", "/api/bookings"],
            true,
        );
        $allowed = false;
        if ($origin && $public) {
            try {
                $selector = $r->query('provider', 1);
                if (!is_scalar($selector) || !preg_match('/^[1-9][0-9]{0,8}$/', (string) $selector)) abort(404);
                $allowed = app(\App\Services\Tenant::class)->run((int) $selector, fn() => in_array(
                    $origin,
                    app(SalonStore::class)->read()["settings"]["embedOrigins"],
                    true,
                ));
            } catch (\Throwable) {
            }
        }
        if ($r->method() === "OPTIONS" && $public) {
            $response = response("", 204);
        } else {
            $r->attributes->set("embedAllowed", $allowed);
            $response = $next($r);
        }
        if ($allowed) {
            $response->headers->set("Access-Control-Allow-Origin", $origin);
            $response->headers->set("Vary", "Origin");
            $response->headers->set(
                "Access-Control-Allow-Methods",
                "GET, POST, OPTIONS",
            );
            $response->headers->set(
                "Access-Control-Allow-Headers",
                "Content-Type, Accept",
            );
        }
        return $response;
    }
}
