<?php
namespace App\Services;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CalendarImport
{
 public static function event(array $event,string $timezone='Europe/Budapest'):?array{
  if(($event['status']??'')==='cancelled'||($event['transparency']??'')==='transparent'||!empty($event['extendedProperties']['private']['raero_booking']))return null;
  $start=$event['start']['dateTime']??$event['start']['date']??null;$end=$event['end']['dateTime']??$event['end']['date']??null;if(!$start||!$end||empty($event['id']))return null;
  try{$from=Date::parse($start,$event['start']['timeZone']??$timezone);$to=Date::parse($end,$event['end']['timeZone']??$timezone);}catch(\Throwable){return null;}if($to<=$from)return null;
  $text=html_entity_decode(strip_tags($event['description']??''),ENT_QUOTES|ENT_HTML5,'UTF-8');$emails=[];preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',$text,$matches);foreach($matches[0]??[] as $email)if(filter_var($email,FILTER_VALIDATE_EMAIL))$emails[]=strtolower($email);
  foreach($event['attendees']??[] as $a)if(empty($a['self'])&&empty($a['organizer'])&&empty($a['resource'])&&!empty($a['email']))$emails[]=strtolower($a['email']);$emails=array_values(array_unique($emails));
  $phones=[];preg_match_all('/(?<!\d)(?:\+36|0036|06)[\s().\-\/]*\d(?:[\d\s().\-\/]{6,25}\d)(?!\d)/u',$text,$matches);foreach($matches[0]??[] as $raw){try{$phones[]=ContactImport::phone(trim($raw));}catch(InvalidArgumentException){}}$phones=array_values(array_unique($phones));
  return ['eventId'=>$event['id'],'title'=>mb_substr($event['summary']??'Névtelen esemény',0,200),'start'=>$from->utc()->toIso8601String(),'end'=>$to->utc()->toIso8601String(),'allDay'=>!empty($event['start']['date']),'name'=>mb_substr($event['summary']??'',0,150),'phone'=>count($phones)===1?$phones[0]:'','email'=>count($emails)===1?$emails[0]:'','ambiguous'=>count($phones)>1||count($emails)>1];
 }
 public static function apply(array &$s,array $events,array $choices,string $calendar):array{
  $result=['blocks'=>0,'bookings'=>0,'contacts'=>0,'skipped'=>0];$rules=app(BookingRules::class);
  foreach($choices as $choice){$event=$events[$choice['index']]??null;if(!$event)throw new InvalidArgumentException('Hiányzó esemény.');$staff=$choice['staff'];if(!in_array($staff,array_column(Settings::activeStaff($s),'id'),true))throw new InvalidArgumentException('Válassz aktív munkatársat.');$fingerprint=hash('sha256',$calendar.'|'.$event['eventId'].'|'.$staff);if(isset($s['calendarImports'][$fingerprint])){++$result['skipped'];continue;}
   $record=['name'=>trim($choice['name']??''),'phone'=>$choice['phone']??'','email'=>$choice['email']??''];$client=null;
   if(!empty($choice['contact'])){$check=ContactImport::inspect($s['clients'],$record);if(!in_array($check['status'],['new','fill','same'],true))throw new InvalidArgumentException($event['title'].': '.$check['message']);if($check['status']==='new'){$client=$check['record']+['id'=>(string)Str::uuid(),'guestId'=>null];$s['clients'][]=$client;++$result['contacts'];}else{foreach($s['clients'] as &$c)if($c['id']===$check['existing']['id']){foreach($check['changes'] as $k=>$v)$c[$k]=$v;$client=$c;break;}unset($c);if($check['status']==='fill')++$result['contacts'];}}
   if($choice['mode']==='booking'){
    $service=$rules->service($s['services'],$choice['service']??'');Subscriptions::guard($s,true);if(($service['bookingMode']??'individual')!=='individual')throw new InvalidArgumentException('Csoportos szolgáltatás helyett válaszd a foglalt idősávot.');$minutes=(Date::parse($event['end'])->getTimestamp()-Date::parse($event['start'])->getTimestamp())/60;
    if($event['allDay']||(float)$minutes!==(float)$service['duration'])throw new InvalidArgumentException($event['title'].': a szolgáltatás időtartama eltér. Vedd át foglalt idősávként.');
    $record=Contact::apply($record);if(!$record['name'])throw new InvalidArgumentException('Foglaláshoz add meg a vendég nevét.');
    $b=$record+['id'=>(string)Str::uuid(),'token'=>bin2hex(random_bytes(24)),'start'=>$event['start'],'staff'=>$staff,'services'=>[$service['id']],'serviceSnapshot'=>[$service],'public'=>false,'anonymous'=>true,'guestId'=>null,'status'=>'confirmed','createdAt'=>now()->utc()->toIso8601String(),'updatedAt'=>now()->utc()->toIso8601String(),'calendarImport'=>['sourceKey'=>hash('sha256',$calendar.'|'.$event['eventId'])]];
    if($client){$b['clientId']=$client['id'];$b['guestId']=$client['guestId']??null;}
    $rules->validate($b,$s);$b['pricing']=['original'=>(int)$service['price'],'discount'=>0,'payable'=>(int)$service['price']];$s['bookings'][]=$b;++$result['bookings'];
   }else{
    $from=Date::parse($event['start'])->setTimezone('Europe/Budapest');$to=Date::parse($event['end'])->setTimezone('Europe/Budapest');$day=$from->startOfDay();
    while($day<$to){$begin=$from>$day?$from:$day;$finish=$to<$day->addDay()?$to:$day->addDay();$s['availability']['blocks'][]=['id'=>(string)Str::uuid(),'fromDate'=>$day->toDateString(),'toDate'=>$day->toDateString(),'allDay'=>$begin->eq($day)&&$finish->eq($day->addDay()),'fromTime'=>$begin->format('H:i'),'toTime'=>$finish->eq($day->addDay())?'24:00':$finish->format('H:i'),'staffId'=>$staff,'label'=>mb_substr('Átvett: '.$event['title'],0,100)];$day=$day->addDay();}++$result['blocks'];
   }
   $s['calendarImports'][$fingerprint]=['at'=>now()->toIso8601String()];
  }
  if(count($s['availability']['blocks'])>500)throw new InvalidArgumentException('Legfeljebb 500 nem foglalható időszak tárolható. Válassz rövidebb időszakot.');
  if($result['blocks']+$result['bookings']>0)$s['contactImport']['all']['doneAt']=now()->toIso8601String();return $result;
 }
}
