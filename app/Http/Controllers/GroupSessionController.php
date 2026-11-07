<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant,GroupSessions,BookingRules,Notifications,Offers};
use Carbon\CarbonImmutable as Time;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
final class GroupSessionController
{
    public function handle(Request $r){
        abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);
        if($r->isMethod('GET')){$s=app(SalonStore::class)->read();return response()->json(array_map(fn($g)=>$g+['booked'=>GroupSessions::booked($g,$s)],$s['groupSessions']));}
        $v=$r->validate(['id'=>'sometimes|string|max:80','serviceId'=>'required_without:id|string|max:80','staff'=>'required_without:id|string|max:80','start'=>'required_without:id|date','capacity'=>'required|integer|between:1,100','cancelled'=>'sometimes|boolean']);
        return response()->json(app(SalonStore::class)->mutate(function(&$s)use($v){
            if(!empty($v['id'])){
                foreach($s['groupSessions'] as &$g)if($g['id']===$v['id']){
                    if(Time::parse($g['start'])->isPast()||!empty($g['cancelled']))throw new InvalidArgumentException('Elmúlt vagy lemondott csoportos óra nem módosítható.');
                    if($v['capacity']<GroupSessions::booked($g,$s))throw new InvalidArgumentException('A férőhely nem lehet kevesebb a már foglalt létszámnál.');
                    $g['serviceSnapshot']['capacity']=$v['capacity'];$g['cancelled']=$v['cancelled']??false;
                    foreach($s['bookings'] as &$b)if(($b['groupSessionId']??null)===$g['id']&&!in_array($b['status'],['cancelled','rejected'],true)){
                        $b['serviceSnapshot'][0]['capacity']=$v['capacity'];
                        if($g['cancelled']){$next=$b;$next['status']='cancelled';$next['updatedAt']=now()->toISOString();app(Offers::class)->transitionPass($s,$b,$next);$b=$next;app(Notifications::class)->enqueue($b,$s,'cancelled');}
                    }unset($b);return $g;
                }unset($g);abort(404);
            }
            if(count($s['groupSessions'])>=2000)throw new InvalidArgumentException('Legfeljebb 2000 meghirdetett alkalom kezelhető.');
            $service=app(BookingRules::class)->service($s['services'],$v['serviceId']);if(($service['bookingMode']??'individual')!=='group'||!empty($service['archived']))throw new InvalidArgumentException('Aktív csoportos szolgáltatást válassz.');
            $service['capacity']=$v['capacity'];$g=['id'=>(string)Str::uuid(),'serviceId'=>$service['id'],'staff'=>$v['staff'],'start'=>Time::parse($v['start'])->utc()->toISOString(),'serviceSnapshot'=>$service,'cancelled'=>false];
            $s['groupSessions'][]=$g;$b=['name'=>'Csoportos óra','phone'=>'internal','services'=>[$service['id']],'staff'=>$g['staff'],'start'=>$g['start'],'serviceSnapshot'=>[$service],'groupSessionId'=>$g['id']];app(BookingRules::class)->validate($b,$s);return $g;
        }),201);
    }
}
