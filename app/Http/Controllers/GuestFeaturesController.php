<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore, Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
final class GuestFeaturesController
{
    public function profile(Request $r){
        $id=(int)$r->session()->get('guest_id',0);abort_unless($id&&DB::table('guest_accounts')->where('id',$id)->exists(),401);
        $v=$r->validate(['email'=>'present|nullable|email|max:200','phone'=>'present|nullable|string|max:40']);
        $v=\App\Services\Contact::apply($v);
        DB::table('guest_accounts')->where('id',$id)->update(['contact_email'=>$v['email']??'','phone'=>$v['phone']??'','updated_at'=>now()]);
        return response()->json(['ok'=>true]);
    }
    public function passes(Request $r)
    {
        $id=(int)$r->session()->get('guest_id',0);abort_unless($id,401);$s=app(SalonStore::class)->read();
        $clients=collect($s['clients'])->filter(fn($c)=>(int)($c['guestId']??0)===$id)->pluck('id')->all();
        return response()->json(array_values(array_filter($s['clientPasses'],fn($p)=>in_array($p['clientId'],$clients,true))));
    }
    public function claim(Request $r)
    {
        $id=(int)$r->session()->get('guest_id',0);abort_unless($id,401);$v=$r->validate(['token'=>'required|string|regex:/^[a-f0-9]{48}$/']);
        app(SalonStore::class)->mutate(function(&$s)use($v,$id){
            foreach($s['bookings'] as &$b)if(hash_equals($b['token'],$v['token'])){
                abort_if(!empty($b['guestId'])&&(int)$b['guestId']!==$id,403,'Ez a foglalás már másik vendégfiókhoz tartozik.');
                foreach($s['clients'] as &$c)if($c['id']===($b['clientId']??null)){
                    abort_if(!empty($c['guestId'])&&(int)$c['guestId']!==$id,403,'Ez a vendégadatlap másik fiókhoz tartozik.');$c['guestId']=$id;
                }
                $b['guestId']=$id;return;
            }
            abort(404,'Érvénytelen foglalási hivatkozás.');
        });return response()->json(['ok'=>true]);
    }
    public function avatar(Request $r)
    {
        $id = $r->session()->get('guest_id'); abort_unless($id, 401);
        $data = $r->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096']);
        $account = DB::table('guest_accounts')->find($id); abort_unless($account, 401);
        $path = $data['image']->store('avatars', 'local');
        try { DB::table('guest_accounts')->where('id', $id)->update(['avatar' => $path, 'updated_at' => now()]); }
        catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        if ($account->avatar) Storage::disk('local')->delete($account->avatar);
        return response()->json(['ok' => true]);
    }
    public function image(Request $r, int $id)
    {
        $own = (int) $r->session()->get('guest_id', 0) === $id;
        $provider = $r->session()->get('admin') === true && (int) $r->session()->get('provider_id', 1) === app(Tenant::class)->id;
        if (!$own && $provider) {
            $own = (bool) collect(app(SalonStore::class)->read()['clients'])->first(fn($c) => \App\Services\Clients::visible($c) && (int) ($c['guestId'] ?? 0) === $id);
        }
        abort_unless($own, 404);
        $path = DB::table('guest_accounts')->where('id', $id)->value('avatar');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
    public function review(Request $r, string $id) { return app(FeaturesController::class)->review($r, $id, 'guest'); }
}
