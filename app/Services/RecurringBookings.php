<?php
namespace App\Services;
use Carbon\CarbonImmutable as Time;
use Illuminate\Http\Request;
use Illuminate\Session\{Store,ArraySessionHandler};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
final class RecurringBookings{
 public static function at(string $start,string $frequency,int $i):Time{
  $origin=Time::parse($start)->setTimezone('Europe/Budapest');$date=match($frequency){'daily'=>$origin->addDays($i),'weekly'=>$origin->addWeeks($i),'fortnightly'=>$origin->addWeeks($i*2),'monthly'=>$origin->addMonthsNoOverflow($i)};
  if($date->format('H:i')!==$origin->format('H:i'))throw new InvalidArgumentException('Az óraátállítás miatt nem létezik a sorozat egyik kezdési ideje. Válassz másik időt.');return $date;
 }
 public static function starts(string $start,array $rule,int $offset=0):array{
  $count=(int)($rule['count']??0);$end=empty($rule['endDate'])?null:Time::parse($rule['endDate'],'Europe/Budapest')->endOfDay();$horizon=Time::parse($start)->max(Time::now('Europe/Budapest'))->addYear()->endOfDay();$out=[];
  for($i=$offset;$i<max($offset+370,5200);$i++){
   if($count>0&&$i>=$count)break;$at=self::at($start,$rule['frequency'],$i);if($end&&$at->gt($end))break;
   // Store only the rolling year; the daily job advances this window.
   if($at->gt($horizon)&&$i>0)break;$out[]=$at->utc()->toISOString();if(count($out)>=370)break;
  }return $out;
 }
 public static function run():int{
  $total=0;foreach(DB::table('salon_state')->pluck('id') as $tenant)app(Tenant::class)->run((int)$tenant,function()use(&$total){
   $series=app(SalonStore::class)->read()['recurringSeries']??[];foreach($series as $id=>$rule){if(empty($rule['active'])||(!empty($rule['retryAt'])&&Time::parse($rule['retryAt'])->isFuture()))continue;
    try{$total+=app(SalonStore::class)->mutate(function(&$s)use($id){$rule=$s['recurringSeries'][$id]??null;if(!$rule||empty($rule['active']))return 0;$dates=self::starts($rule['start'],$rule,(int)$rule['nextIndex']);$r=Request::create('/api/bookings','POST');$r->setLaravelSession(new Store('recurring',new ArraySessionHandler(120)));$n=0;$consumed=0;
     foreach($dates as $at){$consumed++;if(Time::parse($at)->isPast())continue;$b=app(\App\Http\Controllers\SalonController::class)->createBooking($s,array_replace($rule['template'],['start'=>$at]),true,$r);$j=count($s['bookings'])-1;$s['bookings'][$j]['seriesId']=$id;$s['bookings'][$j]['seriesIndex']=$rule['nextIndex']+$consumed;$n++;}
     $s['recurringSeries'][$id]['nextIndex']+=$consumed;$s['recurringSeries'][$id]['error']=null;$s['recurringSeries'][$id]['retryAt']=null;return $n;});}
    catch(InvalidArgumentException|\Illuminate\Validation\ValidationException $e){app(SalonStore::class)->mutate(function(&$s)use($id,$e){if(isset($s['recurringSeries'][$id])){$s['recurringSeries'][$id]['error']=$e->getMessage();$s['recurringSeries'][$id]['retryAt']=now()->addDay()->toISOString();}});}
   }
  });return $total;
 }
}
