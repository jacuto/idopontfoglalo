<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class Availability
{
    public static function defaults(): array { return ['overrides'=>[], 'blocks'=>[], 'dayLunches'=>[], 'lunch'=>['enabled'=>false,'from'=>'12:00','to'=>'12:30','weekdays'=>[1,2,3,4,5],'staffId'=>null]]; }
    public static function hours(array $state, string $date): array
    {
        foreach ($state['availability']['overrides'] ?? [] as $day) if ($day['date'] === $date) return [$day['closed'] ? null : Settings::minutes($day['openingTime']), $day['closed'] ? null : Settings::minutes($day['closingTime'])];
        return [Settings::minutes($state['settings']['openingTime']),Settings::minutes($state['settings']['closingTime'])];
    }
    public static function blocked(array $state, string $date, string $staff): array
    {
        $ranges=[];
        foreach ($state['availability']['blocks'] ?? [] as $block) {
            if ($date < $block['fromDate'] || $date > $block['toDate'] || (!empty($block['staffId']) && $block['staffId'] !== $staff)) continue;
            $ranges[]=$block['allDay'] ? [0,1440] : [Settings::minutes($block['fromTime']),Settings::minutes($block['toTime'])];
        }
        $lunch=$state['availability']['lunch'] ?? [];$daily=null;
        foreach($state['availability']['dayLunches']??[] as $item)if($item['date']===$date && (empty($item['staffId'])||$item['staffId']===$staff)){if(!$daily||!empty($item['staffId']))$daily=$item;}
        if($daily!==null)$lunch=$daily;
        if (!empty($lunch['enabled']) && (empty($lunch['staffId']) || $lunch['staffId'] === $staff) && ($daily!==null || in_array(CarbonImmutable::parse($date)->dayOfWeekIso,$lunch['weekdays'],true))) $ranges[]=[Settings::minutes($lunch['from']),Settings::minutes($lunch['to'])];
        return $ranges;
    }
    public static function validate(array $booking, array $state, int $duration): void
    {
        $start=CarbonImmutable::parse($booking['start'])->setTimezone('Europe/Budapest');
        $date=$start->format('Y-m-d');$minute=$start->hour*60+$start->minute;
        [$open,$close]=self::hours($state,$date);
        if (!empty($booking['public']) && empty($booking['groupSessionId']) && $open !== null) {
            $step=(int)($state['settings']['slotStep']??10);$first=$step===60?(int)(ceil($open/60)*60):$open;
            if (($minute-$first)%$step!==0 || $start->second!==0) throw new InvalidArgumentException('Válassz a felajánlott kezdési időpontok közül.');
        }
        if ($open === null) throw new InvalidArgumentException('Ezen a napon zárva tartunk. Válassz másik napot.');
        if ($minute < $open || $minute+$duration > $close) throw new InvalidArgumentException('Az időpont nem fér az adott nap nyitvatartásába. Válassz másik időpontot.');
        foreach(self::blocked($state,$date,$booking['staff']) as [$from,$to]) if($minute < $to && $minute+$duration > $from) throw new InvalidArgumentException('Ez az időszak szünet, szabadság vagy más elfoglaltság miatt nem foglalható. Válassz másik időpontot.');
    }
}
