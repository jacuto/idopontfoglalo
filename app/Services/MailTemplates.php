<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
final class MailTemplates
{
 public const EVENTS=['member_invite'=>'Munkatársi meghívó és jelszóbeállítás','guest_verify'=>'Vendég e-mail-cím megerősítése','provider_reset'=>'Szolgáltatói jelszó-visszaállítás','guest_reset'=>'Vendég jelszó-visszaállítás','review_request'=>'Értékelés kérése','created'=>'Új foglalás','confirmed'=>'Foglalás elfogadva','guest_invite'=>'Vendégfiók meghívó','updated'=>'Foglalás módosítva','cancelled'=>'Foglalás lemondva','group_missed'=>'Csoportos óra elmarad','reminder'=>'Időpont emlékeztető','no_show'=>'Nem jelent meg','message'=>'Belső üzenet','platform_message'=>'Üzemeltetői üzenet'];
 public function all():array {$saved=DB::table('mail_templates')->get()->keyBy('event');$out=[];foreach(self::EVENTS as $event=>$label){$row=$saved->get($event);$out[]=['event'=>$event,'label'=>$label,'subject'=>$row->subject??($label.' — {{business}}'),'html'=>$row->html??'<h2>'.$label.'</h2><p>{{business}}</p><p>Kedves {{name}}!</p><p>{{details}}</p><p><a href="{{manage_url}}">Részletek megnyitása</a></p>','enabled'=>$row?(bool)$row->enabled:true];}return $out;}
 public function render(string $event,array $variables):array {$t=collect($this->all())->firstWhere('event',$event);return $t?self::renderTemplate($t,$variables):['enabled'=>false];}
 public static function renderTemplate(array $t,array $variables):array {$escaped=[];$plain=[];foreach($variables as $key=>$value){$macro='{{'.$key.'}}';$escaped[$macro]=$key==='details'?nl2br(e((string)$value)):e((string)$value);$plain[$macro]=(string)$value;}return ['enabled'=>$t['enabled'],'subject'=>preg_replace('/[\r\n]+/',' ',strip_tags(strtr($t['subject'],$plain))),'html'=>SafeHtml::clean(strtr($t['html'],$escaped))];}
}
