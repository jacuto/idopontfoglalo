<?php
namespace App\Services;
use Illuminate\Http\Request;

/** Shared provider-admin guard. OAuth callbacks can opt out of the current URL's tenant. */
final class WorkspaceAccess
{
 public static function admin(Request $request,bool $scoped=false):int
 {
  abort_unless($request->session()->get('admin')===true&&!StaffAccess::editor($request),403);
  $provider=(int)$request->session()->get('provider_id',1);
  if($scoped)abort_unless($provider===app(Tenant::class)->id,403);
  return $provider;
 }
}
