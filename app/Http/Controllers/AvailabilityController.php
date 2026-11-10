<?php
namespace App\Http\Controllers;
use App\Services\{Availability, SalonStore, Subscriptions, Tenant};
use Illuminate\Http\Request;

final class AvailabilityController
{
    public function handle(Request $r)
    {
        abort_unless($r->session()->get('admin') === true,401);
        abort_unless((int)$r->session()->get('provider_id',1) === app(Tenant::class)->id,403);
        $store=app(SalonStore::class);
        if ($r->isMethod('GET')) return response()->json($store->read()['availability']);
        $data=$r->validate([
            'overrides'=>'present|array|max:500','overrides.*'=>'required|array:date,closed,openingTime,closingTime,staffId',
            'overrides.*.staffId'=>'sometimes|nullable|string|max:80','weeklyStaff'=>'sometimes|array|max:700','weeklyStaff.*'=>'array:staffId,weekday,closed,openingTime,closingTime','weeklyStaff.*.staffId'=>'required|string|max:80','weeklyStaff.*.weekday'=>'required|integer|between:1,7','weeklyStaff.*.closed'=>'required|boolean','weeklyStaff.*.openingTime'=>'required|date_format:H:i','weeklyStaff.*.closingTime'=>'required|date_format:H:i',
            'overrides.*.date'=>'required|date_format:Y-m-d','overrides.*.closed'=>'required|boolean',
            'overrides.*.openingTime'=>'required|date_format:H:i','overrides.*.closingTime'=>'required|date_format:H:i',
            'blocks'=>'present|array|max:500','blocks.*'=>'required|array:id,fromDate,toDate,fromTime,toTime,allDay,staffId,label',
            'blocks.*.id'=>'required|uuid|distinct','blocks.*.fromDate'=>'required|date_format:Y-m-d','blocks.*.toDate'=>'required|date_format:Y-m-d',
            'blocks.*.fromTime'=>'required|date_format:H:i','blocks.*.toTime'=>'required|date_format:H:i','blocks.*.allDay'=>'required|boolean',
            'blocks.*.staffId'=>'present|nullable|string|max:80','blocks.*.label'=>'required|string|max:100',
            'dayLunches'=>'sometimes|array|max:500','dayLunches.*'=>'array:date,enabled,from,to,staffId','dayLunches.*.date'=>'required|date_format:Y-m-d','dayLunches.*.enabled'=>'required|boolean','dayLunches.*.from'=>'required|date_format:H:i','dayLunches.*.to'=>'required|date_format:H:i','dayLunches.*.staffId'=>'present|nullable|string|max:80',
            'lunch'=>'required|array:enabled,from,to,weekdays,staffId','lunch.enabled'=>'required|boolean','lunch.from'=>'required|date_format:H:i','lunch.to'=>'required|date_format:H:i',
            'lunch.weekdays'=>'present|array|max:7','lunch.weekdays.*'=>'integer|between:1,7|distinct','lunch.staffId'=>'present|nullable|string|max:80',
        ], [
            'required'=>'Töltsd ki a kötelező mezőket.', 'present'=>'Hiányzik az időbeosztás egy része. Frissítsd az oldalt.',
            'date_format'=>'A dátumot vagy időt a megfelelő formában add meg.', 'distinct'=>'Ugyanaz a nap vagy azonosító csak egyszer szerepelhet.',
            'uuid'=>'Érvénytelen időszakazonosító. Frissítsd az oldalt.', 'max.array'=>'Túl sok időszakot adtál meg.',
        ]);
        $data['lunch']['weekdays']=array_map('intval',$data['lunch']['weekdays']);
        $result=$store->mutate(function(&$state)use($data){
            Subscriptions::guard($state,true);
            if(!empty($data['weeklyStaff'])||collect($data['overrides'])->contains(fn($d)=>!empty($d['staffId'])))\App\Services\PaidFeatures::require($state);
            foreach(['overrides','weeklyStaff'] as $kind){$data[$kind]=$data[$kind]??($state['availability'][$kind]??[]);$seen=[];foreach($data[$kind] as $day){$key=($day['date']??$day['weekday']).':'.($day['staffId']??'');if(isset($seen[$key]))throw new \InvalidArgumentException('Egy naphoz és munkatárshoz csak egy beosztás adható.');$seen[$key]=true;if(!empty($day['staffId'])&&!in_array($day['staffId'],array_column($state['staff'],'id'),true))throw new \InvalidArgumentException('A munkatárs nem található.');if(!$day['closed']&&$day['openingTime']>=$day['closingTime'])throw new \InvalidArgumentException('A zárás legyen később, mint a nyitás.');}}
            foreach($data['overrides'] as $day) if(!$day['closed'] && $day['openingTime'] >= $day['closingTime']) throw new \InvalidArgumentException('A napi zárás legyen később, mint a nyitás.');
            foreach($data['blocks'] as $block){
                if($block['fromDate'] > $block['toDate'] || (!$block['allDay'] && $block['fromTime'] >= $block['toTime'])) throw new \InvalidArgumentException('A kieső idő vége legyen később, mint a kezdete.');
                if(!empty($block['staffId']) && !in_array($block['staffId'],array_column($state['staff'],'id'),true)) throw new \InvalidArgumentException('A munkatárs nem található a saját vállalkozásodban.');
            }
            if($data['lunch']['enabled'] && ($data['lunch']['from'] >= $data['lunch']['to'] || !$data['lunch']['weekdays'])) throw new \InvalidArgumentException('Adj meg érvényes ebédszünetet és legalább egy napot.');
            if(!empty($data['lunch']['staffId']) && !in_array($data['lunch']['staffId'],array_column($state['staff'],'id'),true)) throw new \InvalidArgumentException('A munkatárs nem található.');
            $data['dayLunches']=$data['dayLunches']??($state['availability']['dayLunches']??[]);$seen=[];
            foreach($data['dayLunches'] as $day){$key=$day['date'].':'.($day['staffId']??'');if(isset($seen[$key]))throw new \InvalidArgumentException('Egy napra és munkatársra csak egy ebédszünet adható meg.');$seen[$key]=true;if($day['enabled']&&$day['from'] >= $day['to'])throw new \InvalidArgumentException('Az ebédszünet vége legyen később, mint a kezdete.');if(!empty($day['staffId'])&&!in_array($day['staffId'],array_column($state['staff'],'id'),true))throw new \InvalidArgumentException('A munkatárs nem található.');}
            $state['availability']=$data;$conflicts=[];
            foreach($state['bookings'] as $b){
                if(in_array($b['status'],['cancelled','rejected'],true) || \Carbon\CarbonImmutable::parse($b['start'])->isPast())continue;
                $duration=array_sum(array_column($b['serviceSnapshot'] ?? array_filter($state['services'],fn($s)=>in_array($s['id'],$b['services'],true)),'duration'));
                try { Availability::validate($b,$state,$duration); } catch(\InvalidArgumentException) { $conflicts[]=['id'=>$b['id'],'start'=>$b['start'],'name'=>$b['name']]; }
            }
            return ['availability'=>$data,'conflicts'=>$conflicts];
        });
        return response()->json($result);
    }
}
