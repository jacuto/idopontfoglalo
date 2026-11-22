<?php
namespace App\Services;
final class ImportPolicy
{
 public const MODES=['fill','overwrite','skip'];
 public static function mode(string $mode):string{if(!in_array($mode,self::MODES,true))throw new \InvalidArgumentException('Érvénytelen importálási szabály.');return $mode;}
 public static function changes(array $old,array $incoming,string $mode):array{
  self::mode($mode);if($mode==='skip')return [];$changes=[];
  foreach($incoming as $key=>$value){if(in_array($key,['id','guestId','token','createdAt','updatedAt','version'],true)||$value===null||$value===''||$value===[])continue;
   $empty=!array_key_exists($key,$old)||$old[$key]===null||$old[$key]===''||$old[$key]===[];
   if(($mode==='overwrite'||$empty)&&($old[$key]??null)!==$value)$changes[$key]=$value;
  }return $changes;
 }
 public static function summary(array $items):array{$counts=['total'=>count($items),'new'=>0,'matched'=>0,'conflict'=>0,'invalid'=>0,'duplicate'=>0];foreach($items as $i){if($i['status']==='new')$counts['new']++;elseif(in_array($i['status'],['invalid','duplicate','conflict'],true))$counts[$i['status']]++;else $counts['matched']++;}return $counts;}
}
