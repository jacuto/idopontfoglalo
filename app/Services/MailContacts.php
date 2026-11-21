<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class MailContacts
{
    private static int $requests=0;
    public static function credentials(string $engine):array
    {
        $shared=SharedConnections::all();$id=($shared['contacts_'.$engine.'_client_id']??null)?:config('contact-import.'.$engine.'_client_id');$secret=($shared['contacts_'.$engine.'_client_secret']??null)?:config('contact-import.'.$engine.'_client_secret');
        if($engine==='google'&&!$id&&!$secret)return ['id'=>$shared['google_client_id']??'','secret'=>$shared['google_client_secret']??''];
        return ['id'=>$id,'secret'=>$secret];
    }
    public static function read(string $engine,string $token):array
    {
        self::$requests=0;return $engine==='google'?self::google($token):self::microsoft($token);
    }
    private static function get(string $token,string $url,array $query=[]):array
    {
        if(++self::$requests>80)throw new InvalidArgumentException('Túl sok lekérési oldal. Válassz kisebb exportált névjegycsoportot.');
        $response=Http::timeout(15)->withToken($token)->get($url,$query?:null);
        if(!$response->successful())throw new InvalidArgumentException('A névjegyek lekérése nem sikerült. Ellenőrizd az engedélyt és a szolgáltatói kapcsolat beállítását, vagy válassz névjegyfájlt.');
        return $response->json()??[];
    }
    private static function google(string $token):array
    {
        $groups=[];$next=null;
        do{$r=self::get($token,'https://people.googleapis.com/v1/contactGroups',array_filter(['pageSize'=>1000,'pageToken'=>$next]));foreach($r['contactGroups']??[] as $g)$groups[$g['resourceName']]=$g['name']??'Csoport';$next=$r['nextPageToken']??null;if(count($groups)>2000)throw new InvalidArgumentException('Túl sok névjegycsoport. Válassz exportált névjegyfájlt.');}while($next);
        $records=[];$next=null;
        do{$r=self::get($token,'https://people.googleapis.com/v1/people/me/connections',array_filter(['personFields'=>'names,emailAddresses,phoneNumbers,memberships','pageSize'=>1000,'pageToken'=>$next]));foreach($r['connections']??[] as $p){$labels=[];foreach($p['memberships']??[] as $member){$key=$member['contactGroupMembership']['contactGroupResourceName']??'';if(isset($groups[$key])&&!str_starts_with($key,'contactGroups/myContacts'))$labels[]=$groups[$key];}$records[]=['name'=>$p['names'][0]['displayName']??'','phone'=>$p['phoneNumbers'][0]['value']??'','email'=>$p['emailAddresses'][0]['value']??'','groups'=>array_values(array_unique($labels))];}$next=$r['nextPageToken']??null;if(count($records)>2000||($next&&count($records)>=2000))throw new InvalidArgumentException('A fiók több mint 2000 névjegyet tartalmaz. Exportálj egy kisebb csoportot CSV vagy VCF fájlba.');}while($next);
        return $records;
    }
    private static function microsoft(string $token):array
    {
        $folders=[['id'=>null,'displayName'=>'Alapértelmezett névjegyek']];$url='https://graph.microsoft.com/v1.0/me/contactFolders?$top=100';
        while($url){$r=self::get($token,$url);foreach($r['value']??[] as $folder)$folders[]=$folder;$url=self::next($r);if(count($folders)>100)throw new InvalidArgumentException('Túl sok névjegymappa. Válassz exportált névjegyfájlt.');}
        // Walk nested folders; only contact endpoints under Microsoft Graph are accepted.
        for($i=1;$i<count($folders);$i++){if(empty($folders[$i]['childFolderCount']))continue;$url='https://graph.microsoft.com/v1.0/me/contactFolders/'.rawurlencode($folders[$i]['id']).'/childFolders?$top=100';while($url){$r=self::get($token,$url);foreach($r['value']??[] as $folder)$folders[]=$folder;$url=self::next($r);if(count($folders)>100)throw new InvalidArgumentException('Túl sok névjegymappa. Válassz exportált névjegyfájlt.');}}
        $records=[];$seen=[];
        foreach($folders as $folder){$url='https://graph.microsoft.com/v1.0/me/'.($folder['id']?'contactFolders/'.rawurlencode($folder['id']).'/':'').'contacts?$top=100&$select=id,displayName,givenName,surname,emailAddresses,mobilePhone,businessPhones,homePhones,categories';while($url){$r=self::get($token,$url);foreach($r['value']??[] as $p){if(isset($seen[$p['id']]))continue;$seen[$p['id']]=true;$records[]=['name'=>$p['displayName']??trim(($p['givenName']??'').' '.($p['surname']??'')),'phone'=>($p['mobilePhone']??'')?:($p['businessPhones'][0]??$p['homePhones'][0]??''),'email'=>$p['emailAddresses'][0]['address']??'','groups'=>array_values(array_unique(array_merge([$folder['displayName']],$p['categories']??[])))];}$url=self::next($r);if(count($records)>2000||($url&&count($records)>=2000))throw new InvalidArgumentException('A fiók több mint 2000 névjegyet tartalmaz. Exportálj egy kisebb csoportot.');}}
        return $records;
    }
    private static function next(array $response):?string
    {
        $url=$response['@odata.nextLink']??null;if($url!==null&&(!is_string($url)||!str_starts_with($url,'https://graph.microsoft.com/v1.0/me/')))throw new InvalidArgumentException('Érvénytelen Microsoft lapozási hivatkozás.');return $url;
    }
}
