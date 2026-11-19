<?php
namespace App\Services;

/** Email layout is added only after sanitizing editable content. */
final class NewsletterDesign
{
 public static function text(string $html,array $sender,string $unsubscribe):string
 {
  $safe=SafeHtml::clean($html);
  $safe=preg_replace_callback('/<a[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is',fn($m)=>strip_tags($m[2]).' ('.html_entity_decode($m[1],ENT_QUOTES,'UTF-8').')',$safe);
  $safe=preg_replace('/<br\s*\/?\s*>|<\/(?:p|h[234]|li|div|blockquote)>/i',"\n",$safe);
  return trim(html_entity_decode(strip_tags($safe),ENT_QUOTES,'UTF-8'))."\n\n".($sender['company']??$sender['senderName']??'')."\n".implode(', ',array_filter([$sender['zip']??'',$sender['city']??'',$sender['address']??'']))."\nLeiratkozás: ".$unsubscribe;
 }
 public static function render(string $html,array $sender,string $unsubscribe):string
 {
  $content=SafeHtml::clean($html);
  $doc=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="UTF-8"><div id="newsletter-content">'.$content.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
  libxml_clear_errors();libxml_use_internal_errors($old);
  foreach($doc->getElementsByTagName('*') as $node){
   $style=match(strtolower($node->tagName)){
    'h2'=>'margin:0 0 20px;font-size:26px;line-height:1.3;color:#172554;',
    'h3','h4'=>'margin:24px 0 12px;font-size:20px;line-height:1.4;color:#172554;',
    'p','ul','ol','blockquote'=>'margin:0 0 18px;line-height:1.7;',
    'a'=>'color:#244dc8;text-decoration:underline;overflow-wrap:anywhere;',
    default=>''};
   if($style)$node->setAttribute('style',$style);
  }
  $content='';foreach($doc->getElementById('newsletter-content')->childNodes as $node)$content.=$doc->saveHTML($node);
  $name=e($sender['company']??$sender['senderName']??'');
  $address=e(implode(', ',array_filter([$sender['zip']??'',$sender['city']??'',$sender['address']??''])));
  return '<!doctype html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hírlevél</title></head><body style="margin:0;padding:0;background-color:#f3f5fa;color:#334155;font-family:Arial,Helvetica,sans-serif;font-size:16px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f5fa"><tr><td align="center" style="padding:24px 12px"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff;border-top:4px solid #244dc8"><tr><td style="padding:24px 24px 12px;font-size:14px;font-weight:bold;color:#172554">'.$name.'</td></tr><tr><td style="padding:12px 24px 24px;line-height:1.7;word-break:break-word">'.$content.'</td></tr><tr><td style="padding:20px 24px;background-color:#f8fafc;font-size:12px;line-height:1.7;color:#526175">'.$name.($address?' · '.$address:'').'<br>Ezt a levelet hírlevél-feliratkozásod alapján kaptad.<br><a style="color:#244dc8;text-decoration:underline" href="'.$unsubscribe.'">Leiratkozás</a></td></tr></table></td></tr></table></body></html>';
 }
}
