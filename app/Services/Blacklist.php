<?php
namespace App\Services;
use InvalidArgumentException;
final class Blacklist{
 public static function blocked(array $s,array $booking):bool{
  foreach($s['clients'] as $c)if(!empty($c['blockedOnline'])){
   if(!empty($booking['guestId'])&&!empty($c['guestId'])&&(int)$booking['guestId']===(int)$c['guestId'])return true;
   if(Clients::sameContact($c,$booking)||Clients::sameContact($c['blockedContacts']??[],$booking))return true;
  }return false;
 }
 public static function guard(array $s,array $booking):void{if(!empty($booking['public'])&&self::blocked($s,$booking))throw new InvalidArgumentException('Az online foglalás nem elérhető. Keresd közvetlenül a szolgáltatót.');}
}
