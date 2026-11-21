<?php
namespace Tests;
use App\Services\FrontendAssets;
class FrontendAssetsTest extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication(){$app=require __DIR__.'/../bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();return $app;}
    public function test_built_manifest_resolves_to_existing_content_addressed_files():void
    {
        config(['frontend.bundled'=>true]);$manifest=app(FrontendAssets::class)->manifest();
        $this->assertIsArray($manifest);
        foreach(['script','style'] as $key){$this->assertFileExists(public_path($manifest[$key]));$this->assertStringContainsString(substr(hash_file('sha256',public_path($manifest[$key])),0,16),$manifest[$key]);}
    }
    public function test_source_mode_is_available_without_changing_application_functions():void
    {
        config(['frontend.bundled'=>false]);$this->assertNull(app(FrontendAssets::class)->manifest());
    }
    public function test_missing_or_invalid_build_falls_back_to_sources():void
    {
        config(['frontend.bundled'=>true]);$dir=sys_get_temp_dir().'/raero-assets-'.bin2hex(random_bytes(8));mkdir($dir.'/build',0700,true);$old=public_path();app()->usePublicPath($dir);
        try{
            $this->assertNull(app(FrontendAssets::class)->manifest());
            file_put_contents($dir.'/build/manifest.json','{"version":1,"script":"../private.js","style":"build/app.0123456789abcdef.css"}');
            $this->assertNull(app(FrontendAssets::class)->manifest());
            file_put_contents($dir.'/build/manifest.json','broken json');
            $this->assertNull(app(FrontendAssets::class)->manifest());
        }finally{app()->usePublicPath($old);@unlink($dir.'/build/manifest.json');rmdir($dir.'/build');rmdir($dir);}
    }
}
