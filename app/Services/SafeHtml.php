<?php
namespace App\Services;
final class SafeHtml
{
 public static function clean(?string $html,bool $template=false):string {
  $html=mb_substr($html??'',0,50000);if(!str_contains($html,'<'))return '<p>'.nl2br(htmlspecialchars($html,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')).'</p>';
  $doc=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8"><div id="raero-root">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($old);
  $allowed=['p','br','strong','b','em','i','u','ul','ol','li','h2','h3','h4','blockquote','a','span','div'];
  $walk=function($node)use(&$walk,$allowed,$template){foreach(iterator_to_array($node->childNodes) as $child){if($child instanceof \DOMElement){$tag=strtolower($child->tagName);if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','button','textarea','select','img','video','audio'],true)){$node->removeChild($child);continue;}$walk($child);if(!in_array($tag,$allowed,true)){while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;}$href=$tag==='a'?$child->getAttribute('href'):'';foreach(iterator_to_array($child->attributes) as $attr)$child->removeAttribute($attr->name);if($tag==='a'){$safe=preg_match('#^https?://[^\s<>]+$#i',$href)||preg_match('/^mailto:[a-z0-9._+\-]+@[a-z0-9.\-]+$/i',$href)||($template&&in_array($href,['{{manage_url}}','{{account_url}}','{{provider_url}}'],true));if($safe){$child->setAttribute('href',$href);$child->setAttribute('rel','noopener noreferrer');}}}elseif($child instanceof \DOMComment||$child instanceof \DOMProcessingInstruction)$node->removeChild($child);}};
  $root=$doc->getElementById('raero-root');if(!$root)return '';$walk($root);$result='';foreach($root->childNodes as $node)$result.=$doc->saveHTML($node);if($template)foreach(['manage_url','account_url','provider_url'] as $key)$result=str_ireplace('%7B%7B'.$key.'%7D%7D','{{'.$key.'}}',$result);return $result;
 }
}
