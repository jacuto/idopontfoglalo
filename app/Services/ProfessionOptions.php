<?php
namespace App\Services;
use InvalidArgumentException;
final class ProfessionOptions
{
 public static function validateCatalog(array $s,array $services,array $settings):void{
  foreach($services as $v){$old=collect($s['services'])->firstWhere('id',$v['id'])??[];
   if(empty($settings['advancedBookingEnabled'])&&(($v['bookingMode']??'individual')!==($old['bookingMode']??'individual')||(int)($v['capacity']??1)!==(int)($old['capacity']??1)))throw new InvalidArgumentException('A párhuzamos és csoportos foglalást előbb kapcsold be a beállításoknál.');
   if(empty($settings['servicePhasesEnabled'])&&($v['phases']??[])!==($old['phases']??[]))throw new InvalidArgumentException('A szolgáltatási szakaszokat előbb kapcsold be a beállításoknál.');
   if(($settings['businessMode']??'team')==='solo'&&($v['staff']??[])!==[$settings['primaryStaffId']]&&(!$old||($v['staff']??[])!==($old['staff']??[])))throw new InvalidArgumentException('Egyéni vállalkozásnál az elsődleges szakember végzi a szolgáltatást.');
  }
 }
}
