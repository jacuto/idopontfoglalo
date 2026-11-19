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
 public const KEYS=['free','offer','new','notice','general'];
 public static function bookingUrl(int $provider):string
 {
  return $provider>0?rtrim(config('app.url'),'/').'/foglalas?provider='.$provider:'';
 }
 public static function render(string $html,array $sender,string $unsubscribe,string $template='general',string $bookingUrl=''):string
 {
  [$accent,$soft,$label]=match($template){
   'free'=>['#08755d','#e7f5ef','VÁRUNK SZERETETTEL'],
   'offer'=>['#7736a8','#f3ebfa','NEKED AJÁNLJUK'],
   'new'=>['#a43e24','#fff0e8','VALAMI ÚJ ÉRKEZETT'],
   'notice'=>['#294b68','#edf3f8','FONTOS TUDNIVALÓ'],
   default=>['#284cc4','#eef1ff','HÍREK ÉS ÚJDONSÁGOK']};
  $content=SafeHtml::clean($html);
  $doc=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="UTF-8"><div id="newsletter-content">'.$content.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
  libxml_clear_errors();libxml_use_internal_errors($old);
  $root=$doc->getElementById('newsletter-content');$heading=$root->getElementsByTagName('h2')->item(0);
  $title=$heading?e($heading->textContent):'Híreink és újdonságaink';if($heading)$heading->parentNode->removeChild($heading);
  foreach($doc->getElementsByTagName('*') as $node){
   $style=match(strtolower($node->tagName)){
    'h2'=>'margin:0 0 20px;font-size:26px;line-height:1.3;color:#172554;',
    'h3','h4'=>'margin:28px 0 14px;padding-bottom:10px;border-bottom:2px solid '.$soft.';font-size:21px;line-height:1.4;color:'.$accent.';',
    'p','ul','ol'=>'margin:0 0 20px;line-height:1.75;',
    'blockquote'=>'margin:24px 0;padding:20px;background-color:'.$soft.';border-left:4px solid '.$accent.';line-height:1.7;',
    'a'=>'color:'.$accent.';text-decoration:underline;overflow-wrap:anywhere;',
    default=>''};
   if($node->tagName==='a'&&$bookingUrl!==''&&$node->getAttribute('href')===$bookingUrl)$style='display:inline-block;background-color:'.$accent.';border:14px solid '.$accent.';border-left-width:24px;border-right-width:24px;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;line-height:1.4;border-radius:6px;';
   if($style)$node->setAttribute('style',$style);
  }
  $content='';foreach($root->childNodes as $node)$content.=$doc->saveHTML($node);
  $name=e($sender['company']??$sender['senderName']??'');
  $address=e(implode(', ',array_filter([$sender['zip']??'',$sender['city']??'',$sender['address']??''])));
  return '<!doctype html><html lang="hu"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hírlevél</title></head><body style="margin:0;padding:0;background-color:'.$soft.';color:#334155;font-family:Arial,Helvetica,sans-serif;font-size:16px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:'.$soft.'"><tr><td align="center" style="padding:28px 12px"><table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background-color:#ffffff"><tr><td style="padding:24px;font-size:18px;letter-spacing:1px;font-weight:bold;color:'.$accent.'">'.$name.'</td></tr><tr><td bgcolor="'.$accent.'" style="padding:34px 24px;background-color:'.$accent.';color:#ffffff"><p style="margin:0 0 16px;font-size:11px;letter-spacing:2px;font-weight:bold;color:#ffffff">'.$label.'</p><h1 style="margin:0;font-size:32px;line-height:1.25;font-weight:bold;color:#ffffff">'.$title.'</h1></td></tr><tr><td style="padding:30px 24px 18px;line-height:1.75;word-break:break-word">'.$content.'</td></tr><tr><td style="padding:22px 24px;background-color:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.8;color:#526175"><strong style="color:#172554">'.$name.'</strong>'.($address?'<br>'.$address:'').'<br>Ezt a levelet hírlevél-feliratkozásod alapján kaptad.<br><a style="color:'.$accent.';text-decoration:underline" href="'.$unsubscribe.'">Leiratkozás</a></td></tr></table><p style="margin:16px 0 0;color:#64748b;font-size:11px">'.$name.'</p></td></tr></table></body></html>';
 }
}
