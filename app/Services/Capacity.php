<?php
namespace App\Services;
use InvalidArgumentException;
final class Capacity
{
    public static function snapshot(array $b,array $s):array {
        return $b['serviceSnapshot']??array_values(array_filter($s['services'],fn($v)=>in_array($v['id'],$b['services'],true)));
    }
    public static function validate(array $booking,array $state,array $ranges,?string $ignore):void {
        $services=self::snapshot($booking,$state);$service=count($services)===1?$services[0]:[];
        foreach($state['groupSessions']??[] as $session){
            if(!empty($session['cancelled'])||$session['staff']!==$booking['staff']||($booking['groupSessionId']??null)===$session['id'])continue;
            $from=strtotime($session['start']);$to=$from+$session['serviceSnapshot']['duration']*60;
            foreach($ranges as [$a,$b])if($a<$to&&$b>$from)throw new InvalidArgumentException('Ebben az időben meghirdetett csoportos óra van.');
        }
        foreach($ranges as [$from,$to]){
            $events=[];$limit=max(1,(int)($service['capacity']??1));
            if(empty($booking['groupSessionId'])){$current=collect($state['services'])->firstWhere('id',$service['id']??'');$limit=min($limit,($current['bookingMode']??'individual')==='parallel'?max(1,(int)($current['capacity']??1)):1);}
            foreach($state['bookings'] as $old){
                if(($old['id']??null)===$ignore||$old['staff']!==$booking['staff']||in_array($old['status'],['cancelled','rejected'],true))continue;
                $other=self::snapshot($old,$state);$same=count($services)===1&&count($other)===1&&$other[0]['id']===$service['id'];
                $compatible=$same&&(($service['bookingMode']??'individual')==='parallel'&&($other[0]['bookingMode']??'individual')==='parallel'||!empty($booking['groupSessionId'])&&($booking['groupSessionId']??null)===($old['groupSessionId']??null));
                foreach(app(BookingRules::class)->occupied($old,$state) as [$a,$b]){
                    $a=max($from,$a);$b=min($to,$b);if($a>=$b)continue;
                    if(!$compatible)throw new InvalidArgumentException('Ez az időpont már foglalt. Válassz másikat.');
                    $limit=min($limit,max(1,(int)($other[0]['capacity']??1)));$events[$a]=($events[$a]??0)+1;$events[$b]=($events[$b]??0)-1;
                }
            }
            ksort($events,SORT_NUMERIC);$count=1;foreach($events as $change){$count+=$change;if($count>$limit)throw new InvalidArgumentException('Erre az időpontra elfogytak a szabad férőhelyek.');}
        }
    }
}
