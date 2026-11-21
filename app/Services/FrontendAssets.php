<?php
namespace App\Services;

/** A checked-in build works on shared hosting without a Node.js runtime. */
final class FrontendAssets
{
    public function manifest(): ?array
    {
        if (!config('frontend.bundled', true)) return null;
        $path = public_path('build/manifest.json');
        if (!is_file($path)) return null;
        $manifest = json_decode(file_get_contents($path), true);
        if (!is_array($manifest) || ($manifest['version'] ?? null) !== 1) return null;
        foreach (['script' => 'js', 'style' => 'css'] as $key => $extension) {
            $file = $manifest[$key] ?? '';
            if (!is_string($file) || !preg_match('/^build\/app\.[a-f0-9]{16}\.'.$extension.'$/D', $file) || !is_file(public_path($file))) return null;
        }
        return $manifest;
    }
}
