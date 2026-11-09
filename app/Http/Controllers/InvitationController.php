<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ClientInvitations;
final class InvitationController
{
 public function open(Request $r,string $token){$row=DB::table('guest_invitations')->where('token_hash',hash('sha256',$token))->first();if(!$row||now()->gte($row->expires_at))return redirect('/fiokom?inviteError=expired');$r->session()->put('pending_guest_invitation',$token);return redirect('/fiokom?provider='.$row->provider_id);}
 public function claim(Request $r){$guest=(int)$r->session()->get('guest_id',0);abort_unless($guest&&\App\Services\ProviderGuest::allowed($r)&&DB::table('guest_accounts')->where('id',$guest)->exists(),401);$token=$r->session()->get('pending_guest_invitation');if(!$token)return response()->json(['claimed'=>false]);try{$result=ClientInvitations::claim($token,$guest);$r->session()->forget('pending_guest_invitation');return response()->json($result);}catch(\Throwable $e){$r->session()->forget('pending_guest_invitation');throw $e;}}
}
