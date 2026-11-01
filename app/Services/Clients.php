<?php
namespace App\Services;
use Illuminate\Support\Str;
use InvalidArgumentException;
final class Clients
{
    public static function phone(string $value): string { return preg_replace('/[^0-9]/', '', $value); }
    public static function attach(array &$state, array &$booking, ?string $selected, bool $provider): void
    {
        if ($provider) $booking['guestId'] = null;
        if ($provider && $selected) {
            foreach ($state['clients'] as $c) {
                if ($c['id'] === $selected) {
                    $booking['clientId'] = $c['id'];
                    foreach (['name', 'phone', 'email'] as $key) $booking[$key] = $c[$key] ?? '';
                    // Reuse an already proved account relationship; selection cannot create one.
                    $booking['guestId'] = $c['guestId'] ?? null;
                    return;
                }
            }
            throw new InvalidArgumentException('A kiválasztott vendég nem található a saját vendégeid között.');
        }
        // Only an authenticated guest identity may reuse its own linked record automatically.
        $guestId = $booking['guestId'] ?? null;
        if ($guestId && !$provider) {
            foreach ($state['clients'] as $c) {
                if ((int) ($c['guestId'] ?? 0) === (int) $guestId) {
                    $booking['clientId'] = $c['id'];
                    return;
                }
            }
        }
        $client = ['id' => (string) Str::uuid(), 'name' => $booking['name'], 'phone' => $booking['phone'], 'email' => $booking['email']];
        if (!$provider && $guestId) $client['guestId'] = $guestId;
        $state['clients'][] = $client;
        $booking['clientId'] = $client['id'];
    }
    public static function detail(array $s, string $id): array
    {
        $c = collect($s['clients'])->firstWhere('id', $id);
        abort_unless($c, 404, 'Nincs ilyen vendég.');
        $bookings = array_values(array_filter($s['bookings'], fn($b) => ($b['clientId'] ?? null) === $id));
        $stats = ['total' => count($bookings), 'cancelled' => 0, 'attended' => 0, 'no_show' => 0];
        foreach ($bookings as $b) {
            if ($b['status'] === 'cancelled') $stats['cancelled']++;
            if ($b['status'] === 'confirmed' && isset($stats[$b['attendance'] ?? ''])) $stats[$b['attendance']]++;
        }
        $avatar = null;
        if (!empty($c['guestId'])) $avatar = \Illuminate\Support\Facades\DB::table('guest_accounts')->where('id', $c['guestId'])->value('avatar');
        return ['client' => $c, 'avatar' => $avatar ? '/api/guest/avatar/' . $c['guestId'] : null, 'stats' => $stats, 'bookings' => $bookings,
            'reviews' => array_values(array_filter($s['reviews'], fn($r) => $r['direction'] === 'provider' && $r['clientId'] === $id))];
    }
}
