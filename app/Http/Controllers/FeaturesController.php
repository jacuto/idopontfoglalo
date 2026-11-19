<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore, Clients, Offers, Tenant};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class FeaturesController
{
    public function __construct(private SalonStore $store) {}
    public function handle(Request $r)
    {
        $path = $r->path(); $method = $r->method();
        if ($path === 'api/clients/search' && $method === 'GET') {
            return response()->json(Clients::search($this->store->read(), (string)$r->query('q', '')))->header('Cache-Control','no-store');
        }
        if (preg_match('#^api/clients/([a-zA-Z0-9_-]+)/detail$#', $path, $m) && $method === 'GET') return response()->json(Clients::detail($this->store->read(), $m[1]));
        if (preg_match('#^api/bookings/([a-zA-Z0-9_-]+)/review$#', $path, $m) && $method === 'POST') return $this->review($r, $m[1], 'provider');
        if ($path === 'api/catalog-insights' && $method === 'GET')return response()->json(\App\Services\CatalogInsights::build($this->store->read()))->header('Cache-Control','no-store');
        if (preg_match('#^api/client-passes/([a-zA-Z0-9_-]+)$#',$path,$match)&&in_array($method,['PATCH','DELETE'],true)) {
            $v=$r->validate(['version'=>'required|integer|min:0','remaining'=>($method==='PATCH'?'required':'sometimes').'|integer|min:0|max:1000','expiresAt'=>'nullable|date']);
            $actor='provider:'.app(Tenant::class)->id.':'.($r->session()->get('provider_member_id')??'owner');
            return response()->json($this->store->mutate(fn(&$s)=>\App\Services\RecordCorrections::issuedPass($s,$match[1],$v,$method==='DELETE',$actor)));
        }
        if ($path === 'api/profile' && $method === 'PUT') {
            $r->merge(\App\Services\WebAddresses::fields($r->all()));
            $data = $r->validate([
                'intro' => 'nullable|string|max:500', 'description' => 'nullable|string|max:12000',
                'city'=>'nullable|string|max:100','zip'=>'nullable|string|max:20','categories'=>'sometimes|array|max:7','categories.*'=>\Illuminate\Validation\Rule::in(array_keys(\App\Services\ProviderDiscovery::CATEGORIES)),
                'address' => 'nullable|string|max:500', 'directions' => 'nullable|string|max:1500', 'phone' => 'nullable|string|max:40',
                'email' => 'nullable|email|max:200', 'website' => 'nullable|url:http,https|max:1000',
                'mapUrl' => 'nullable|url:http,https|max:1000', 'facebook' => 'nullable|url:http,https|max:1000', 'instagram' => 'nullable|url:http,https|max:1000',
                'logo' => ['nullable','string','max:1000',self::publicImageRule()], 'cover' => ['nullable','string','max:1000',self::publicImageRule()], 'photos' => 'sometimes|array|max:12', 'photos.*' => ['string','max:1000',self::publicImageRule()],
                'acceptedPayments'=>'sometimes|array|max:6','acceptedPayments.*'=>'in:cash,card,transfer,szep,otp_szep,other','hours' => 'nullable|string|max:1500', 'terms' => 'nullable|string|max:5000',
            ]);
            $data=\App\Services\Contact::apply($data);
            foreach(['description','terms'] as $key)if(isset($data[$key]))$data[$key]=\App\Services\SafeHtml::clean($data[$key]);
            $this->store->mutate(function (&$s) use ($data) { if(isset($data['photos'])&&count($data['photos'])>5&&$data['photos']!==($s['profile']['photos']??[]))throw new \InvalidArgumentException('Legfeljebb 5 üzletfotó adható meg.');$s['profile'] = array_replace($s['profile']??[], $data); });
            \Illuminate\Support\Facades\Cache::forget(ProviderDirectoryController::CACHE_KEY);
            return response()->json(['ok' => true]);
        }
        if ($method === 'DELETE' && preg_match('#^api/(coupons|passes)/([a-zA-Z0-9_-]+)$#', $path, $m)) {
            $this->store->mutate(function (&$s) use ($m) {
                foreach ($s[$m[1]] as &$offer) if ($offer['id'] === $m[2]) {
                    $offer['active'] = false; $offer['deletedAt'] = now()->toISOString(); return;
                }
                abort(404);
            });
            return response()->json(['ok'=>true]);
        }
        if ($path === 'api/coupons' && $method === 'POST') {
            $data = $r->validate([
                'id' => 'nullable|string|max:80|regex:/^[a-zA-Z0-9_-]+$/', 'code' => 'required|alpha_dash|max:80', 'name' => 'required|string|max:150',
                'startsAt' => 'required|date', 'endsAt' => 'required|date|after:startsAt', 'services' => 'present|array', 'services.*' => 'string|max:80',
                'type' => 'required|in:percent,fixed', 'value' => 'required|integer|min:1|max:100000000', 'active' => 'required|boolean',
                'limit' => 'nullable|integer|min:1', 'perClient' => 'required|integer|min:1|max:10000', 'public' => 'required|boolean', 'firstVisit' => 'required|boolean',
            ]);
            if ($data['type'] === 'percent' && $data['value'] > 100) throw new InvalidArgumentException('A százalék legfeljebb 100 lehet.');
            $data['id'] = $data['id'] ?? (string) Str::uuid(); $data['code'] = strtoupper($data['code']);
            $this->store->mutate(function (&$s) use ($data) {
                if(collect($s['coupons'])->contains(fn($c)=>$c['id']===$data['id']&&!empty($c['deletedAt'])))throw new InvalidArgumentException('A törölt kupon nem szerkeszthető.');
                if(empty($s['settings']['couponsEnabled'])&&!collect($s['coupons'])->contains('id',$data['id']))throw new InvalidArgumentException('Az új kuponok létrehozása ki van kapcsolva.');
                if (array_diff($data['services'], array_column($s['services'], 'id'))) throw new InvalidArgumentException('Ismeretlen szolgáltatás.');
                foreach ($s['coupons'] as $c) if ($c['code'] === $data['code'] && $c['id'] !== $data['id']) throw new InvalidArgumentException('Ez a kuponkód már létezik.');
                $s['coupons'] = array_values(array_filter($s['coupons'], fn($c) => $c['id'] !== $data['id'])); $s['coupons'][] = $data;
            });
            return response()->json($data, 201);
        }
        if ($path === 'api/passes' && $method === 'POST') {
            $data = $r->validate(['id' => 'nullable|string|max:80|regex:/^[a-zA-Z0-9_-]+$/', 'name' => 'required|string|max:150', 'description' => 'nullable|string|max:3000', 'price' => 'required|integer|min:0|max:100000000', 'visits' => 'required|integer|between:1,1000', 'days' => 'required|integer|between:1,3650', 'services' => 'present|array', 'services.*' => 'string', 'active' => 'required|boolean', 'terms' => 'nullable|string|max:3000', 'validityMode'=>'sometimes|in:assigned,first_use','validFrom'=>'nullable|date_format:Y-m-d','validUntil'=>'nullable|date_format:Y-m-d']);
            if(!empty($data['validFrom'])&&!empty($data['validUntil'])&&$data['validFrom']>$data['validUntil'])throw new InvalidArgumentException('A bérlet záró dátuma nem lehet a kezdő dátum előtt.');
            foreach(['description','terms'] as $key)if(isset($data[$key]))$data[$key]=\App\Services\SafeHtml::clean($data[$key]);
            $data['validityMode']=$data['validityMode']??'assigned';
            $data['id'] = $data['id'] ?? (string) Str::uuid();
            $this->store->mutate(function (&$s) use ($data) {
                if(collect($s['passes'])->contains(fn($p)=>$p['id']===$data['id']&&!empty($p['deletedAt'])))throw new InvalidArgumentException('A törölt bérlet nem szerkeszthető.');
                if(empty($s['settings']['passesEnabled'])&&!collect($s['passes'])->contains('id',$data['id']))throw new InvalidArgumentException('Az új bérletek létrehozása ki van kapcsolva.');
                if (array_diff($data['services'], array_column($s['services'], 'id'))) throw new InvalidArgumentException('Ismeretlen szolgáltatás.');
                $s['passes'] = array_values(array_filter($s['passes'], fn($p) => $p['id'] !== $data['id'])); $s['passes'][] = $data;
            });
            return response()->json($data, 201);
        }
        if ($path === 'api/client-passes' && $method === 'POST') {
            $data = $r->validate(['clientId' => 'required|string', 'passId' => 'required|string']);
            return response()->json($this->store->mutate(function (&$s) use ($data) {
                abort_unless(collect($s['clients'])->firstWhere('id', $data['clientId']), 404);
                $p = collect($s['passes'])->firstWhere('id', $data['passId']); abort_unless($p && $p['active'] && empty($p['deletedAt']), 404);
                $assigned = ['id' => (string) Str::uuid(), 'passTypeId'=>$p['id'],'initialVisits'=>$p['visits'],'assignedAt'=>now()->toISOString(),'version'=>0, 'clientId' => $data['clientId'], 'name' => $p['name'], 'services' => $p['services'], 'remaining' => $p['visits'], 'price' => $p['price'], 'terms' => $p['terms'] ?? '', 'validityMode'=>$p['validityMode']??'assigned','days'=>$p['days'],'validFrom'=>$p['validFrom']??null,'validUntil'=>$p['validUntil']??null,'startsAt'=>null,'expiresAt'=>null,'reservations' => []];
                \App\Services\PassValidity::assigned($assigned);
                $s['clientPasses'][] = $assigned; return $assigned;
            }), 201);
        }
        abort(404);
    }
    public function publicDiscounts(Request $r)
    {
        $v=$r->validate(['services'=>'sometimes|array|max:20','services.*'=>'string|max:80','start'=>'nullable|date']);
        $s=$this->store->read();$ids=$v['services']??[];
        if(!$ids || array_diff($ids,array_column($s['services'],'id')))return response()->json(['hasCoupon'=>false,'passes'=>[]]);
        $guest=(int)$r->session()->get('guest_id',0);$clientIds=collect($s['clients'])->filter(fn($c)=>Clients::visible($c)&&$guest&&(int)($c['guestId']??0)===$guest)->pluck('id')->all();
        $b=['id'=>'discount-preview','services'=>$ids,'start'=>$v['start']??now()->toISOString(),'guestId'=>$guest,'clientId'=>$clientIds[0]??'', 'public'=>true,'serviceSnapshot'=>array_values(array_filter($s['services'],fn($service)=>in_array($service['id'],$ids,true)))];
        $hasCoupon=false;
        if(!empty($s['settings']['couponsEnabled']))foreach($s['coupons'] as $c){
            if(empty($c['active'])||!empty($c['deletedAt'])||!now()->between(CarbonImmutable::parse($c['startsAt']),CarbonImmutable::parse($c['endsAt']))||(!empty($c['services'])&&array_diff($ids,$c['services'])))continue;
            if($guest){try{app(Offers::class)->quote($s,$b,$c['code']);}catch(InvalidArgumentException){continue;}}
            else if(!empty($c['limit'])&&count(array_filter($s['bookings'],fn($old)=>($old['pricing']['couponId']??null)===$c['id']&&!in_array($old['status'],['cancelled','rejected'],true)))>=$c['limit'])continue;
            $hasCoupon=true;break;
        }
        $passes=[];
        if($guest)foreach($s['clientPasses'] as $pass){
            if(!empty($pass['revokedAt'])||!in_array($pass['clientId'],$clientIds,true)||$pass['remaining']<1||(!empty($pass['services'])&&array_diff($ids,$pass['services'])))continue;
            try{\App\Services\PassValidity::recalculate($pass,$s,$b);\App\Services\PassValidity::check($pass,$b);}catch(InvalidArgumentException){continue;}
            $passes[]=array_intersect_key($pass,array_flip(['id','name','remaining','expiresAt']));
        }
        return response()->json(['hasCoupon'=>$hasCoupon,'passes'=>$passes])->header('Cache-Control','no-store');
    }
    public function review(Request $r, string $bookingId, string $direction)
    {
        $data = $r->validate(['score' => 'required|integer|between:1,5', 'text' => 'required|string|max:3000']);
        $review = $this->store->mutate(function (&$s) use ($r, $bookingId, $direction, $data) {
            $b = collect($s['bookings'])->firstWhere('id', $bookingId); abort_unless($b, 404);
            if ($direction === 'guest') abort_unless($r->session()->get('guest_id') && (int) $b['guestId'] === (int) $r->session()->get('guest_id'), 403);
            if ($b['status'] !== 'confirmed' || ($b['attendance'] ?? '') !== 'attended') throw new InvalidArgumentException('Értékelést csak a szolgáltató által igazolt látogatás után írhatsz.');
            foreach ($s['reviews'] as $v) if ($v['bookingId'] === $bookingId && $v['direction'] === $direction) throw new InvalidArgumentException('Erre a látogatásra már írtál értékelést.');
            $v = $data + ['id' => (string) Str::uuid(), 'bookingId' => $bookingId, 'clientId' => $b['clientId'], 'direction' => $direction, 'createdAt' => now()->toISOString()];
            $s['reviews'][] = $v; return $v;
        });
        return response()->json($review, 201);
    }
    public function quote(Request $r)
    {
        $s = $this->store->read();
        $data = $r->validate(['services' => 'required|array|min:1|max:20', 'services.*' => 'string', 'coupon' => 'nullable|string|max:80', 'passId'=>'nullable|string|max:80','start'=>'nullable|date', 'clientId' => 'nullable|string|max:80']);
        $b = ['services' => $data['services'], 'serviceSnapshot' => array_map(fn($id) => app(\App\Services\BookingRules::class)->service($s['services'], $id), $data['services']), 'guestId' => $r->session()->get('guest_id'), 'clientId' => '', 'public' => true];
        if ($r->session()->get('admin') === true && !$r->boolean('public') && (int) $r->session()->get('provider_id', 1) === app(Tenant::class)->id) {
            \App\Services\StaffAccess::ownClient($s,$data['clientId']??null,$r);
            abort_unless(empty($data['clientId']) || collect($s['clients'])->firstWhere('id', $data['clientId']), 404);
            $b['public'] = false; $b['clientId'] = $data['clientId'] ?? '';
        }
        if (!empty($b['guestId']) && $b['public']) $b['clientId'] = collect($s['clients'])->first(fn($c) => (int)($c['guestId']??0) === (int)$b['guestId'])['id'] ?? '';
        $b['id']='preview';$b['start']=$data['start']??now()->toISOString();
        $b['pricing']=app(Offers::class)->quote($s,$b,$data['coupon']??null);
        app(Offers::class)->reservePass($s,$b,$data['passId']??null,!$b['public']);
        return response()->json(array_replace($b['pricing'],['prepayment'=>['enabled'=>\App\Services\BookingPrepayments::enabled($s),'requiredAmount'=>\App\Services\BookingPrepayments::enabled($s)?\App\Services\BookingPrepayments::target($s,$b):0]]));
    }
    private static function publicImageRule():\Closure{return static function($attribute,$value,$fail){if(!\App\Services\ProviderDiscovery::image($value)&&!(filter_var($value,FILTER_VALIDATE_URL)&&parse_url($value,PHP_URL_SCHEME)==='http'))$fail('Érvényes képcímet adj meg.');};}
}
