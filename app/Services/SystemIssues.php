<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Cache,Schema};
/** Bounded, aggregated diagnostics. Never persist request bodies, tokens, URLs or exception messages. */
final class SystemIssues {
 public static function record(string $category,string $code,string $message,?int $provider=null,?int $status=null):void {
  try{$fingerprint=hash('sha256',$category.':'.$code.':'.$provider.':'.$status);if(!Cache::add('issue:'.$fingerprint,true,60))return;if(!Schema::hasTable('system_issues'))return;
   DB::transaction(function()use($category,$code,$message,$provider,$status,$fingerprint){$row=DB::table('system_issues')->where('fingerprint',$fingerprint)->lockForUpdate()->first();if($row)DB::table('system_issues')->where('id',$row->id)->update(['occurrences'=>$row->occurrences+1,'last_seen_at'=>now(),'resolved'=>false,'message'=>$message]);else DB::table('system_issues')->insertOrIgnore(['fingerprint'=>$fingerprint,'category'=>$category,'provider_id'=>$provider,'code'=>$code,'message'=>mb_substr($message,0,500),'http_status'=>$status,'first_seen_at'=>now(),'last_seen_at'=>now()]);});
  }catch(\Throwable){} // A diagnostic failure must never interrupt booking or payments.
 }
 public static function capture(\Throwable $e,string $category='application',?int $provider=null):void {
  $status=$e instanceof \Illuminate\Http\Client\RequestException?$e->response->status():null;$code=class_basename($e);$hint='A művelet nem fejeződött be. Az ismétlődő hibát az üzemeltető vizsgálja.';
  if($category==='google_calendar'){$reason=$e instanceof \Illuminate\Http\Client\RequestException?$e->response->json('error.errors.0.reason'):null;$hint=match(true){$reason==='accessNotConfigured'||$reason==='serviceDisabled'=>'A Google Calendar API nincs engedélyezve a Google Cloud projektben.',$status===401=>'A Google-hozzáférés lejárt vagy visszavonták. Csatlakoztasd újra a naptárt.',$status===403=>'A Google nem engedélyezte a naptárműveletet. Ellenőrizd a Calendar API-t és a naptárengedélyeket.',$status===404=>'A kiválasztott Google-naptár nem érhető el. Ellenőrizd a naptárazonosítót.',$status===429=>'A Google kéréskorlátja betelt; a rendszer később újrapróbálja.',default=>'A Google Naptár kapcsolata vagy frissítése hibára futott.'};}
  self::record($category,$code,$hint,$provider,$status);
 }
 public static function prune():void {try{DB::table('system_issues')->where('last_seen_at','<',now()->subDays(30))->delete();$keep=DB::table('system_issues')->orderByDesc('last_seen_at')->limit(1000)->pluck('id');if($keep->isNotEmpty())DB::table('system_issues')->whereNotIn('id',$keep)->delete();}catch(\Throwable){}}
}
