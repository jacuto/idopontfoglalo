<?php
namespace App\Http\Controllers;
use App\Services\{Tenant,SmsWallet,SmsTopups,StaffAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class SmsWalletController {
 public function handle(Request $r){
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r),403);$provider=app(Tenant::class)->id;abort_unless((int)$r->session()->get('provider_id',1)===$provider,403);
  if($r->isMethod('GET'))return response()->json(SmsWallet::info($provider));
  $v=$r->validate(['amount'=>'required|integer|in:3000,5000,10000','requestId'=>'required|uuid','acceptPrice'=>'accepted']);return response()->json($r->is('api/sms-wallet/transfer')?SmsTopups::transfer($provider,$v['amount'],$v['requestId']):app(SmsTopups::class)->checkout($provider,$v['amount'],$v['requestId']));
 }
 public function history(Request $r,string $kind){
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r)&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);abort_unless(in_array($kind,['ledger','topups'],true),404);
  $q=DB::table($kind==='ledger'?'sms_ledger':'sms_topups')->where('provider_id',app(Tenant::class)->id);
  $q=$kind==='ledger'?$q->select('id','kind','amount','created_at')->orderByDesc('id'):$q->select('id','amount','refunded','status','invoice_number','invoice_issued_on','created_at')->orderByDesc('created_at')->orderByDesc('id');
  return response()->json(\App\Services\HistoryPage::query($q,$r,10));
 }
 public function operator(Request $r){
  abort_unless($r->session()->get('platform_admin')===true,403);
  if($r->isMethod('GET'))return response()->json(['providers'=>DB::table('salon_state')->get()->map(fn($row)=>['id'=>$row->id,'name'=>json_decode($row->data,true)['settings']['name']??('#'.$row->id)]),'price'=>SmsWallet::price(),'wallets'=>DB::table('sms_wallets')->get(),'topups'=>DB::table('sms_topups')->latest('created_at')->limit(300)->get()]);
  $v=$r->validate(['topupId'=>'required|uuid','invoiceNumber'=>'required|string|max:100','issuedOn'=>'required|date_format:Y-m-d']);$q=DB::table('sms_topups')->where('id',$v['topupId'])->whereIn('status',['paid','refunded']);abort_unless($q->exists(),404);$q->update(['invoice_number'=>trim($v['invoiceNumber']),'invoice_issued_on'=>$v['issuedOn'],'updated_at'=>now()]);return response()->json(['ok'=>true]);
 }
 public function credit(Request $r){
  abort_unless($r->session()->get('platform_admin')===true,403);$v=$r->validate(['providerId'=>'required|integer|min:1','amount'=>'required|integer|between:3000,1000000','bankReference'=>'required|string|min:3|max:160','topupId'=>'nullable|uuid','confirmed'=>'accepted']);
  return response()->json(SmsTopups::creditTransfer($v));
 }
 public function resolve(Request $r,int $id){
  abort_unless($r->session()->get('admin')===true&&!StaffAccess::editor($r),403);$provider=app(Tenant::class)->id;$v=$r->validate(['result'=>'required|in:sent,not_sent','confirmed'=>'accepted']);DB::transaction(function()use($provider,$id,$v){SmsWallet::locked($provider);$row=DB::table('deliveries')->where('id',$id)->where('provider_id',$provider)->lockForUpdate()->first();abort_unless($row&&$row->status==='unknown',422,'Csak ellenőrzést igénylő SMS rendezhető.');
  SmsWallet::finish($id,$v['result']==='sent'?'sent':'released',$row->provider_reference);DB::table('deliveries')->where('id',$id)->where('provider_id',$provider)->update(['status'=>$v['result']==='sent'?'sent':'failed','error'=>$v['result']==='sent'?null:'A szolgáltató ellenőrizte: nem ment ki.','updated_at'=>now()]);});return response()->json(['ok'=>true]);
 }
}
