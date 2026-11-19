<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
final class PublicHours
{
 public static function week(array $s):array{
  if(($s['profile']['showOpeningHours']??true)===false)return [];$start=Time::now('Europe/Budapest')->startOfWeek();$rows=[];
  foreach(['Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat','Vasárnap'] as $i=>$label){$date=$start->addDays($i)->toDateString();$ranges=[];foreach(Settings::activeStaff($s) as $p){[$from,$to]=Availability::hours($s,$date,$p['id']);if($from!==null)$ranges[]=[$from,$to];}usort($ranges,fn($a,$b)=>$a[0]<=>$b[0]);$merged=[];foreach($ranges as [$from,$to]){$n=count($merged)-1;if($n>=0&&$from<=$merged[$n][1])$merged[$n][1]=max($to,$merged[$n][1]);else $merged[]=[$from,$to];}$fmt=fn($m)=>sprintf('%02d:%02d',intdiv($m,60),$m%60);$rows[]=['date'=>$date,'day'=>$label,'hours'=>$merged?implode(', ',array_map(fn($r)=>$fmt($r[0]).'–'.$fmt($r[1]),$merged)):'Zárva'];}
  return $rows;
 }
}
