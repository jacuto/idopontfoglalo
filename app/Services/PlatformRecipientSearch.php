<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
final class PlatformRecipientSearch
{
 public static function search(string $query):array
 {
  $query=trim($query);$numeric=preg_match('/^#?[0-9]+$/',$query);if(mb_strlen($query)<3&&!$numeric)return [];
  $fold=fn($value)=>mb_strtolower(Str::ascii((string)$value));$key=$fold($query);$id=ltrim($query,'#');$items=[];
  // Project only the displayed name rather than fetching entire booking histories.
  $providers=DB::table('salon_state')->select('id','data->settings->name as name')->when($numeric,fn($q)=>$q->where('id',$id))->orderBy('id')->cursor();
  foreach($providers as $p){if($numeric||str_contains($fold($p->name),$key))$items[]=['id'=>'provider:'.$p->id,'name'=>'Szolgáltató: '.$p->name.' (#'.$p->id.')'];if(count($items)>=10)return $items;}
  $guests=DB::table('guest_accounts')->select('id','name','email')->orderBy('id');if($numeric)$guests->where('id',$id);else $guests->where(function($q)use($query){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';$q->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'",[$like])->orWhereRaw("LOWER(email) LIKE LOWER(?) ESCAPE '!'",[$like]);});
  foreach($guests->limit(10-count($items))->get() as $g)$items[]=['id'=>'guest:'.$g->id,'name'=>'Vendég: '.$g->name.' · '.($g->email??'').' (#'.$g->id.')'];return $items;
 }
}
