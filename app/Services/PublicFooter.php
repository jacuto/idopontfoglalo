<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
final class PublicFooter
{
 public const FIELDS=['name','email','phone','address','taxNumber','website','facebook','contactUrl'];
 public static function get():array{
  $row=DB::table('platform_settings')->find(2);$data=$row?json_decode($row->data,true,32,JSON_THROW_ON_ERROR):[];
  return array_replace(['name'=>'Digit Marketing','website'=>'https://digitmarketing.hu','contactUrl'=>'https://digitmarketing.hu/elerhetosegek'],array_intersect_key($data,array_flip(self::FIELDS)));
 }
}
