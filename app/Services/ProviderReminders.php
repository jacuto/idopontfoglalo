<?php
namespace App\Services;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\DB;
final class ProviderReminders {
 /** Expand only the requested bounded window; recurring reminders never create infinite rows. */
 public static function occurrences(object $row,string $from,string $to):array {
  $base=Date::parse($row->starts_at,'Europe/Budapest');$first=Date::parse($from,'Europe/Budapest')->startOfDay();$last=Date::parse($to,'Europe/Budapest')->endOfDay();$out=[];$done=DB::table('reminder_completions')->where('reminder_id',$row->id)->whereBetween('occurrence_date',[$from,$to])->pluck('occurrence_date')->all();
  $days=(int)floor($base->startOfDay()->diffInDays($first,false));$months=($first->year-$base->year)*12+$first->month-$base->month;$index=max(0,match($row->frequency){'daily'=>$days-1,'weekly'=>(int)floor($days/7)-1,'monthly'=>$months-1,default=>0});
  for($n=$index;$n<$index+70;$n++){$start=match($row->frequency){'daily'=>$base->addDays($n),'weekly'=>$base->addWeeks($n),'monthly'=>$base->addMonthsNoOverflow($n),default=>$base};if($start->gt($last)||($row->until_date&&$start->toDateString()>$row->until_date)||($row->occurrence_count&&$n>=$row->occurrence_count))break;if($start->gte($first))$out[]=['id'=>$row->id,'staffId'=>$row->staff_id,'title'=>$row->title,'notes'=>$row->notes??'','start'=>$start->toISOString(),'end'=>$start->addMinutes($row->duration)->toISOString(),'date'=>$start->toDateString(),'completed'=>in_array($start->toDateString(),$done,true)];if($row->frequency==='none')break;}
  return $out;
 }
}
