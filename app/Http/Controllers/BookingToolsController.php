<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant,Offers,Notifications};
use Illuminate\Http\Request;
final class BookingToolsController{
 public function blacklist(Request $r,string $id){$this->admin($r);$v=$r->validate(['blocked'=>'required|boolean','reason'=>'nullable|string|max:1000']);$result=app(SalonStore::class)->mutate(function(&$s)use($v,$id){foreach($s['clients'] as &$c)if($c['id']===$id){$c['blockedOnline']=$v['blocked'];$c['blockedReason']=$v['blocked']?($v['reason']??''):'';$c['blockedAt']=$v['blocked']?now()->toISOString():null;if($v['blocked'])$c['blockedContacts']=['email'=>$c['email']??'','phone'=>$c['phone']??''];else unset($c['blockedContacts']);return $c;}abort(404);});return response()->json($result);}
 public function cancelSeries(Request $r,string $id){$this->admin($r);$count=app(SalonStore::class)->mutate(function(&$s)use($id){$found=false;$count=0;foreach($s['bookings'] as &$b)if(($b['seriesId']??null)===$id){$found=true;if(in_array($b['status'],['pending','confirmed'],true)&&strtotime($b['start'])>=time()&&in_array($b['attendance']??'unmarked',['unmarked',''],true)){$next=array_replace($b,['status'=>'cancelled','updatedAt'=>now()->toISOString()]);app(Offers::class)->transitionPass($s,$b,$next);$b=$next;app(Notifications::class)->enqueue($b,$s,'cancelled');$count++;}}unset($b);abort_unless($found,404);return $count;});return response()->json(['cancelled'=>$count]);}
 private function admin(Request $r):void{abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);}
}
