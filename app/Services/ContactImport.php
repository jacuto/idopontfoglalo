<?php
namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** Imports only contact fields: never account links, bookings or notification consent. */
final class ContactImport
{
    public static function phone(string $raw):string
    {
        $value=preg_replace('/^tel:\s*/i','',trim($raw));
        if(preg_match('/[\p{L}]/u',$value)||substr_count($value,'+')>1||(str_contains($value,'+')&&!str_starts_with($value,'+')))throw new InvalidArgumentException('Ellenőrizd a telefonszámot: betűt vagy több számot tartalmazhat.');
        $digits=preg_replace('/\D/','',$value);return Contact::phone(str_starts_with($value,'+')?'+'.$digits:$digits);
    }
    public static function inspect(array $clients,array $input,string $policy='fill',?string $target=null):array
    {
        ImportPolicy::mode($policy);$name=trim((string)($input['name']??''));
        try{$record=['name'=>$name,'phone'=>self::phone($input['phone']??''),'email'=>Contact::email($input['email']??'')];
            if(mb_strlen($name)>150||mb_strlen($record['email'])>200||strlen($record['phone'])>40)throw new InvalidArgumentException('Túl hosszú név vagy elérhetőség.');
            if($record['phone']===''&&$record['email']==='')throw new InvalidArgumentException('Legalább telefonszám vagy e-mail szükséges.');
        }catch(InvalidArgumentException $e){return ['status'=>'invalid','message'=>$e->getMessage(),'record'=>['name'=>$name,'phone'=>(string)($input['phone']??''),'email'=>(string)($input['email']??'')],'changes'=>[]];}
        $found=Clients::contactMatches($clients,$record);$matches=[];foreach([...$found['email'],...$found['phone']] as $c)$matches[$c['id']]=$c;$c=null;
        if($target==='new'&&!$found['email'])$matches=[];
        elseif($target!==null){$c=$matches[$target]??null;if(!$c)return ['status'=>'conflict','message'=>'A kiválasztott vendég már nem egyezik az adatokkal.','record'=>$record,'changes'=>[],'candidates'=>array_values($matches)];}
        elseif(count($matches)===1)$c=reset($matches);
        if(count($matches)>1&&!$c)return ['status'=>'conflict','message'=>'Több lehetséges vendég. Válaszd ki a megfelelőt.','record'=>$record,'changes'=>[],'candidates'=>array_values($matches),'canCreate'=>!$found['email']];
        if(!$matches){if($name==='')return ['status'=>'invalid','message'=>'Új vendéghez add meg a nevet.','record'=>$record,'changes'=>[]];return ['status'=>'new','message'=>'Új vendég','record'=>$record,'changes'=>$record];}
        $fold=function($value){$words=preg_split('/\s+/',mb_strtolower(\Illuminate\Support\Str::ascii(trim($value))),-1,PREG_SPLIT_NO_EMPTY);sort($words);return implode(' ',$words);};
        if($target===null&&!$found['email']&&$name!==''&&$fold($name)!==$fold($c['name']))return ['status'=>'conflict','message'=>'A telefonszám egyezik, de a név eltér. Válassz meglévő vendéget vagy új adatlapot.','record'=>$record,'changes'=>[],'candidates'=>array_values($matches),'canCreate'=>true];
        $full=collect($clients)->firstWhere('id',$c['id']);$changes=ImportPolicy::changes($full,$record,$policy);
        try{if(array_key_exists('email',$changes))Clients::preventDuplicate($clients,array_replace($full,$changes));Clients::editable($full,$changes);}catch(InvalidArgumentException $e){return ['status'=>'conflict','message'=>$e->getMessage(),'record'=>$record,'existing'=>$c,'changes'=>[],'candidates'=>array_values($matches),'canCreate'=>!$found['email']];}
        $status=$policy==='skip'?'same':($changes?($policy==='overwrite'?'update':'fill'):'same');
        return ['status'=>$status,'message'=>['update'=>'Meglévő adatok frissítése','fill'=>'Hiányzó adatok kiegészítése','same'=>$policy==='skip'?'Meglévő vendég kihagyása':'Már szerepel, nincs új adat'][$status],'record'=>$record,'existing'=>$c,'changes'=>$changes,'nameDiffers'=>mb_strtolower($c['name'])!==mb_strtolower($name),'target'=>$target];
    }
    public static function preview(array $clients,array $records,string $policy='fill',array $resolutions=[]):array
    {
        $items=[];$seen=[];foreach($records as $index=>$record){$item=self::inspect($clients,$record,$policy,$resolutions[$index]??null);$item['index']=$index;
            $key=!empty($item['record']['email'])?'email:'.$item['record']['email']:'phone:'.($item['record']['phone']??'').':'.mb_strtolower(trim($item['record']['name']??''));
            if(!in_array($item['status'],['invalid','conflict'],true)&&isset($seen[$key])){$item['status']='duplicate';$item['message']='A fájlban már szerepel ugyanez a vendég.';$item['changes']=[];}
            if(!in_array($item['status'],['invalid','conflict','duplicate'],true))$seen[$key]=true;$items[]=$item;
        }return $items;
    }
    public static function apply(array &$state,array $preview,array $selected,string $policy='fill'):array
    {
        ImportPolicy::mode($policy);$result=['added'=>0,'updated'=>0,'skipped'=>0];
        foreach($selected as $index){$before=$preview[$index]??null;if(!$before||!in_array($before['status'],['new','fill','update'],true)){++$result['skipped'];continue;}
            if($before['status']==='new'){
                if(Clients::contactMatches($state['clients'],$before['record'])['email']){++$result['skipped'];continue;}
                $state['clients'][]=$before['record']+['id'=>(string)Str::uuid(),'guestId'=>null,'createdAt'=>now()->toIso8601String(),'importedAt'=>now()->toIso8601String()];++$result['added'];continue;
            }
            $current=self::inspect($state['clients'],$before['record'],$policy,$before['target']??null);
            if($current['status']!==$before['status']||($current['existing']??null)!==($before['existing']??null)||$current['changes']!==$before['changes']){++$result['skipped'];continue;}
            foreach($state['clients'] as &$client)if($client['id']===$current['existing']['id']){$client=array_replace($client,$current['changes']);break;}unset($client);++$result['updated'];
        }return $result;
    }
}
