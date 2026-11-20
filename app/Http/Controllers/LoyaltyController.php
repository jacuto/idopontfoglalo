<?php
namespace App\Http\Controllers;
use App\Services\{SalonStore,Tenant,StaffAccess,Loyalty,Clients};
use Illuminate\Http\Request;
final class LoyaltyController
{
 public function settings(Request $r){
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r)&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);
  if($r->isMethod('GET')){$s=app(SalonStore::class)->read();Loyalty::init($s);return response()->json(['enabled'=>!empty($s['settings']['loyaltyEnabled']),'rule'=>$s['loyalty']['rule'],'rewards'=>$s['loyalty']['rewards']]);}
  $v=$r->validate(['enabled'=>'required|boolean','rule'=>'required|array:mode,pointsPerVisit,spendUnit','rule.mode'=>'required|in:visits,spend','rule.pointsPerVisit'=>'required|integer|min:1|max:1000000','rule.spendUnit'=>'required|integer|min:1|max:100000000','rewards'=>'present|array|max:20','rewards.*'=>'array:id,name,points,amount,active,services','rewards.*.id'=>'required|string|max:80|regex:/^[a-zA-Z0-9_-]+$/|distinct','rewards.*.name'=>'required|string|max:150','rewards.*.points'=>'required|integer|min:1|max:1000000','rewards.*.amount'=>'required|integer|min:1|max:100000000','rewards.*.active'=>'required|boolean','rewards.*.services'=>'present|array|max:100','rewards.*.services.*'=>'string|max:80']);
  app(SalonStore::class)->mutate(function(&$s)use($v){foreach($v['rewards'] as $reward)foreach($reward['services'] as $id)abort_unless(collect($s['services'])->contains('id',$id),422,'Ismeretlen szolgáltatás a jutalomban.');Loyalty::init($s);$s['settings']['loyaltyEnabled']=$v['enabled'];$s['loyalty']['rule']=$v['rule'];$s['loyalty']['rewards']=$v['rewards'];});return response()->json(['ok'=>true]);
 }
 public function client(Request $r,string $id){
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r)&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);$s=app(SalonStore::class)->read();abort_unless(collect($s['clients'])->contains(fn($c)=>$c['id']===$id&&Clients::visible($c)),404);return response()->json(self::view($s,$id,$r));
 }
 public function guest(Request $r){
  $guest=(int)$r->session()->get('guest_id',0);abort_unless($guest,401);$s=app(SalonStore::class)->read();if(empty($s['settings']['loyaltyEnabled']))return response()->json(['enabled'=>false]);$account=\Illuminate\Support\Facades\DB::table('guest_accounts')->find($guest);abort_unless($account&&!empty($account->email_verified),403);$client=collect($s['clients'])->first(fn($c)=>(int)($c['guestId']??0)===$guest&&Clients::visible($c));return response()->json($client?self::view($s,$client['id'],$r):['enabled'=>true,'balance'=>0,'available'=>0,'rewards'=>[],'history'=>[]]);
 }
 private static function view(array $s,string $client,Request $r):array{
  $data=Loyalty::view($s,$client,$r->has('page')?null:50);
  if($r->has('page')){$page=\App\Services\HistoryPage::items(array_reverse($data['history']),$r,10);$data['history']=$page['items'];$data['historyPagination']=$page['pagination'];}
  return $data;
 }

}
