<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
final class MarketingGateway
{
 public function __construct(private array $keys){}
 public function call(string $method,string $path,array $data=[],bool $allowNotFound=false):array{
  $brevo=$this->keys['engine']==='brevo';$request=Http::timeout(20)->connectTimeout(10)->acceptJson();$request=$brevo?$request->withHeaders(['api-key'=>$this->keys['apiKey']]):$request->withBasicAuth('raero',$this->keys['apiKey']);
  $base=$brevo?'https://api.brevo.com/v3/':'https://'.$this->keys['dc'].'.api.mailchimp.com/3.0/';
  try{$response=$request->send($method,$base.ltrim($path,'/'),strtoupper($method)==='GET'?['query'=>$data]:['json'=>$data]);}catch(\Throwable){throw new MarketingException('A hírlevélküldő nem igazolta a kérés eredményét. Ellenőrizd a külső fiókban.');}
  if($allowNotFound&&$method==='GET'&&$response->status()===404)return ['notFound'=>true];
  if(!$response->successful())throw new MarketingException($response->status()===429?'A hírlevélküldő kéréskorlátja betelt.':($response->status()===402?'A hírlevélküldő ingyenes vagy fizetős küldési kerete nem elegendő.':'A hírlevélküldő elutasította a kérést. Ellenőrizd a kulcsot, a feladót és a fiók küldési keretét.'));
  $v=$response->json();return is_array($v)?$v:[];
 }
 public function test():array{
  $v=$this->call('GET',$this->keys['engine']==='brevo'?'account':'');return ['ok'=>true,'account'=>$v['companyName']??$v['account_name']??$v['email']??'A kapcsolat elérhető'];
 }
 public function audience(int $provider):string{
  if(!empty($this->keys['listId'])){if($this->keys['engine']==='brevo'&&empty($this->keys['folderId'])){$list=$this->call('GET','contacts/lists/'.rawurlencode((string)$this->keys['listId']));if(empty($list['folderId']))throw new MarketingException('A megadott Brevo-lista mappája nem érhető el. Ellenőrizd a listaazonosítót.');$this->keys['folderId']=$list['folderId'];MarketingConnections::patch($provider,['folderId'=>$list['folderId']]);}return (string)$this->keys['listId'];}
  if($this->keys['engine']==='brevo'){
   $folder=$this->keys['folderId']??null;if(!$folder){$v=$this->call('POST','contacts/folders',['name'=>'Raero-'.$provider]);$folder=$v['id']??null;if(!$folder)throw new MarketingException('A Brevo-mappa nem jött létre.');$this->keys['folderId']=$folder;MarketingConnections::patch($provider,['folderId'=>$folder]);}
   $v=$this->call('POST','contacts/lists',['name'=>'Ráérő feliratkozók','folderId'=>(int)$folder]);
  }else{
   foreach(['company','address','city','zip','country'] as $field)if(empty($this->keys[$field]))throw new MarketingException('A Mailchimp-listához töltsd ki a feladó postai adatait is.');
   // The Free plan has one audience: prefer its existing audience instead of duplicating contacts.
   $lists=$this->call('GET','lists',['count'=>2]);if(!empty($lists['lists'])){$id=(string)$lists['lists'][0]['id'];$this->keys['listId']=$id;MarketingConnections::patch($provider,['listId'=>$id]);return $id;}
   $v=$this->call('POST','lists',['name'=>'Ráérő feliratkozók','contact'=>['company'=>$this->keys['company'],'address1'=>$this->keys['address'],'city'=>$this->keys['city'],'state'=>'','zip'=>$this->keys['zip'],'country'=>$this->keys['country']],'permission_reminder'=>'Ön hozzájárult a szolgáltató hírleveleinek fogadásához.','campaign_defaults'=>['from_name'=>$this->keys['senderName'],'from_email'=>$this->keys['senderEmail'],'subject'=>'Hírlevél','language'=>'hu'],'email_type_option'=>false]);
  }
  if(empty($v['id']))throw new MarketingException('A hírlevéllista létrehozása nem igazolt.');$this->keys['listId']=(string)$v['id'];MarketingConnections::patch($provider,['listId'=>(string)$v['id']]);return (string)$v['id'];
 }
 public function contact(string $list,array $recipient):bool{
  $existing=$this->call('GET',$this->keys['engine']==='brevo'?'contacts/'.rawurlencode($recipient['email']):'lists/'.rawurlencode($list).'/members/'.md5(mb_strtolower($recipient['email'])),[],true);
  if(!empty($existing['emailBlacklisted'])||in_array($existing['status']??'',['unsubscribed','cleaned','pending','archived'],true))return false;
  if($this->keys['engine']==='brevo')$this->call('POST','contacts',['email'=>$recipient['email'],'listIds'=>[(int)$list],'updateEnabled'=>true]);
  else $this->call('PUT','lists/'.rawurlencode($list).'/members/'.md5(mb_strtolower($recipient['email'])),['email_address'=>$recipient['email'],'status_if_new'=>'subscribed']);
  return true;
 }
 public function create(object $campaign,string $list,array $recipients):string{
  if($this->keys['engine']==='brevo'&&empty($this->keys['folderId']))$this->keys=array_replace($this->keys,MarketingConnections::get((int)$campaign->provider_id));
  $footer='<hr><p>'.e($this->keys['company']??$this->keys['senderName']).' · '.e(implode(', ',array_filter([$this->keys['zip']??'',$this->keys['city']??'',$this->keys['address']??'']))).'</p>';
  if($this->keys['engine']==='brevo'){
   // A segment is built for this campaign so an older contact in the shared list cannot leak in.
   $v=$this->call('POST','contacts/lists',['name'=>'Ráérő kampány '.substr($campaign->id,0,8),'folderId'=>(int)$this->keys['folderId']]);$campaignList=$v['id']??null;if(!$campaignList)throw new MarketingException('A kampány címzettlistája nem jött létre.');
   // Brevo adds existing contacts in one idempotent list-membership request.
   foreach(array_chunk(array_column($recipients,'email'),150) as $emails)$this->call('POST','contacts/lists/'.$campaignList.'/contacts/add',['emails'=>$emails]);
   $v=$this->call('POST','emailCampaigns',['name'=>'Ráérő '.substr($campaign->id,0,8),'subject'=>$campaign->subject,'sender'=>['email'=>$this->keys['senderEmail'],'name'=>$this->keys['senderName']],'htmlContent'=>$campaign->html.$footer.'<p><a href="{{ unsubscribe }}">Leiratkozás</a></p>','recipients'=>['listIds'=>[(int)$campaignList]],'toField'=>'{EMAIL}']);
  }else{
   $segment=$this->call('POST','lists/'.rawurlencode($list).'/segments',['name'=>'Raero-'.substr($campaign->id,0,8),'static_segment'=>array_column($recipients,'email')]);if(empty($segment['id']))throw new MarketingException('A Mailchimp címzettcsoport nem jött létre.');
   $v=$this->call('POST','campaigns',['type'=>'regular','recipients'=>['list_id'=>$list,'segment_opts'=>['saved_segment_id'=>(int)$segment['id']]],'settings'=>['subject_line'=>$campaign->subject,'from_name'=>$this->keys['senderName'],'reply_to'=>$this->keys['senderEmail'],'title'=>'Raero-'.substr($campaign->id,0,8)]]);if(empty($v['id']))throw new MarketingException('A Mailchimp-kampány nem jött létre.');$this->call('PUT','campaigns/'.rawurlencode($v['id']).'/content',['html'=>$campaign->html.$footer.'<p><a href="*|UNSUB|*">Leiratkozás</a></p>']);
  }
  if(empty($v['id']))throw new MarketingException('A kampányazonosító hiányzik.');return (string)$v['id'];
 }
 public function send(string $id):void{$this->call('POST',$this->keys['engine']==='brevo'?'emailCampaigns/'.rawurlencode($id).'/sendNow':'campaigns/'.rawurlencode($id).'/actions/send');}
 public function preview(string $id,string $email):void{$this->call('POST',$this->keys['engine']==='brevo'?'emailCampaigns/'.rawurlencode($id).'/sendTest':'campaigns/'.rawurlencode($id).'/actions/test',$this->keys['engine']==='brevo'?['emailTo'=>[$email]]:['test_emails'=>[$email],'send_type'=>'html']);}
 public function report(string $id):array{return $this->call('GET',$this->keys['engine']==='brevo'?'emailCampaigns/'.rawurlencode($id):'campaigns/'.rawurlencode($id));}
}
