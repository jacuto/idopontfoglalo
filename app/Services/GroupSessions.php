<?php
namespace App\Services;
use InvalidArgumentException;
final class GroupSessions
{
    public static function prepare(array &$b,array $s):void {
        $services=array_values(array_filter($s['services'],fn($v)=>in_array($v['id'],$b['services'],true)));
        if(!collect($services)->contains(fn($v)=>($v['bookingMode']??'individual')==='group'))return;
        if(count($b['services'])!==1)throw new InvalidArgumentException('Csoportos órát külön foglalj, más szolgáltatás hozzáadása nélkül.');
        $session=collect($s['groupSessions']??[])->first(fn($g)=>!GroupMinimum::unavailable($g,$s)&&$g['serviceId']===$b['services'][0]&&$g['staff']===$b['staff']&&strtotime($g['start'])===strtotime($b['start']));
        if(!$session)throw new InvalidArgumentException('Válassz a meghirdetett csoportos órák közül.');
        $b['groupSessionId']=$session['id'];$b['serviceSnapshot']=[$session['serviceSnapshot']];
        if(!empty($b['guestId'])&&collect($s['bookings'])->contains(fn($v)=>($v['groupSessionId']??null)===$session['id']&&($v['guestId']??null)===$b['guestId']&&!in_array($v['status'],['cancelled','rejected'],true)&&($v['id']??null)!==($b['id']??null)))throw new InvalidArgumentException('Erre a csoportos órára már van foglalásod.');
    }
    public static function checkCatalog(array $s,array $services,array $staff):void {
        foreach($s['groupSessions']??[] as $g){if(!empty($g['cancelled'])||strtotime($g['start'])<=time())continue;
            $service=collect($services)->firstWhere('id',$g['serviceId']);$person=collect($staff)->firstWhere('id',$g['staff']);
            if(!$service||!empty($service['archived'])||($service['bookingMode']??'individual')!=='group'||!$person||!empty($person['archived'])||!in_array($g['staff'],array_unique(array_merge($service['staff'],Settings::serviceStaff($s,$service))),true))throw new InvalidArgumentException('Előbb mondd le a kapcsolódó meghirdetett csoportos órákat.');
            if((int)$service['price']!==(int)$g['serviceSnapshot']['price']||(int)$service['duration']!==(int)$g['serviceSnapshot']['duration'])throw new InvalidArgumentException('Meghirdetett csoportos óránál az ár és időtartam rögzített. Módosításhoz előbb mondd le az alkalmakat.');
        }
    }
    public static function booked(array $session,array $s):int{return collect($s['bookings'])->filter(fn($b)=>($b['groupSessionId']??null)===$session['id']&&!in_array($b['status'],['cancelled','rejected'],true))->count();}
}
