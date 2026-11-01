<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore, Tenant};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Crypt};
use Illuminate\Support\Str;
final class MessagesController
{
    public function handle(Request $r)
    {
        $provider = $r->session()->get('admin') === true && (int) $r->session()->get('provider_id', 1) === app(Tenant::class)->id;
        $guest = (int) $r->session()->get('guest_id', 0);
        abort_unless($provider || $guest, 401);
        $method = $r->method();
        if ($method === 'POST') {
            $v = $r->validate(['guestId' => 'sometimes|integer|min:1', 'text' => 'required|string|max:5000']);
            $toGuest = $provider ? (int) ($v['guestId'] ?? 0) : $guest;
            return response()->json(app(SalonStore::class)->mutate(function (&$s) use ($v, $provider, $toGuest) {
                $client = collect($s['clients'])->first(fn($c) => (int) ($c['guestId'] ?? 0) === $toGuest);
                abort_unless($client, 404, 'Nincs ehhez a vállalkozáshoz kapcsolt vendégfiók. Előbb bejelentkezve foglalj időpontot.');
                $message = ['id' => (string) Str::uuid(), 'guestId' => $toGuest, 'sender' => $provider ? 'provider' : 'guest', 'text' => $v['text'], 'createdAt' => now()->toISOString(), 'readAt' => null];
                $s['messages'][] = $message;
                $email = $provider ? DB::table('guest_accounts')->where('id', $toGuest)->value('email') : $s['settings']['providerEmail'];
                if ($email) {
                    $payload = ['kind' => 'message', 'messageId' => $message['id'], 'recipient' => $email, 'guest' => $provider];
                    DB::table('deliveries')->insertOrIgnore(['provider_id' => app(Tenant::class)->id, 'dedup' => hash('sha256', 'message:' . $message['id']), 'payload' => Crypt::encryptString(json_encode($payload)), 'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
                return $message;
            }), 201);
        }
        if ($method === 'GET') {
            $s = app(SalonStore::class)->read();
            return response()->json(array_values(array_filter($s['messages'], fn($m) => $provider || $m['guestId'] === $guest)));
        }
        if ($method === 'PATCH') {
            $v = $r->validate(['guestId' => 'sometimes|integer|min:1']);
            $conversation = $provider ? (int) ($v['guestId'] ?? 0) : $guest;
            app(SalonStore::class)->mutate(function (&$s) use ($provider, $conversation) {
                foreach ($s['messages'] as &$m) if ($m['guestId'] === $conversation && $m['sender'] !== ($provider ? 'provider' : 'guest') && !$m['readAt']) $m['readAt'] = now()->toISOString();
            });
            return response()->json(['ok' => true]);
        }
        abort(405);
    }
}
