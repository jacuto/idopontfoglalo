<?php
namespace App\Services;
use Carbon\CarbonImmutable as Date;
use InvalidArgumentException;
final class PassValidity
{
    private static function bound(?string $date,bool $end=false):?Date {return $date?($end?Date::parse($date,'Europe/Budapest')->endOfDay():Date::parse($date,'Europe/Budapest')->startOfDay()):null;}
    public static function assigned(array &$pass):void {
        if(($pass['validityMode']??'assigned')==='first_use'){ $pass['startsAt']=self::bound($pass['validFrom']??null)?->toISOString();$pass['expiresAt']=self::bound($pass['validUntil']??null,true)?->toISOString();return; }
        $start=self::bound($pass['validFrom']??null)??Date::now();
        $pass['startsAt']=$start->toISOString();$pass['expiresAt']=(self::bound($pass['validUntil']??null,true)??$start->addDays($pass['days']))->toISOString();
    }
    public static function recalculate(array &$pass,array $state,array $candidate):void {
        if(($pass['validityMode']??'assigned')!=='first_use')return;
        $visits=array_values(array_filter($state['bookings'],fn($b)=>($b['passId']??null)===$pass['id'] && $b['id']!==$candidate['id'] && !in_array($b['status'],['cancelled','rejected'],true)));
        if(!in_array($candidate['status']??'pending',['cancelled','rejected'],true))$visits[]=$candidate;
        if(!$visits){self::assigned($pass);return;}
        $first=collect($visits)->min(fn($b)=>Date::parse($b['start'])->getTimestamp());$start=Date::createFromTimestampUTC($first);$end=$start->addDays($pass['days']);
        $from=self::bound($pass['validFrom']??null);$until=self::bound($pass['validUntil']??null,true);
        if($until&&$until->lt($end))$end=$until;
        foreach($visits as $b){$date=Date::parse($b['start']);if(($from&&$date->lt($from))||$date->gt($end))throw new InvalidArgumentException('Ez az időpont nem fér bele a bérlet érvényességébe. A korábban lefoglalt alkalmakat is figyelembe vettük.');}
        $pass['startsAt']=$start->toISOString();$pass['expiresAt']=$end->toISOString();
    }
    public static function check(array $pass,array $booking):void {
        $date=Date::parse($booking['start']);
        if((!empty($pass['startsAt'])&&$date->lt(Date::parse($pass['startsAt'])))||(!empty($pass['expiresAt'])&&(Date::now()->gt(Date::parse($pass['expiresAt']))||$date->gt(Date::parse($pass['expiresAt'])))))throw new InvalidArgumentException('Ez az időpont a bérlet érvényességén kívül van.');
    }
}
