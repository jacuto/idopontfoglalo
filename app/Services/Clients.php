<?php
namespace App\Services;
use Illuminate\Support\Str;
use InvalidArgumentException;
final class Clients
{
    public static function search(array $state, string $query): array
    {
        $query = mb_strtolower(trim($query));
        if (mb_strlen($query) < 3) return [];
        $digits = self::phone($query);
        $matches = array_values(array_filter($state['clients'], fn($c) => self::visible($c) && (str_contains(mb_strtolower($c['name']), $query) || (strlen($digits) >= 3 && str_contains(self::phone($c['phone'] ?? ''), $digits)))));
        $counts = []; $last = [];
        foreach ($state['bookings'] as $booking) {
            $id = $booking['clientId'] ?? null;
            if (!$id || in_array($booking['status'], ['cancelled', 'rejected'], true)) continue;
            $counts[$id] = ($counts[$id] ?? 0) + 1;
            $last[$id] = max($last[$id] ?? '', $booking['createdAt'] ?? $booking['start']);
        }
        usort($matches, fn($a, $b) => (($counts[$b['id']] ?? 0) <=> ($counts[$a['id']] ?? 0)) ?: strcmp($last[$b['id']] ?? '', $last[$a['id']] ?? '') ?: strnatcasecmp($a['name'], $b['name']) ?: strcmp($a['id'], $b['id']));
        return array_map(fn($c) => array_intersect_key($c, array_flip(['id', 'name', 'phone', 'email'])), array_slice($matches, 0, 20));
    }
    public static function editable(array $client,array $input):void {
        if(empty($client['guestId']))return;
        if(!empty($client['nameManaged'])&&isset($input['name'])&&$input['name']!==$client['name'])throw new InvalidArgumentException('A regisztrált vendég saját nevét a vendég módosíthatja a fiókjában.');
        foreach(['email','phone'] as $field)if(array_key_exists($field,$input)&&($field==='phone'?self::phone((string)($input[$field]??''))!==self::phone((string)($client[$field]??'')):mb_strtolower(trim((string)($input[$field]??'')))!==mb_strtolower(trim((string)($client[$field]??''))))) throw new InvalidArgumentException('A regisztrált vendég emailjét és telefonszámát csak a vendég módosíthatja a saját fiókjában.');
    }
    public static function visible(array $client):bool {return empty($client['deletedAt']);}
    public static function phone(string $value): string {
        $digits=preg_replace('/[^0-9]/','',$value);
        if(str_starts_with($digits,'0036'))return substr($digits,2);
        if(str_starts_with($digits,'06') && strlen($digits)>=9)return '36'.substr($digits,2);
        if((strlen($digits)===9 && preg_match('/^(20|30|31|50|70)/',$digits)) || (strlen($digits)===8 && str_starts_with($digits,'1')))return '36'.$digits;
        return $digits;
    }
    public static function sameContact(array $a,array $b):bool {
        $email=mb_strtolower(trim($a['email']??''));$phone=self::phone($a['phone']??'');
        return ($email!=='' && $email===mb_strtolower(trim($b['email']??''))) || ($phone!=='' && $phone===self::phone($b['phone']??''));
    }
    public static function preventDuplicate(array $clients,array $candidate):void {
        foreach($clients as $client)if(self::visible($client)&&$client['id']!==($candidate['id']??null)&&self::sameContact($client,$candidate))throw new InvalidArgumentException('Ezzel az emailcímmel vagy telefonszámmal már van vendég a saját listádban. Keresd meg és válaszd ki a meglévő vendéget.');
    }
    public static function attach(array &$state, array &$booking, ?string $selected, bool $provider): void
    {
        if ($provider) $booking['guestId'] = null;
        if($provider&&!empty($booking['anonymous'])&&empty($booking['phone'])&&empty($booking['email'])){$booking['clientId']=null;return;}
        if ($provider && $selected) {
            foreach ($state['clients'] as $c) {
                if ($c['id'] === $selected && self::visible($c)) {
                    $booking['clientId'] = $c['id'];
                    foreach (['name', 'phone', 'email'] as $key) $booking[$key] = $c[$key] ?? '';
                    // Reuse an already proved account relationship; selection cannot create one.
                    $booking['guestId'] = $c['guestId'] ?? null;
                    return;
                }
            }
            throw new InvalidArgumentException('A kiválasztott vendég nem található a saját vendégeid között.');
        }
        if($provider){
            $matches=array_values(array_filter($state['clients'],fn($c)=>self::visible($c)&&self::sameContact($c,$booking)));
            if(count($matches)>1)throw new InvalidArgumentException('Több egyező vendég található. Válaszd ki a megfelelő vendéget a találati listából.');
            if($matches){$c=$matches[0];$sameName=mb_strtolower(trim($c['name']))===mb_strtolower(trim($booking['name']));$both=!empty($booking['email'])&&!empty($booking['phone'])&&mb_strtolower(trim($booking['email']))===mb_strtolower(trim($c['email']??''))&&self::phone($booking['phone'])===self::phone($c['phone']??'');if(!$sameName&&!$both)throw new InvalidArgumentException('Ezzel az elérhetőséggel már van vendég, eltérő névvel vagy másik elérhetőséggel. Ellenőrizd az adatokat és válaszd ki a megfelelő vendéget.');$booking['clientId']=$c['id'];$booking['guestId']=$c['guestId']??null;foreach(['name','phone','email'] as $key)$booking[$key]=$c[$key]??'';return;}
        }
        // Only an authenticated guest identity may reuse its own linked record automatically.
        $guestId = $booking['guestId'] ?? null;
        if ($guestId && !$provider) {
            foreach ($state['clients'] as &$c) {
                if ((int) ($c['guestId'] ?? 0) === (int) $guestId) {
                    unset($c['deletedAt']);
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
        abort_unless($c && self::visible($c), 404, 'Nincs ilyen vendég.');
        $bookings = array_values(array_filter($s['bookings'], fn($b) => ($b['clientId'] ?? null) === $id));
        $stats = ['total' => count($bookings), 'cancelled' => 0, 'attended' => 0, 'no_show' => 0];
        foreach ($bookings as $b) {
            if ($b['status'] === 'cancelled') $stats['cancelled']++;
            if (in_array($b['status'],['confirmed','no_show'],true) && isset($stats[$b['attendance'] ?? ''])) $stats[$b['attendance']]++;
        }
        $avatar = null;
        if (!empty($c['guestId'])) $avatar = \Illuminate\Support\Facades\DB::table('guest_accounts')->where('id', $c['guestId'])->value('avatar');
        return ['client' => $c, 'avatar' => $avatar ? '/api/guest/avatar/' . $c['guestId'] : null, 'stats' => $stats, 'bookings' => $bookings,
            'reviews' => array_values(array_filter($s['reviews'], fn($r) => $r['direction'] === 'provider' && $r['clientId'] === $id))];
    }
}
