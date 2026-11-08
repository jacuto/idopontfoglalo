<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use InvalidArgumentException;
final class RecurringBookings{
 public static function starts(string $start,array $rule):array{
  $origin=Time::parse($start)->setTimezone('Europe/Budapest');$out=[];for($i=0;$i<(int)$rule['count'];$i++){$date=match($rule['frequency']){'daily'=>$origin->addDays($i),'weekly'=>$origin->addWeeks($i),'fortnightly'=>$origin->addWeeks($i*2),'monthly'=>$origin->addMonthsNoOverflow($i)};if($date->format('H:i')!==$origin->format('H:i'))throw new InvalidArgumentException('Az óraátállítás miatt nem létezik a sorozat egyik kezdési ideje. Válassz másik időt.');$out[]=$date->utc()->toISOString();}return $out;
 }
}
