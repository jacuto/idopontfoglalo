<?php
namespace App\Services;
final class Settings
{
    public static function defaults(): array
    {
        return [
            "businessMode" => "team",
            "primaryStaffId" => "anna",
            "openingTime" => "08:00",
            "closingTime" => "20:00",
            "providerEmail" => "",
            "notificationVersion" => 1,
            "notifyGuest" => false,
            "notifyProvider" => false,
            "reminderGuest" => false,
            "billingName" => "",
            "billingTaxNumber" => "",
            "billingCountry" => "HU",
            "billingZip" => "",
            "billingCity" => "",
            "billingAddress" => "",
            "invoiceProvider" => "none",
            "autoInvoice" => false,
            "invoiceVat" => "AAM",
            "embedOrigins" => [],
            "googleAnalyticsId" => "",
            "googleTagManagerId" => "",
            "googleAdsId" => "",
            "googleAdsLabel" => "",
            "facebookPixelId" => "",
        ];
    }
    public static function upgrade(array $state): array
    {
        $old = $state["settings"] ?? [];
        $defaults = self::defaults();
        $defaults["openingTime"] = sprintf(
            "%02d:%02d",
            floor($old["open"] ?? 8),
            round(fmod((float) ($old["open"] ?? 8), 1) * 60),
        );
        $defaults["closingTime"] = sprintf(
            "%02d:%02d",
            floor($old["close"] ?? 20),
            round(fmod((float) ($old["close"] ?? 20), 1) * 60),
        );
        $defaults["primaryStaffId"] = $state["staff"][0]["id"] ?? "";
        $state["settings"] = array_replace($defaults, $old);
        $state += [
            "payments" => [],
            "checkouts" => [],
            "invoiceRequests" => [],
            "subscription" => [],
        ];
        return $state;
    }
    public static function minutes(string $time): int
    {
        [$h, $m] = array_map("intval", explode(":", $time));
        return $h * 60 + $m;
    }
    public static function activeStaff(array $state): array
    {
        $people = array_values(
            array_filter($state["staff"], fn($p) => empty($p["archived"])),
        );
        if (($state["settings"]["businessMode"] ?? "team") === "solo") {
            $people = array_values(
                array_filter(
                    $people,
                    fn($p) => $p["id"] === $state["settings"]["primaryStaffId"],
                ),
            );
        }
        return $people;
    }
}
