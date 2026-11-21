<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
final class MarketingCampaigns
{
 public static function recipients(int $provider):array{
  if($provider===0)return DB::table('newsletter_consents')->where('audience','provider')->whereNotNull('consented_at')->whereNull('unsubscribed_at')->orderBy('id')->get()->unique(fn($r)=>mb_strtolower($r->email))->map(fn($r)=>['id'=>$r->id,'email'=>$r->email,'name'=>'','active'=>true])->values()->all();
  return DB::table('marketing_consents')->where('provider_id',$provider)->whereNotNull('consented_at')->whereNull('unsubscribed_at')->orderBy('id')->get()->map(fn($r)=>['id'=>$r->id,'email'=>$r->email,'name'=>$r->name,'active'=>true])->all();
 }
 public static function unsubscribe(int $provider,string $email):void{
  $email=mb_strtolower(trim($email));if($provider===0)DB::table('newsletter_consents')->where('audience','provider')->whereRaw('LOWER(email) = ?',[$email])->update(['unsubscribed_at'=>now(),'updated_at'=>now()]);
  else DB::table('marketing_consents')->where('provider_id',$provider)->whereRaw('LOWER(email) = ?',[$email])->update(['unsubscribed_at'=>now(),'updated_at'=>now()]);
 }
 public static function run(int $limit=5,?int $onlyProvider=null,?string $onlyCampaign=null):array{
  if(($onlyProvider===null)!==($onlyCampaign===null))throw new \InvalidArgumentException('A célzott futtatáshoz szolgáltató és kampány szükséges.');
  DB::table('marketing_campaigns')->when($onlyCampaign!==null,fn($q)=>$q->where('id',$onlyCampaign)->where('provider_id',$onlyProvider))->where('status','processing')->where('updated_at','<',now()->subMinutes(10))->update(['status'=>'unknown','error'=>'A feldolgozás megszakadt. Ellenőrizd a külső hírlevélküldő fiókot; automatikus újraküldés nincs.','updated_at'=>now()]);$counts=['prepared'=>0,'submitted'=>0,'unknown'=>0,'blocked'=>0];
  for($i=0;$i<$limit;$i++){
   $job=DB::transaction(function()use($onlyProvider,$onlyCampaign){$r=DB::table('marketing_campaigns')->when($onlyCampaign!==null,fn($q)=>$q->where('id',$onlyCampaign)->where('provider_id',$onlyProvider))->whereNull('deleted_at')->whereIn('status',['preparing','scheduled'])->where(fn($q)=>$q->whereNull('scheduled_at')->orWhere('scheduled_at','<=',now()))->orderBy('updated_at')->lockForUpdate()->first();if($r)DB::table('marketing_campaigns')->where('id',$r->id)->update(['status'=>'processing','updated_at'=>now()]);return $r;});if(!$job)break;
   try{
    $provider=(int)$job->provider_id;if($provider>0){$allowed=app(Tenant::class)->run($provider,fn()=>MarketingConnections::allowed(app(SalonStore::class)->read()));if(!$allowed){DB::table('marketing_campaigns')->where('id',$job->id)->update(['status'=>'blocked','error'=>'A hírlevélküldéshez aktív Pro jogosultság szükséges.','updated_at'=>now()]);$counts['blocked']++;continue;}}
    $keys=MarketingConnections::get($provider);$data=json_decode($job->recipients??'{}',true);if(empty($keys['apiKey'])||($keys['engine']??'')!==$job->engine||($data['revision']??'')!==($keys['revision']??''))throw new MarketingException('A hírlevélkapcsolat megváltozott. Ellenőrizd a fiókot és készíts új piszkozatot.');$gateway=new MarketingGateway($keys);
    $current=collect(self::recipients($provider))->keyBy('email');
    if($job->status==='preparing'){
     $list=$data['list']??$gateway->audience($provider);$data['list']=$list;$items=$data['items']??[];$cursor=$data['cursor']??0;
     // Keep each cron slice bounded; contact upserts are idempotent, campaign sends are not retried.
     for($n=0;$cursor<count($items)&&$n<10;$n++,$cursor++){
      $r=$items[$cursor];if(!$current->has($r['email'])||!$gateway->contact($list,$r)){$items[$cursor]['active']=false;if($current->has($r['email']))self::unsubscribe($provider,$r['email']);}
     }
     $data['items']=$items;$data['cursor']=$cursor;DB::table('marketing_campaigns')->where('id',$job->id)->update(['recipients'=>json_encode($data),'updated_at'=>now()]);
     if($cursor<count($items)){DB::table('marketing_campaigns')->where('id',$job->id)->update(['status'=>'preparing','scheduled_at'=>now()->addSeconds(20)]);continue;}
     $selected=array_values(array_filter($items,fn($r)=>!empty($r['active'])&&$current->has($r['email'])));if(!$selected){DB::table('marketing_campaigns')->where('id',$job->id)->update(['status'=>'cancelled','error'=>'Nincs aktív, hozzájárulással rendelkező címzett.']);continue;}
     $id=$gateway->create($job,$list,$selected);$data['items']=$selected;DB::table('marketing_campaigns')->where('id',$job->id)->update(['status'=>'ready','remote_id'=>$id,'remote_list_id'=>$list,'recipient_count'=>count($selected),'recipients'=>json_encode($data),'error'=>null,'updated_at'=>now()]);$counts['prepared']++;
    }else{
     $items=$data['items']??[];foreach($items as $r)if(!$current->has($r['email']))throw new MarketingException('A címzettlista az előkészítés óta megváltozott. Nem indítottuk el a küldést; készíts új piszkozatot.');
     // A native campaign provides the provider's unsubscribe handling and account quota checks.
     if($job->engine==='brevo'){$account=$gateway->call('GET','account');foreach($account['plan']??[] as $plan)if(($plan['type']??'')==='free'&&isset($plan['credits'])&&(int)$plan['credits']<count($items))throw new MarketingException('A Brevo napi ingyenes keretébe nem fér bele a teljes kampány. Csökkentsd a címzettlistát vagy válassz nagyobb küldési keretet.');}
     $gateway->send($job->remote_id);DB::table('marketing_campaigns')->where('id',$job->id)->update(['status'=>'submitted','submitted_at'=>now(),'error'=>null,'updated_at'=>now()]);$counts['submitted']++;
    }
   }catch(\Throwable $e){SystemIssues::capture($e,'newsletter',app(Tenant::class)->id);$message=($job->status==='scheduled'&&!empty($job->remote_id))?('A küldés eredménye még nem igazolható. A külső állapotot utólag is ellenőrizzük; ne küldd el újra. '.($e instanceof MarketingException?$e->getMessage():'')):($e instanceof MarketingException?$e->getMessage():'A hírlevélküldő válasza nem igazolható. Ellenőrizd a külső fiókot.');DB::table('marketing_campaigns')->where('id',$job->id)->update(['status'=>'unknown','error'=>mb_substr($message,0,500),'updated_at'=>now()]);$counts['unknown']++;}
  }
  return $counts;
 }
 /** Read-only reconciliation: never repeat a campaign send, even after a timeout. */
 public static function check(object $campaign):array{
  $keys=MarketingConnections::get((int)$campaign->provider_id);
  if(empty($keys['apiKey'])||($keys['engine']??'')!==$campaign->engine)throw new MarketingException('Az eredeti hírlevélkapcsolat nem elérhető. Ellenőrizd a külső fiókot.');
  $snapshot=json_decode($campaign->recipients??'{}',true);if(!empty($snapshot['revision'])&&$snapshot['revision']!==($keys['revision']??''))throw new MarketingException('A kapcsolat megváltozott; az eredeti küldőfiókban ellenőrizd a kampányt.');
  $report=(new MarketingGateway($keys))->report($campaign->remote_id);$remote=mb_substr((string)($report['status']??''),0,40);
  $positive=in_array(mb_strtolower($remote),['sending','sent','inprogress','queued','scheduled','schedule'],true);
  $update=['remote_status'=>$remote,'remote_checked_at'=>now(),'remote_check_error'=>null];
  if($positive){$update['status']=mb_strtolower($remote)==='sent'?'sent':'submitted';$update['error']=null;$update['submitted_at']=$campaign->submitted_at??now();}
  // Do not overwrite a new edit, cancellation, or another campaign generation after the HTTP request.
  DB::table('marketing_campaigns')->where('id',$campaign->id)->where('provider_id',$campaign->provider_id)->where('remote_id',$campaign->remote_id)->whereNull('deleted_at')->whereIn('status',['ready','unknown','submitted','blocked','sent'])->when(mb_strtolower($remote)!=='sent',fn($q)=>$q->where('status','!=','sent'))->update($update);
  return ['status'=>$remote,'emailsSent'=>$report['emails_sent']??$report['statistics']['globalStats']['sent']??null];
 }
 public static function reconcile(int $limit=10):int{
  $count=0;$rows=DB::table('marketing_campaigns')->whereNull('deleted_at')->whereNotNull('remote_id')->whereIn('status',['unknown','submitted','blocked'])->where('updated_at','>=',now()->subDays(7))->where(fn($q)=>$q->whereNull('remote_checked_at')->orWhere('remote_checked_at','<',now()->subMinutes(2)))->orderBy('remote_checked_at')->limit(min(50,max(1,$limit)))->get();
  foreach($rows as $row){try{self::check($row);}catch(\Throwable){DB::table('marketing_campaigns')->where('id',$row->id)->where('remote_id',$row->remote_id)->update(['remote_checked_at'=>now(),'remote_check_error'=>'Az utólagos állapotellenőrzés most nem sikerült. Később újrapróbáljuk.']);}++$count;}return $count;
 }

}
