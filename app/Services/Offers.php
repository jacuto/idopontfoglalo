<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
final class Offers
{
    public static function total(array $b): int { return (int) (($b['status']??'')==='no_show'?($b['noShowFee']??0):($b['pricing']['payable'] ?? array_sum(array_column($b['serviceSnapshot'], 'price')))); }
    public function quote(array $s, array $b, ?string $code): array
    {
        $original = (int) array_sum(array_column($b['serviceSnapshot'], 'price'));
        $result = ['original' => $original, 'discount' => 0, 'payable' => $original];
        if (!$code) return $result;
        $coupon = collect($s['coupons'])->first(fn($c) => strtoupper($c['code']) === strtoupper(trim($code)));
        if (!$coupon || !$coupon['active']) throw new InvalidArgumentException('A kupon nem található vagy nem aktív. Ellenőrizd a kódot.');
        if (now()->lt(CarbonImmutable::parse($coupon['startsAt'])) || now()->gt(CarbonImmutable::parse($coupon['endsAt']))) throw new InvalidArgumentException('A kupon még nem érvényes vagy már lejárt.');
        if (!empty($b['public']) && empty($b['guestId'])) throw new InvalidArgumentException('A kupon használatához lépj be a vendégfiókodba.');
        if (!empty($coupon['services']) && array_diff($b['services'], $coupon['services'])) throw new InvalidArgumentException('Ez a kupon nem használható az összes kiválasztott szolgáltatásra.');
        $uses = array_filter($s['bookings'], fn($v) => ($v['pricing']['couponId'] ?? null) === $coupon['id'] && !in_array($v['status'], ['cancelled', 'rejected'], true));
        $same = fn($v) => (!empty($b['guestId']) && (int) ($v['guestId'] ?? 0) === (int) $b['guestId']) || ($v['clientId'] ?? null) === $b['clientId'];
        if ((!empty($coupon['limit']) && count($uses) >= $coupon['limit']) || count(array_filter($uses, $same)) >= $coupon['perClient']) throw new InvalidArgumentException('A kupon felhasználási kerete elfogyott.');
        if (!empty($coupon['firstVisit'])) {
            foreach ($s['bookings'] as $old) {
                if ($same($old) && ($old['attendance'] ?? '') === 'attended') throw new InvalidArgumentException('Az első alkalmas ajánlatot már nem veheted igénybe ennél a szolgáltatónál.');
                if ($same($old) && !in_array($old['status'], ['cancelled', 'rejected'], true) && !empty($old['pricing']['firstVisit'])) throw new InvalidArgumentException('Már van első alkalmas foglalásod.');
            }
        }
        $discount = min($original, $coupon['type'] === 'percent' ? (int) round($original * $coupon['value'] / 100) : (int) $coupon['value']);
        return ['original' => $original, 'discount' => $discount, 'payable' => $original - $discount, 'couponId' => $coupon['id'], 'code' => $coupon['code'], 'firstVisit' => $coupon['firstVisit']];
    }
    public function reservePass(array &$s, array &$b, ?string $id, bool $provider): void
    {
        if (!$id) return;
        if (!empty($b['pricing']['couponId'])) throw new InvalidArgumentException('Kupon és bérlet egyszerre nem használható.');
        foreach ($s['clientPasses'] as &$pass) {
            if ($pass['id'] !== $id) continue;
            $owner = $pass['clientId'] === $b['clientId'];
            if (!$provider) {
                $client = collect($s['clients'])->firstWhere('id', $pass['clientId']);
                $owner = !empty($b['guestId']) && (int) ($client['guestId'] ?? 0) === (int) $b['guestId'];
            }
            abort_unless($owner, 404);
            if($pass['remaining']<1)throw new InvalidArgumentException('A bérlet alkalmai elfogytak.');
            PassValidity::recalculate($pass,$s,$b);PassValidity::check($pass,$b);
            if (!empty($pass['services']) && array_diff($b['services'], $pass['services'])) throw new InvalidArgumentException('A bérlet nem érvényes ezekre a szolgáltatásokra.');
            $pass['remaining']--;
            $pass['reservations'][$b['id']] = 'reserved';
            $b['passId'] = $id;
            $b['pricing'] = ['original' => $b['pricing']['original'], 'discount' => $b['pricing']['original'], 'payable' => 0, 'passId' => $id];
            return;
        }
        abort(404, 'Nincs ilyen bérlet.');
    }
    public function transitionPass(array &$s, array $old, array &$next): void
    {
        if (!empty($old['attendance']) && $old['attendance'] !== 'unmarked' && ($old['status'] !== $next['status'] || ($next['attendance'] ?? '') !== $old['attendance'])) {
            throw new InvalidArgumentException('A lezárt látogatás állapota nem írható át. Egyeztess az üzemeltetővel.');
        }
        if (empty($old['passId'])) return;
        $restoring=in_array($old['status'],['cancelled','rejected'],true)&&in_array($next['status'],['pending','confirmed'],true);
        foreach ($s['clientPasses'] as &$pass) {
            if ($pass['id'] !== $old['passId']) continue;
            PassValidity::recalculate($pass,$s,$next);
            if($next['start']!==$old['start'] && !in_array($next['status'],['cancelled','rejected'],true))PassValidity::check($pass,$next);
            $current = $pass['reservations'][$old['id']] ?? 'reserved';
            if($restoring&&$current==='released'){if($pass['remaining']<1)throw new InvalidArgumentException('A bérlet alkalmai időközben elfogytak.');PassValidity::check($pass,$next);$pass['remaining']--;$pass['reservations'][$old['id']]='reserved';}
            if (in_array($next['status'], ['cancelled', 'rejected'], true) && $current === 'reserved') {
                $pass['remaining']++;
                $pass['reservations'][$old['id']] = 'released';
            } elseif (in_array($next['attendance'] ?? '', ['attended', 'no_show'], true) && $current === 'reserved') {
                $pass['reservations'][$old['id']] = 'consumed';
            }
        }
    }
}
