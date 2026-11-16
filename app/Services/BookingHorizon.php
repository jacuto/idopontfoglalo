<?php
namespace App\Services;
use Carbon\CarbonImmutable;
final class BookingHorizon {
 public static function end(array $s):?CarbonImmutable{$v=$s['settings'];return match($v['bookingHorizonMode']??'none'){'date'=>!empty($v['bookingHorizonDate'])?CarbonImmutable::parse($v['bookingHorizonDate'],'Europe/Budapest')->endOfDay():null,'months'=>CarbonImmutable::now('Europe/Budapest')->addMonthsNoOverflow((int)($v['bookingHorizonMonths']??3))->endOfDay(),default=>null};}
 public static function guard(array $s,CarbonImmutable $start):void{$end=self::end($s);if($end&&$start->gt($end))throw new \InvalidArgumentException('Online időpont jelenleg eddig foglalható: '.$end->format('Y.m.d.'));}
}
