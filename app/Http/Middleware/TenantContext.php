<?php
namespace App\Http\Middleware;
use App\Services\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TenantContext
{
    public function handle(Request $r, Closure $next)
    {
        $selector = $r->query('provider', $r->input('provider', 1));
        abort_unless(is_scalar($selector) && preg_match('/^[1-9][0-9]{0,8}$/', (string) $selector), 404);
        $id = (int) $selector;
        $public = in_array($r->path(), ['api/public', 'api/slots', 'api/slots/next', 'api/bookings', 'api/manage', 'api/manage/checkout', 'api/login', 'api/stripe/webhook'], true)
            || $r->is('api/platform/*') || $r->is('api/platform-messages*') || !$r->is('api/*') || $r->is('api/guest*') || $r->is('api/auth/*');
        // Old admin sessions remain scoped exclusively to the original salon.
        $owner = (int) $r->session()->get('provider_id', 1);
        if ($r->session()->get('admin') === true && !$public) {
            abort_unless(!$r->has('provider') || $owner === $id, 403, 'Más vállalkozás adataihoz nincs hozzáférés.');
            $id = $owner;
        }
        if ($id !== 1) {
            abort_unless(DB::table('providers')->where('id', $id)->where('active', true)->exists(), 404);
        }
        app(Tenant::class)->id = $id;
        return $next($r);
    }
}
