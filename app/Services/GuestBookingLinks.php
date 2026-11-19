<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
/** Matching is allowed only for the verified login address, never a typed contact address. */
final class GuestBookingLinks
{
 public static function link(array &$s,object $account):bool{
  $email=mb_strtolower(trim((string)($account->email??'')));if(empty($account->email_verified)||!$email)return false;$changed=false;
  foreach($s['bookings'] as &$b){if(!empty($b['guestId'])||mb_strtolower(trim((string)($b['email']??'')))!==$email)continue;
   $index=null;foreach($s['clients'] as $i=>$c)if($c['id']===($b['clientId']??null)){$index=$i;break;}
   if($index!==null){$c=$s['clients'][$index];if(!empty($c['guestId'])&&(int)$c['guestId']!==(int)$account->id)continue;if(mb_strtolower(trim((string)($c['email']??'')))!==$email)continue;$s['clients'][$index]['guestId']=(int)$account->id;}
   $b['guestId']=(int)$account->id;$changed=true;
  }unset($b);return $changed;
 }
 public static function sync(object $account):void{
  if(empty($account->email_verified)||empty($account->email))return;
  foreach(DB::table('salon_state')->get(['id','data']) as $row){$s=json_decode($row->data,true,512,JSON_THROW_ON_ERROR);if(!self::link($s,$account))continue;app(Tenant::class)->run((int)$row->id,fn()=>app(SalonStore::class)->mutate(function(&$s)use($account){self::link($s,$account);}));}
 }
}
