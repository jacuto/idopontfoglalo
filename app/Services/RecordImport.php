<?php
namespace App\Services;
use Illuminate\Support\Str;
use InvalidArgumentException;
/** Shared legacy-record validation; previews operate only on an in-memory copy. */
final class RecordImport
{
 public static function find(array $s,string $kind,array $record):?int{
  foreach($s[$kind] as $i=>$old)if(!empty($record['id'])&&$old['id']===$record['id'])return $i;
  $matches=[];
  if($kind==='clients'&&!empty($record['_newContact'])&&!Clients::contactMatches($s['clients'],$record)['email'])return null;
  if($kind==='clients'){foreach($s[$kind] as $i=>$old)if(Clients::visible($old)&&Clients::sameContact($old,$record))$matches[]=$i;}
  elseif($kind==='staff'){foreach($s[$kind] as $i=>$old)if(mb_strtolower(trim($old['name']))===mb_strtolower(trim($record['name']??'')))$matches[]=$i;}
  if($kind==='clients'&&count($matches)===1&&!Clients::contactMatches($s['clients'],$record)['email']&&mb_strtolower(trim($record['name']??''))!==mb_strtolower(trim($s[$kind][$matches[0]]['name'])))throw new InvalidArgumentException('A telefonszám egyezik, de a név eltér. Válassz meglévő vagy új vendéget.');
  if(count($matches)>1)throw new InvalidArgumentException('Több lehetséges egyezés. Add meg a megfelelő meglévő rekord azonosítóját.');
  return $matches[0]??null;
 }
 public static function apply(array &$s,string $kind,array $records,string $policy='fill'):array{
  if(!in_array($kind,['clients','staff','bookings'],true))throw new InvalidArgumentException('Érvénytelen importtípus.');
  $data=['records'=>$records];

                $previousStaff = $s["staff"];
                $added = 0;
                $updated = 0;$skipped=0;ImportPolicy::mode($policy);
                foreach ($data["records"] as $index => $record) {
                    $match=self::find($s,$kind,$record);$old=$match===null?null:$s[$kind][$match];
                    if($old&&$policy==='skip'){++$skipped;continue;}
                    if($old){$changes=ImportPolicy::changes($old,$record,$policy);if($kind==='bookings'&&$changes){$paid=collect($s['payments']??[])->contains(fn($p)=>$p['bookingId']===$old['id']&&($p['status']??'')!=='void');$invoiced=collect($s['invoiceRequests']??[])->contains(fn($i)=>($i['bookingId']??null)===$old['id']&&!in_array($i['status']??'',['failed','void','cancelled'],true));if($paid||$invoiced||!empty($old['groupSessionId'])||!empty($old['passId'])||!empty($old['loyaltyRewardId'])||!empty($old['pricing']['discount']))throw new InvalidArgumentException('Befizetéshez, számlához, bérlethez vagy kedvezményhez kapcsolódó foglalást külön szerkessz.');}$record=array_replace($old,$changes);}
                    $id=$old['id']??($record['id']??'')?: (string)Str::uuid();
                    if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id)) {
                        throw new InvalidArgumentException(
                            "Érvénytelen azonosító a(z) " .
                                ($index + 2) .
                                ". sorban.",
                        );
                    }
                    if ($kind === "bookings") {
                        $record = validator($record, [
                            "name" => "required|string|max:150",
                            "phone" => "nullable|string|max:40",
                            "email" => "nullable|email|max:200",
                            "start" => "required|date",
                            "staff" => "required|string",
                            "services" => "required|array|min:1|max:20",
                            "services.*" => "required|string",
                            "serviceSnapshot" => "sometimes|array|min:1|max:20",
                            "serviceSnapshot.*.id" => "required|string",
                            "serviceSnapshot.*.name" =>
                                "required|string|max:150",
                            "serviceSnapshot.*.price" =>
                                "required|numeric|min:0|max:100000000",
                            "serviceSnapshot.*.duration" =>
                                "required|integer|between:5,1440",
                            "serviceSnapshot.*.staff" => "sometimes|array",
                            "serviceSnapshot.*.phases" =>
                                "sometimes|array|size:3",
                            "serviceSnapshot.*.phases.*" =>
                                "required|integer|min:0",
                            "status" =>
                                "sometimes|in:pending,confirmed,cancelled,rejected,no_show",
                            "groupSessionId"=>"sometimes|nullable|string|max:80", "anonymous"=>"sometimes|boolean", "noShowFee"=>"sometimes|integer|min:0|max:100000000",
                            "serviceSnapshot.*.bookingMode"=>"sometimes|in:individual,parallel,group", "serviceSnapshot.*.capacity"=>"sometimes|integer|between:1,100",
                        ])->validate();
                        $old = collect($s["bookings"])->firstWhere("id", $id);
                        if(!empty($old['groupSessionId'])&&empty($record['groupSessionId']))$record['groupSessionId']=$old['groupSessionId'];
                        if(!empty($record['groupSessionId'])){ $g=collect($s['groupSessions'])->firstWhere('id',$record['groupSessionId']);if(!$g||$g['staff']!==$record['staff']||[$g['serviceId']]!==$record['services']||strtotime($g['start'])!==strtotime($record['start']))throw new InvalidArgumentException('A csoportos foglalás saját meghirdetett alkalma hiányzik vagy eltér az importban.');$record['serviceSnapshot']=[$g['serviceSnapshot']];}
                        if(($record['status']??'')==='no_show')$record['attendance']='no_show';
                        $record = array_merge($record, [
                            "id" => $id,
                            "phone" => $record["phone"] ?? "",
                            "email" => $record["email"] ?? "",
                            "status" => $record["status"] ?? "confirmed",
                            "token" =>
                                $old["token"] ?? bin2hex(random_bytes(24)),
                            "createdAt" =>
                                $old["createdAt"] ?? now()->toISOString(),
                            "updatedAt" => now()->toISOString(),
                        ]);
                        if (
                            !in_array(
                                $record["status"],
                                ["cancelled", "rejected"],
                                true,
                            )
                        ) {
                            app(BookingRules::class)->validate($record, $s, $id, true);
                        } else {
                            foreach ($record["services"] as $v) {
                                app(BookingRules::class)->service($s["services"], $v);
                            }
                        }
                        $record["serviceSnapshot"] =
                            $record["serviceSnapshot"] ??
                            array_map(
                                fn($v) => app(BookingRules::class)->service(
                                    $s["services"],
                                    $v,
                                ),
                                $record["services"],
                            );
                        if (
                            array_column($record["serviceSnapshot"], "id") !==
                            $record["services"]
                        ) {
                            throw new InvalidArgumentException(
                                "A szolgáltatáspillanatkép azonosítói nem egyeznek.",
                            );
                        }
                        foreach ($record["serviceSnapshot"] as $snap) {
                            if (
                                isset($snap["phases"]) &&
                                array_sum($snap["phases"]) !== $snap["duration"]
                            ) {
                                throw new InvalidArgumentException(
                                    "Érvénytelen szakaszidők az importban.",
                                );
                            }
                        }
                    } else {
                        $record = validator($record, [
                            "name" => "required|string|max:150",
                            "phone" => "nullable|string|max:40",
                            "email" => "nullable|email|max:200",
                            "role" =>
                                ($kind === "staff" ? "required" : "sometimes") .
                                "|string|max:80",
                        ])->validate();
                        $record = array_merge($record, [
                            "id" => $id,
                            "phone" => $record["phone"] ?? "",
                            "email" => $record["email"] ?? "",
                        ]);
                    }
                    $record=\App\Services\Contact::apply($record);
                    if ($match === null) {
                        if($kind==='clients')Clients::preventDuplicate($s[$kind],$record);
                        $s[$kind][] = $record;
                        $added++;
                    } else {
                        $record["id"] = $s[$kind][$match]["id"];
                        if($kind==='clients'){\App\Services\Clients::editable($s[$kind][$match],$record);if(($s[$kind][$match]['email']??'')!==($record['email']??'')||($s[$kind][$match]['phone']??'')!==($record['phone']??''))\App\Services\Clients::preventDuplicate($s[$kind],$record,$s[$kind][$match]);}
                        $s[$kind][$match] = array_replace(
                            $s[$kind][$match],
                            $record,
                        );
                        $updated++;
                    }
                }
                if ($kind === "staff") Settings::checkCalendars(array_replace($s,["staff"=>$previousStaff]),$s["staff"]);
                return ["added"=>$added,"updated"=>$updated,"skipped"=>$skipped];

 }
 public static function preview(array $s,string $kind,array $records,string $policy):array{
  $items=[];$seen=[];
  foreach($records as $index=>$record){try{$match=self::find($s,$kind,$record);$old=$match===null?null:$s[$kind][$match];$copy=$s;$r=self::apply($copy,$kind,[$record],$policy);$status=$old?($r['updated']?'update':'same'):'new';$key=$old['id']??($record['id']??'');if($key!==''&&isset($seen[$key])){$status='duplicate';}else{if($key!=='')$seen[$key]=true;$s=$copy;}$items[]=['index'=>$index,'status'=>$status,'name'=>$record['name']??'','message'=>$status==='duplicate'?'A fájlban már szerepel ugyanez a rekord.':($old?'Meglévő rekord':'Új rekord'),'existingId'=>$old['id']??null,'fingerprint'=>$old?hash('sha256',json_encode($old)):null];}
   catch(\Illuminate\Validation\ValidationException|InvalidArgumentException $e){$candidates=$kind==='clients'?array_values(array_reduce(array_merge(Clients::contactMatches($s['clients'],$record)['email'],Clients::contactMatches($s['clients'],$record)['phone']),function($out,$c){$out[$c['id']]=$c;return $out;},[])):[];$items[]=['index'=>$index,'status'=>'conflict','name'=>$record['name']??'','message'=>$e->getMessage(),'candidates'=>$candidates,'canCreate'=>$kind==='clients'&&!Clients::contactMatches($s['clients'],$record)['email']];}
  }return $items;
 }
}
