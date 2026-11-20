<?php
namespace App\Services;
use Illuminate\Http\Request;
/** Fixed endpoint-owned sizes; deterministic ordering belongs to the scoped query. */
final class HistoryPage
{
 public static function meta(int $total,int $page,int $size):array{$pages=max(1,(int)ceil($total/$size));$page=min($page,$pages);return ['page'=>$page,'perPage'=>$size,'total'=>$total,'pages'=>$pages,'from'=>$total?($page-1)*$size+1:0,'to'=>min($total,$page*$size)];}
 private static function number(Request $r):int{return (int)($r->validate(['page'=>'sometimes|integer|min:1|max:1000000'])['page']??1);}
 public static function query($query,Request $r,int $size):array{$m=self::meta((int)(clone $query)->count(),self::number($r),$size);return ['items'=>$query->offset(($m['page']-1)*$size)->limit($size)->get(),'pagination'=>$m];}
 public static function items(array $items,Request $r,int $size):array{$m=self::meta(count($items),self::number($r),$size);return ['items'=>array_slice(array_values($items),($m['page']-1)*$size,$size),'pagination'=>$m];}
}
