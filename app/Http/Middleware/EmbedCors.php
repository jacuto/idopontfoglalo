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
            ["/api/public", "/api/slots", "/api/bookings"],
            true,
        );
        $allowed = false;
        if ($origin && $public) {
            try {
                $allowed = in_array(
                    $origin,
                    app(SalonStore::class)->read()["settings"]["embedOrigins"],
                    true,
                );
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
