<?php
// Only for a local PHP test server; never route a production server through this file.
$base=dirname(__DIR__,2);
if(getenv('APP_ENV')!=='testing'||getenv('RAERO_APPLE_BROWSER_TEST')!=='1'||getenv('DB_CONNECTION')!=='sqlite'||!str_starts_with((string)getenv('DB_DATABASE'),$base.'/work/')){http_response_code(404);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path!=='/'&&is_file($base.'/public'.$path))return false;
require $base.'/vendor/autoload.php';$app=require $base.'/bootstrap/app.php';$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$kernel->bootstrap();
$fixture=$base.'/work/apple-browser-fixture.json';
if(!is_file($fixture)){
 $rsa=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048]);openssl_pkey_export($rsa,$private);$details=openssl_pkey_get_details($rsa);
 $ec=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);openssl_pkey_export($ec,$clientPrivate);$b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
 file_put_contents($fixture,json_encode(['rsa'=>$private,'ec'=>$clientPrivate,'jwk'=>['keys'=>[['kid'=>'apple-browser','kty'=>'RSA','alg'=>'RS256','n'=>$b64($details['rsa']['n']),'e'=>$b64($details['rsa']['e'])]]]]));chmod($fixture,0600);
}
$f=json_decode(file_get_contents($fixture),true);
config(['app.url'=>'http://127.0.0.1:8089','integrations.path'=>$base.'/work/no-apple-connections.enc','integrations.connections.apple_client_id'=>'test.apple.browser','integrations.connections.apple_team_id'=>'TESTTEAM','integrations.connections.apple_key_id'=>'TESTKEY','integrations.connections.apple_private_key'=>$f['ec'],'cache.stores.file.path'=>$base.'/work/apple-browser-cache']);
Illuminate\Support\Facades\Http::preventStrayRequests();
Illuminate\Support\Facades\Http::fake(function($request)use($f){
 if(str_ends_with($request->url(),'/keys'))return Illuminate\Support\Facades\Http::response($f['jwk']);
 if(str_ends_with($request->url(),'/token'))return Illuminate\Support\Facades\Http::response(['id_token'=>Firebase\JWT\JWT::encode(['iss'=>'https://appleid.apple.com','aud'=>'test.apple.browser','sub'=>'apple-browser-person','nonce'=>substr($request['code'],5),'iat'=>time(),'exp'=>time()+300,'email'=>'apple-browser@example.invalid','email_verified'=>true],$f['rsa'],'RS256','apple-browser')]);
 throw new RuntimeException('Unexpected test HTTP request.');
});
$request=Illuminate\Http\Request::capture();$response=$kernel->handle($request);$response->send();$kernel->terminate($request,$response);
