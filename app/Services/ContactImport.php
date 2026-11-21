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
    public static function inspect(array $clients, array $input): array
    {
        $name=trim((string)($input['name']??''));
        try {
            $record=['name'=>$name,'phone'=>self::phone($input['phone']??''),'email'=>Contact::email($input['email']??'')];
            if(mb_strlen($name)>150||mb_strlen($record['email'])>200||strlen($record['phone'])>40)throw new InvalidArgumentException('Túl hosszú név vagy elérhetőség.');
            if($record['phone']===''&&$record['email']==='')throw new InvalidArgumentException('Legalább telefonszám vagy e-mail szükséges.');
        }catch(InvalidArgumentException $e){return ['status'=>'invalid','message'=>$e->getMessage(),'record'=>['name'=>$name,'phone'=>(string)($input['phone']??''),'email'=>(string)($input['email']??'')],'changes'=>[]];}
        $matches=[];
        foreach($clients as $c){if(!empty($c['deletedAt']))continue;$phone=Contact::phone($c['phone']??'',false);$email=mb_strtolower(trim($c['email']??''));if(($record['phone']!==''&&$phone===$record['phone'])||($record['email']!==''&&$email===$record['email']))$matches[]=$c;}
        if(count($matches)>1)return ['status'=>'conflict','message'=>'Az elérhetőségek több vendégre mutatnak. Külön ellenőrzés szükséges.','record'=>$record,'changes'=>[]];
        if(!$matches){if($name==='')return ['status'=>'invalid','message'=>'Új vendéghez add meg a nevet.','record'=>$record,'changes'=>[]];return ['status'=>'new','message'=>'Új vendég','record'=>$record,'changes'=>$record];}
        $c=$matches[0];$changes=[];$different=[];
        foreach(['name','phone','email'] as $key){$old=trim($c[$key]??'');if($old===''&&$record[$key]!=='')$changes[$key]=$record[$key];elseif($old!==''&&$record[$key]!==''&&($key==='phone'?Contact::phone($old,false):mb_strtolower($old))!==mb_strtolower($record[$key]))$different[]=$key;}
        // An alternative name can be a phone-book nickname. Different contact fields require manual review.
        if(array_intersect($different,['phone','email']))return ['status'=>'conflict','message'=>'Meglévő vendég, de eltérő telefonszám vagy e-mail. Nem írjuk felül.','record'=>$record,'existing'=>array_intersect_key($c,array_flip(['id','name','phone','email'])),'changes'=>[]];
        return ['status'=>$changes?'fill':'same','message'=>$changes?'Hiányzó adatok kiegészíthetők':'Már szerepel, nincs új adat','record'=>$record,'existing'=>array_intersect_key($c,array_flip(['id','name','phone','email'])),'changes'=>$changes,'nameDiffers'=>in_array('name',$different,true)];
    }

    public static function preview(array $clients,array $records):array
    {
        $seen=[];$items=[];
        foreach($records as $index=>$record){$item=self::inspect($clients,$record);$item['index']=$index;$keys=[];foreach(['phone','email'] as $key)if(!empty($item['record'][$key]))$keys[]=$key.':'.$item['record'][$key];
            if(!in_array($item['status'],['invalid','conflict'],true)&&array_intersect($keys,$seen)){$item['status']='duplicate';$item['message']='Ebben az importban már szerepel ugyanez az elérhetőség.';$item['changes']=[];}
            if(!in_array($item['status'],['invalid','conflict','duplicate'],true))$seen=array_merge($seen,$keys);
            $items[]=$item;
        }
        return $items;
    }

    public static function apply(array &$state,array $preview,array $selected):array
    {
        $result=['added'=>0,'updated'=>0,'skipped'=>0];
        foreach($selected as $index){$before=$preview[$index]??null;if(!$before||!in_array($before['status'],['new','fill'],true)){++$result['skipped'];continue;}
            $current=self::inspect($state['clients'],$before['record']);
            if($current['status']!==$before['status']||($current['existing']['id']??null)!==($before['existing']['id']??null)||$current['changes']!==$before['changes']){++$result['skipped'];continue;}
            if($current['status']==='new'){$state['clients'][]=$current['record']+['id'=>(string)Str::uuid(),'guestId'=>null,'createdAt'=>now()->toIso8601String(),'importedAt'=>now()->toIso8601String()];++$result['added'];}
            else{foreach($state['clients'] as &$client)if($client['id']===$current['existing']['id']){foreach($current['changes'] as $k=>$v)$client[$k]=$v;break;}unset($client);++$result['updated'];}
        }
        return $result;
    }
}
