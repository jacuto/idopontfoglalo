<?php
namespace App\Services;

use Illuminate\Support\Facades\{DB, Http, Log, Schema, Storage};
use Illuminate\Support\Str;

final class SocialAvatar
{
    public function import(int $accountId, string $provider, mixed $url): void
    {
        if (!is_string($url) || !$this->allowed($url, $provider)) return;
        try {
            if (!Schema::hasColumn('guest_accounts', 'avatar')) return;
            $account = DB::table('guest_accounts')->find($accountId);
            if (!$account || !empty($account->avatar)) return;
            $response = Http::connectTimeout(3)->timeout(8)->withOptions([
                'allow_redirects' => false,
                'on_headers' => function ($response) {
                    if ((int) $response->getHeaderLine('Content-Length') > 2097152) throw new \RuntimeException('Túl nagy kép.');
                },
                'progress' => function ($total, $downloaded) {
                    if ($total > 2097152 || $downloaded > 2097152) throw new \RuntimeException('Túl nagy kép.');
                },
            ])->get($url);
            if (!$response->successful()) return;
            $bytes = $response->body();
            if (strlen($bytes) > 2097152) return;
            $image = @getimagesizefromstring($bytes);
            $extension = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$image[2] ?? 0] ?? null;
            if (!$extension || $image[0] > 4096 || $image[1] > 4096) return;
            $path = 'avatars/' . Str::uuid() . '.' . $extension;
            if (!Storage::disk('local')->put($path, $bytes)) return;
            try {
                // A concurrently uploaded own picture always takes precedence.
                $saved = DB::table('guest_accounts')->where('id', $accountId)
                    ->where(fn($q) => $q->whereNull('avatar')->orWhere('avatar', ''))
                    ->update(['avatar' => $path, 'updated_at' => now()]);
                if (!$saved) Storage::disk('local')->delete($path);
            } catch (\Throwable $e) {
                Storage::disk('local')->delete($path);
                throw $e;
            }
        } catch (\Throwable) {
            // A missing picture must never prevent login. Do not log signed image URLs or tokens.
            Log::notice('A közösségi profilkép átvétele nem sikerült.', ['provider' => $provider]);
        }
    }

    private function allowed(string $url, string $provider): bool
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) return false;
        $host = strtolower($parts['host'] ?? '');
        $domains = match ($provider) { 'google' => ['googleusercontent.com'], 'facebook' => ['fbcdn.net', 'fbsbx.com'], default => [] };
        foreach ($domains as $domain) if ($host === $domain || str_ends_with($host, '.' . $domain)) return true;
        return false;
    }
}
