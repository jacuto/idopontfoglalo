<?php
namespace App\Services;

/** Event/channel choices; old accounts retain their existing opt-ins until edited. */
final class NotificationPreferences
{
    public const EVENTS = ['created', 'confirmed', 'updated', 'cancelled', 'group_missed', 'no_show'];

    public static function choices(array $settings): array
    {
        if (isset($settings['notificationRules'])) return $settings['notificationRules'];
        $rules = ['guest' => [], 'provider' => []];
        foreach (self::EVENTS as $event) {
            $rules['guest'][$event] = [
                'email' => !empty($settings['notifyGuest']) && in_array($settings['channel'] ?? 'email', ['email', 'mindkettő'], true),
                'sms' => !empty($settings['notifyGuest']) && in_array($settings['channel'] ?? 'email', ['sms', 'mindkettő'], true),
            ];
            $rules['provider'][$event] = ['email' => !empty($settings['notifyProvider']), 'sms' => false];
        }
        $rules['guest']['reminder'] = [
            'email' => !empty($settings['reminderGuest']) && in_array($settings['channel'] ?? 'email', ['email', 'mindkettő'], true),
            'sms' => !empty($settings['reminderGuest']) && in_array($settings['channel'] ?? 'email', ['sms', 'mindkettő'], true),
        ];
        return $rules;
    }

    public static function enabled(array $settings, string $audience, string $event, string $channel, array $booking = []): bool
    {
        // Preserve the legacy safety notice, without turning on new paid channels.
        if (!isset($settings['notificationRules']) && $event === 'group_missed' && $audience === 'guest') {
            return $channel === 'email' || empty($booking['email']);
        }
        return !empty(self::choices($settings)[$audience][$event][$channel]);
    }

    public static function event(string $event, array $booking): string
    {
        return $event === 'updated' && ($booking['status'] ?? '') === 'no_show' ? 'no_show' : $event;
    }
}
