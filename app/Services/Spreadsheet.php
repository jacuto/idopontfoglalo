<?php
namespace App\Services;
final class Spreadsheet {
 public static function download(array $sheets,string $filename){
  $dir=storage_path('app/private/exports');if(!is_dir($dir))mkdir($dir,0700,true);$path=$dir.'/'.bin2hex(random_bytes(16)).'.zip';$zip=new \PharData($path,0,null,\Phar::ZIP);
  $escape=fn($s)=>htmlspecialchars((string)$s,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $types='<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
  $book='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';$rels='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
  foreach(array_values($sheets) as $i=>$sheet){$n=$i+1;$book.='<sheet name="'.$escape($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';$rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';$types.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
   $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
   foreach($sheet['rows'] as $r=>$row){$xml.='<row r="'.($r+1).'">';foreach(array_values($row) as $c=>$value){$col='';for($x=$c+1;$x>0;$x=intdiv($x-1,26))$col=chr(65+($x-1)%26).$col;$ref=$col.($r+1);$xml.=is_int($value)||is_float($value)?'<c r="'.$ref.'"><v>'.$value.'</v></c>':'<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$escape($value??'').'</t></is></c>';}$xml.='</row>';}$zip->addFromString('xl/worksheets/sheet'.$n.'.xml',$xml.'</sheetData></worksheet>');
  }
  $zip->addFromString('[Content_Types].xml',$types.'</Types>');$zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');$zip->addFromString('xl/workbook.xml',$book.'</sheets></workbook>');$zip->addFromString('xl/_rels/workbook.xml.rels',$rels.'</Relationships>');unset($zip);
  return response()->download($path,$filename,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'private,no-store'])->deleteFileAfterSend(true);
 }
}
