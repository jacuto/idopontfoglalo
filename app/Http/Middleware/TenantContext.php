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
        if(in_array($r->header('X-App-Language'),['hu','en','de'],true))app()->setLocale($r->header('X-App-Language'));
        if($r->session()->get('guest_password_account')){$c=DB::table('guest_credentials')->where('guest_id',$r->session()->get('guest_password_account'))->first();if(!$c||(int)$c->version!==(int)$r->session()->get('guest_password_version'))$r->session()->forget(['guest_id','guest_password_account','guest_password_version']);}
        \App\Services\StaffAccess::refresh($r);
        if(!$r->session()->get('provider_member_id')&&$r->session()->get('admin')===true&&$r->session()->get('provider_auth_method')!=='social'&&(int)$r->session()->get('provider_password_version',1)!==\App\Services\ProviderPasswords::version((int)$r->session()->get('provider_id',1)))$r->session()->forget(['admin','provider_id','provider_auth_method','provider_password_version']);
        $selector = $r->query('provider', $r->input('provider', 1));
        abort_unless(is_scalar($selector) && preg_match('/^[1-9][0-9]{0,8}$/', (string) $selector), 404);
        $id = (int) $selector;
        $public = in_array($r->path(), ['api/provider-login', 'api/provider-workspace', 'api/provider-register', 'api/provider-social', 'api/provider-password/forgot', 'api/provider-password/reset', 'api/providers', 'api/public', 'api/quote', 'api/slots', 'api/slots/next', 'api/bookings', 'api/manage', 'api/manage/review', 'api/manage/checkout', 'api/login', 'api/stripe/webhook'], true)
            || $r->is('api/platform/*') || $r->is('api/platform-messages*') || !$r->is('api/*') || $r->is('api/guest*') || $r->is('api/auth/*');
        if($r->is('api/calendar/connection')&&$r->query('owner','guest')==='guest')$public=true;
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
