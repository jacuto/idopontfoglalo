<?php
namespace App\Http\Controllers;
use App\Services\{Connections,Tenant};
use Firebase\JWT\{JWT,JWK};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,DB,Http,RateLimiter};
use Illuminate\Support\Str;

final class AppleAuthController
{
    public static function ready(array $c):bool {foreach(['apple_client_id','apple_team_id','apple_key_id','apple_private_key'] as $key)if(empty($c[$key]))return false;return true;}
    public function start(Request $r){
        $c=app(Connections::class)->all();abort_unless(self::ready($c),503,'Az Apple-belépés még nincs beállítva.');
        $state=bin2hex(random_bytes(32));$nonce=bin2hex(random_bytes(32));
        if($r->query('mode')==='provider-link')abort_unless($r->session()->get('admin')===true&&(int)$r->session()->get('provider_id',1)===app(Tenant::class)->id,403);
        $r->session()->put('apple_oauth',['state'=>$state,'nonce'=>$nonce,'tenant'=>app(Tenant::class)->id,'mode'=>in_array($r->query('mode'),['admin','provider','provider-link'],true)?$r->query('mode'):'guest','returnTo'=>in_array($r->query('returnTo'),['booking','messages','operatorMessages'],true)?$r->query('returnTo'):null,'expires'=>time()+600]);
        return redirect()->away('https://appleid.apple.com/auth/authorize?'.http_build_query(['client_id'=>$c['apple_client_id'],'redirect_uri'=>rtrim(config('app.url'),'/').'/auth/apple/callback','response_type'=>'code','response_mode'=>'form_post','scope'=>'name email','state'=>$state,'nonce'=>$nonce]));
    }
    public function callback(Request $r){
        // Apple posts cross-site. A one-use relay restores the Lax session cookie on the same-site GET.
        if($r->isMethod('POST')){
            abort_if(strlen($r->getContent())>20000,413);abort_if(RateLimiter::tooManyAttempts('apple-relay:'.$r->ip(),30),429);RateLimiter::hit('apple-relay:'.$r->ip(),60);
            $validator=validator($r->all(),['state'=>'required|string|max:256','code'=>'nullable|string|max:4096','user'=>'nullable|string|max:12000','error'=>'nullable|string|max:200']);abort_unless($validator->passes(),422,'Érvénytelen Apple-válasz.');$v=$validator->validated();
            $relay=Str::random(64);Cache::put('apple-relay:'.$relay,$v,120);
            return redirect('/auth/apple/callback?relay='.$relay,303)->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
        }
        $relay=$r->query('relay');$input=is_string($relay)&&preg_match('/^[A-Za-z0-9]{64}$/',$relay)?Cache::pull('apple-relay:'.$relay):null;
        $saved=$r->session()->pull('apple_oauth');
        if(!$input||!$saved||$saved['expires']<time()||!hash_equals($saved['state'],(string)($input['state']??''))||empty($input['code']))return redirect('/fiokom?authError=state');
        app(Tenant::class)->id=(int)$saved['tenant'];$c=app(Connections::class)->all();
        try{
            $secret=JWT::encode(['iss'=>$c['apple_team_id'],'iat'=>time(),'exp'=>time()+300,'aud'=>'https://appleid.apple.com','sub'=>$c['apple_client_id']],str_replace('\\n',"\n",$c['apple_private_key']),'ES256',$c['apple_key_id']);
            $token=Http::timeout(15)->asForm()->post('https://appleid.apple.com/auth/token',['client_id'=>$c['apple_client_id'],'client_secret'=>$secret,'code'=>$input['code'],'grant_type'=>'authorization_code','redirect_uri'=>rtrim(config('app.url'),'/').'/auth/apple/callback'])->throw()->json();
            $keys=Cache::remember('apple-signing-keys',3600,fn()=>Http::timeout(10)->get('https://appleid.apple.com/auth/keys')->throw()->json());
            $header=JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.',$token['id_token'])[0]));
            if(($header->alg??'')!=='RS256')throw new \RuntimeException('Invalid Apple algorithm.');
            $parsed=JWK::parseKeySet($keys,'RS256');
            if(!isset($parsed[$header->kid??''])){$keys=Http::timeout(10)->get('https://appleid.apple.com/auth/keys')->throw()->json();Cache::put('apple-signing-keys',$keys,3600);$parsed=JWK::parseKeySet($keys,'RS256');}
            $claims=JWT::decode($token['id_token'],$parsed);
            if(($claims->iss??'')!=='https://appleid.apple.com'||($claims->aud??'')!==$c['apple_client_id']||!is_string($claims->sub??null)||$claims->sub===''||!isset($claims->nonce)||!hash_equals($saved['nonce'],(string)$claims->nonce)||!is_int($claims->exp??null)||$claims->exp<time())throw new \RuntimeException('Invalid Apple identity.');
            if(in_array($saved['mode'],['provider','provider-link'],true))return \App\Services\ProviderIdentity::complete($r,'apple',$claims->sub,filter_var($claims->email??'',FILTER_VALIDATE_EMAIL)?$claims->email:null,'',$saved['mode'],(int)$saved['tenant']);
            if($saved['mode']==='admin'){
                if($saved['tenant']!==1||!in_array($claims->sub,array_map('trim',explode(',',$c['admin_apple_ids']??'')),true))return redirect('/?authError=denied');
                \App\Services\ProviderIdentity::signIn($r,(int)$saved['tenant']);return redirect($saved['tenant']===1?'/':'/?provider='.$saved['tenant']);
            }
            $account=DB::table('guest_accounts')->where('provider','apple')->where('subject',$claims->sub)->first();
            $user=json_decode($input['user']??'{}',true);$name=trim(($user['name']['firstName']??'').' '.($user['name']['lastName']??''));
            $values=['name'=>mb_substr($name?:($account->name??'Vendég'),0,150),'email'=>filter_var($claims->email??'',FILTER_VALIDATE_EMAIL)?$claims->email:($account->email??null),'email_verified'=>isset($claims->email_verified)?in_array($claims->email_verified,[true,'true'],true):(bool)($account->email_verified??false),'updated_at'=>now()];
            if(!$account){DB::table('guest_accounts')->insertOrIgnore(array_merge($values,['provider'=>'apple','subject'=>$claims->sub,'created_at'=>now()]));$account=DB::table('guest_accounts')->where('provider','apple')->where('subject',$claims->sub)->first();}else DB::table('guest_accounts')->where('id',$account->id)->update($values);
            $r->session()->regenerate();$r->session()->forget(['admin','platform_admin','guest_password_account','guest_password_version']);$r->session()->put('guest_id',$account->id);
            $path=match($saved['returnTo']){'booking'=>'/foglalas?provider='.$saved['tenant'].'#public-form','messages'=>'/uzenetek?provider='.$saved['tenant'],'operatorMessages'=>'/uzenetek?provider='.$saved['tenant'].'&platformMessages=1',default=>'/fiokom?provider='.$saved['tenant']};
            return redirect($path);
        }catch(\Throwable){$target=in_array($saved['mode'],['provider','provider-link'],true)?'/belepes':($saved['mode']==='admin'?'/':'/fiokom');return redirect($target.'?authError=provider'.($saved['tenant']!==1?'&provider='.$saved['tenant']:'').($saved['returnTo']?'&returnTo='.$saved['returnTo']:''));}
    }
}
