<?php
namespace App\Http\Middleware;
use App\Services\SecurityAudit;
use Closure;
use Illuminate\Http\Request;
final class LoginAudit
{
 public function handle(Request $r,Closure $next){if(!$r->hasSession())return $next($r);$support=$r->session()->get('platform_support');$before=$this->identities($r);$session=$r->session()->getId();$response=$next($r);if($support&&!$r->is('api/platform/support*')&&(!$r->session()->has('platform_support')||$r->session()->get('platform_admin')!==true)){SecurityAudit::platform($r,'support_end',(int)$support['providerId']);$r->session()->forget('platform_support');}if($response->getStatusCode()<400&&!$r->is('api/platform/support*'))foreach($this->identities($r) as $type=>$identity)if(!isset($before[$type])||$before[$type]!==$identity||$session!==$r->session()->getId())SecurityAudit::record($r,'login',$type,$identity['id'],$identity['provider']);if($response->getStatusCode()<400&&$r->session()->get('platform_admin')===true&&$r->session()->has('platform_support')&&$r->is('api/*')&&!$r->is('api/platform/support*')&&!$r->isMethod('GET')&&!$r->isMethod('HEAD')&&!$r->isMethod('OPTIONS'))SecurityAudit::platform($r,'support_write',app(\App\Services\Tenant::class)->id,'operation',mb_substr($r->method().' '.implode('/',array_slice(explode('/',$r->path()),0,2)),0,100));return $response;}
 private function identities(Request $r):array{$rows=[];if($r->session()->get('platform_admin')===true)$rows['platform']=['id'=>null,'provider'=>null];if($r->session()->get('admin')===true&&!$r->session()->has('platform_support')){$member=(int)$r->session()->get('provider_member_id',0);$rows[$member?'member':'provider']=['id'=>$member?:((int)$r->session()->get('provider_id',1)),'provider'=>(int)$r->session()->get('provider_id',1)];}if($r->session()->get('admin')!==true&&($id=(int)$r->session()->get('guest_id',0)))$rows['guest']=['id'=>$id,'provider'=>null];return $rows;}
}
