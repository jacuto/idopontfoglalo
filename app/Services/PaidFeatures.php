<?php
namespace App\Services;
final class PaidFeatures{
 public static function enabled(array $s):bool{
  $sub=$s['subscription']??[];
  if(!isset($sub['plan'])&&!isset($sub['status'])&&empty($sub['trialEndsAt'])&&empty($sub['id']))return empty($sub['calendarBlocked']); // Unconverted legacy accounts retain their existing features.
  return (($sub['plan']??'basic')==='pro'||($sub['status']??'')==='trialing')&&empty($sub['calendarBlocked'])&&!in_array($sub['status']??'', ['canceled','cancelled','blocked','unpaid','incomplete_expired'],true);
 }
 public static function require(array $s):void{abort_unless(self::enabled($s),402,'Ehhez a funkcióhoz Pro előfizetés szükséges. Az aktív próbaidő alatt kipróbálható. Nyisd meg az Előfizetés menüt.');}
}
