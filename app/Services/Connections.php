<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
final class Connections
{
    private const FIELDS = [
        "smtp_host",
        "smtp_port",
        "smtp_username",
        "smtp_password",
        "mail_from",
        "mail_name",
        "billingo_key",
        "billingo_block",
        "szamlazz_key",
        "stripe_key",
        "stripe_webhook_secret",
        "stripe_price_id",
        "twilio_sid",
        "twilio_token",
        "twilio_from",
        "google_client_id",
        "google_client_secret",
        "facebook_client_id",
        "facebook_client_secret",
        "admin_google_emails",
        "admin_facebook_ids",
    ];
    public function all(): array
    {
        $file = config("integrations.path");
        $saved = is_file($file)
            ? json_decode(
                Crypt::decryptString(file_get_contents($file)),
                true,
                512,
                JSON_THROW_ON_ERROR,
            )
            : [];
        $env = config("integrations.connections", []);
        return array_replace($env, $saved);
    }
    public function save(array $input): void
    {
        $path = config("integrations.path");
        $directory = dirname($path);
        if (
            !is_dir($directory) &&
            !mkdir($directory, 0700, true) &&
            !is_dir($directory)
        ) {
            throw new \RuntimeException("Nem írható a kapcsolatok könyvtára.");
        }
        $lock = fopen($path . ".lock", "c");
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException(
                "Nem zárolható a kapcsolati beállítás.",
            );
        }
        $temporary = null;
        try {
            $current = $this->all();
            foreach (self::FIELDS as $key) {
                if (isset($input[$key]) && $input[$key] !== "") {
                    $current[$key] = $input[$key];
                }
            }
            $temporary = tempnam($directory, "connection-");
            if ($temporary === false) {
                throw new \RuntimeException(
                    "Nem írható a kapcsolati beállítás.",
                );
            }
            chmod($temporary, 0600);
            if (
                file_put_contents(
                    $temporary,
                    Crypt::encryptString(
                        json_encode($current, JSON_THROW_ON_ERROR),
                    ),
                ) === false ||
                !rename($temporary, $path)
            ) {
                throw new \RuntimeException(
                    "Nem menthető a kapcsolati beállítás.",
                );
            }
        } finally {
            if ($temporary && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
    public function status(): array
    {
        $v = $this->all();
        $safe = [];
        foreach (
            [
                "smtp_host",
                "smtp_port",
                "smtp_username",
                "mail_from",
                "mail_name",
                "billingo_block",
                "stripe_price_id",
                "google_client_id",
                "facebook_client_id",
                "admin_google_emails",
                "admin_facebook_ids",
                "twilio_from",
            ]
            as $key
        ) {
            $safe[$key] = $v[$key] ?? "";
        }
        foreach (self::FIELDS as $key) {
            $safe[$key . "_configured"] = !empty($v[$key]);
        }
        return $safe;
    }
    public function mailReady(): bool
    {
        $c = $this->all();
        return !empty($c["smtp_host"]) && !empty($c["mail_from"]);
    }
}
