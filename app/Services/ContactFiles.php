<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class ContactFiles
{
    public static function read(UploadedFile $file):array
    {
        $ext=strtolower($file->getClientOriginalExtension());
        if($ext==='vcf')return ['kind'=>'contacts','records'=>self::vcard(file_get_contents($file->getRealPath()))];
        if($ext==='csv'||$ext==='tsv'||$ext==='txt')return self::csv(file_get_contents($file->getRealPath()));
        if($ext==='xlsx')return self::xlsx($file->getRealPath());
        throw new InvalidArgumentException('VCF, CSV, TSV vagy XLSX fájlt válassz. A régi XLS táblázatot mentsd XLSX vagy CSV formátumban.');
    }

    private static function utf8(string $text):string
    {
        if(str_starts_with($text,"\xff\xfe")||str_starts_with($text,"\xfe\xff"))$text=mb_convert_encoding($text,'UTF-8','UTF-16');
        elseif(!mb_check_encoding($text,'UTF-8'))$text=mb_convert_encoding($text,'UTF-8','Windows-1250');
        return preg_replace('/^\xEF\xBB\xBF/','',$text);
    }

    public static function csv(string $text):array
    {
        $text=self::utf8($text);$line=strtok($text,"\r\n")?:'';$sep=';';$max=0;
        foreach([';',',',"\t"] as $candidate){$count=count(str_getcsv($line,$candidate,'"',''));if($count>$max){$max=$count;$sep=$candidate;}}
        $stream=fopen('php://temp','r+');fwrite($stream,$text);rewind($stream);$rows=[];
        while(($row=fgetcsv($stream,0,$sep,'"',''))!==false){if(count(array_filter($row,fn($x)=>trim((string)$x)!==''))===0)continue;if(count($row)>100)throw new InvalidArgumentException('Legfeljebb 100 oszlop olvasható be.');$rows[]=array_map(fn($v)=>mb_substr((string)$v,0,2000),$row);if(count($rows)>2001)throw new InvalidArgumentException('Egyszerre legfeljebb 2000 névjegy importálható.');}fclose($stream);
        if(count($rows)<2)throw new InvalidArgumentException('A táblázatban fejléc és legalább egy névjegysor szükséges.');
        return ['kind'=>'table','headers'=>array_shift($rows),'rows'=>$rows];
    }

    public static function vcard(string $text):array
    {
        $text=self::utf8($text);$text=preg_replace('/=\r?\n/','',$text);$text=preg_replace('/\r?\n[ \t]/','',$text);$records=[];$current=null;
        foreach(preg_split('/\r\n|\n|\r/',$text) as $line){if(strtoupper(trim($line))==='BEGIN:VCARD'){$current=['name'=>'','phone'=>'','email'=>'','groups'=>[],'phones'=>[],'emails'=>[]];continue;}if(strtoupper(trim($line))==='END:VCARD'){if($current!==null){$current['phone']=$current['phones'][0]??'';$current['email']=$current['emails'][0]??'';unset($current['phones'],$current['emails']);$records[]=$current;}if(count($records)>2000)throw new InvalidArgumentException('Egyszerre legfeljebb 2000 névjegy importálható.');$current=null;continue;}if($current===null||!str_contains($line,':'))continue;
            [$head,$value]=explode(':',$line,2);$key=strtoupper(preg_replace('/^.*\./','',explode(';',$head)[0]));
            if(str_contains(strtoupper($head),'ENCODING=QUOTED-PRINTABLE'))$value=quoted_printable_decode($value);
            if(preg_match('/CHARSET=([^;:]+)/i',$head,$match)){try{$value=mb_convert_encoding($value,'UTF-8',trim($match[1],'"'));}catch(\ValueError){}}
            $unescape=fn($s)=>str_replace(['\\n','\\N','\\,','\\;','\\\\'],["\n","\n",',',';','\\'],$s);
            if($key==='FN')$current['name']=trim($unescape($value));
            elseif($key==='N'&&$current['name']===''){$parts=preg_split('/(?<!\\\\);/',$value);$current['name']=trim(implode(' ',array_filter(array_map($unescape,$parts))));}
            elseif($key==='TEL'){ $v=preg_replace('/^tel:/i','',$unescape($value));if(str_contains(strtoupper($head),'CELL')||str_contains(strtoupper($head),'PREF'))array_unshift($current['phones'],$v);else $current['phones'][]=$v;}
            elseif($key==='EMAIL'){$v=$unescape($value);if(str_contains(strtoupper($head),'PREF'))array_unshift($current['emails'],$v);else $current['emails'][]=$v;}
            elseif($key==='CATEGORIES')$current['groups']=array_values(array_filter(array_map('trim',preg_split('/(?<!\\\\),/',$value))));
        }
        if(!$records)throw new InvalidArgumentException('Nem található olvasható névjegy a VCF-fájlban.');return $records;
    }

    private static function xml(string $text):\DOMDocument
    {
        if(stripos($text,'<!DOCTYPE')!==false||stripos($text,'<!ENTITY')!==false)throw new InvalidArgumentException('A táblázat nem támogatott XML-t tartalmaz.');
        $doc=new \DOMDocument();$old=libxml_use_internal_errors(true);try{$ok=$doc->loadXML($text,LIBXML_NONET);libxml_clear_errors();}finally{libxml_use_internal_errors($old);}if(!$ok)throw new InvalidArgumentException('Sérült XLSX-fájl.');return $doc;
    }

    private static function xlsx(string $path):array
    {
        $tmp=tempnam(sys_get_temp_dir(),'contacts-');unlink($tmp);$tmp.='.zip';copy($path,$tmp);chmod($tmp,0600);
        try{$zip=new \PharData($tmp);$size=0;$count=0;foreach(new \RecursiveIteratorIterator($zip) as $entry){$size+=$entry->getSize();if(++$count>500||$size>12*1024*1024)throw new InvalidArgumentException('Túl nagy vagy túl összetett XLSX-fájl.');}
            $strings=[];if(isset($zip['xl/sharedStrings.xml'])){$d=self::xml($zip['xl/sharedStrings.xml']->getContent());foreach($d->getElementsByTagName('si') as $si){$value='';foreach($si->getElementsByTagName('t') as $t)$value.=$t->textContent;$strings[]=$value;}}
            if(!isset($zip['xl/worksheets/sheet1.xml']))throw new InvalidArgumentException('Az első munkalap nem olvasható. Mentsd a névjegyek munkalapját külön CSV-fájlba.');
            $d=self::xml($zip['xl/worksheets/sheet1.xml']->getContent());$rows=[];
            foreach($d->getElementsByTagName('row') as $row){$values=[];foreach($row->getElementsByTagName('c') as $cell){preg_match('/^([A-Z]+)/',$cell->getAttribute('r'),$m);$col=0;foreach(str_split($m[1]??'A') as $ch)$col=$col*26+ord($ch)-64;if($col>100)throw new InvalidArgumentException('Legfeljebb 100 oszlop olvasható be.');$v=$cell->getElementsByTagName('v')->item(0)?->textContent??'';$type=$cell->getAttribute('t');if($cell->getElementsByTagName('f')->length)$v='';elseif($type==='s')$v=$strings[(int)$v]??'';elseif($type==='inlineStr'){$v='';foreach($cell->getElementsByTagName('t') as $t)$v.=$t->textContent;}$values[$col-1]=mb_substr($v,0,2000);}if(!$values)continue;$dense=array_fill(0,max(array_keys($values))+1,'');foreach($values as $i=>$v)$dense[$i]=$v;if(!array_filter($dense,fn($v)=>trim($v)!==''))continue;$rows[]=$dense;if(count($rows)>2001)throw new InvalidArgumentException('Egyszerre legfeljebb 2000 névjegy importálható.');}
            if(count($rows)<2)throw new InvalidArgumentException('A táblázatban fejléc és legalább egy névjegysor szükséges.');return ['kind'=>'table','headers'=>array_shift($rows),'rows'=>$rows,'note'=>'Az XLSX első munkalapját olvastuk be. A képleteket nem futtatjuk.'];
        }catch(\UnexpectedValueException|\BadMethodCallException $e){throw new InvalidArgumentException('Az XLSX-fájl nem olvasható. Próbáld CSV-ként elmenteni.');}finally{unset($zip);if(is_file($tmp))unlink($tmp);}
    }
}
