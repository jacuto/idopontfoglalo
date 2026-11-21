<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;

/** Separate encrypted staging cache so expired, unimported contacts can be pruned. */
final class ContactImportCache
{
    public static function store():\Illuminate\Contracts\Cache\Repository
    {
        if(config('cache.default')==='array')return Cache::store();
        return Cache::build(['driver'=>'file','path'=>storage_path('framework/cache/contact-import'),'lock_path'=>storage_path('framework/cache/contact-import-locks')]);
    }
    public static function prune():int
    {
        $directory=storage_path('framework/cache/contact-import');if(!is_dir($directory))return 0;$removed=0;
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory,\FilesystemIterator::SKIP_DOTS)) as $file){if(!$file->isFile()||$file->isLink())continue;$handle=fopen($file->getPathname(),'rb');if(!$handle)continue;$expires=fread($handle,10);fclose($handle);if(ctype_digit($expires)&&(int)$expires<time()&&unlink($file->getPathname()))++$removed;}
        return $removed;
    }
}
