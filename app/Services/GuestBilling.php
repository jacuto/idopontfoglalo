<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Crypt};
final class GuestBilling
{
 public static function get(int $id):array{$row=DB::table('guest_accounts')->find($id);return !empty($row->billing_data)?json_decode(Crypt::decryptString($row->billing_data),true,32,JSON_THROW_ON_ERROR):[];}
}
