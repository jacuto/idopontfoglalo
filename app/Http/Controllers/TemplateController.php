<?php
namespace App\Http\Controllers;
use App\Services\{MailTemplates,SafeHtml};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class TemplateController {
 public function handle(Request $r){abort_unless($r->session()->get('platform_admin')===true,401);if($r->isMethod('GET'))return response()->json(app(MailTemplates::class)->all());
  $v=$r->validate(['event'=>'required|in:'.implode(',',array_keys(MailTemplates::EVENTS)),'subject'=>'required|string|max:250','html'=>'required|string|max:50000','enabled'=>'required|boolean']);$v['html']=SafeHtml::clean($v['html'],true);if(str_ends_with($r->path(),'/preview'))return response()->json(MailTemplates::renderTemplate($v,['business'=>'Minta Szalon','name'=>'Minta Vendég','details'=>'Foglalás: 2035. március 12. 10:00 – Szolgáltatás','manage_url'=>rtrim(config('app.url'),'/').'/fiokom','account_url'=>rtrim(config('app.url'),'/').'/fiokom','provider_url'=>rtrim(config('app.url'),'/').'/foglalas']));$v['updated_at']=now();$v['created_at']=now();DB::table('mail_templates')->updateOrInsert(['event'=>$v['event']],$v);return response()->json(['ok'=>true]);
 }
}
