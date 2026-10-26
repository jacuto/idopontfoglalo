<?php
namespace App\Services;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class BookingRules
{
    public function service(array $catalog, string $id): array
    {
        foreach ($catalog as $service) {
            if ($service["id"] === $id) {
                return $service;
            }
        }
        throw new InvalidArgumentException("Ismeretlen szolgáltatás.");
    }
    public function occupied(array $booking, array $state): array
    {
        $start = CarbonImmutable::parse($booking["start"])->getTimestamp();
        $ranges = [];
        foreach ($booking["services"] as $id) {
            $service = $this->service(
                $booking["serviceSnapshot"] ?? $state["services"],
                $id,
            );
            if (!empty($service["phases"])) {
                [$work, $wait, $finish] = $service["phases"];
                if ($work > 0) {
                    $ranges[] = [$start, $start + $work * 60];
                }
                if ($finish > 0) {
                    $ranges[] = [
                        $start + ($work + $wait) * 60,
                        $start + $service["duration"] * 60,
                    ];
                }
            } else {
                $ranges[] = [$start, $start + $service["duration"] * 60];
            }
            $start += $service["duration"] * 60;
        }
        return $ranges;
    }
    public function validate(
        array $booking,
        array $state,
        ?string $ignore = null,
        bool $historical = false,
    ): void {
        if (
            !trim($booking["name"] ?? "") ||
            (!trim($booking["phone"] ?? "") && !trim($booking["email"] ?? ""))
        ) {
            throw new InvalidArgumentException(
                "Név és legalább egy elérhetőség szükséges.",
            );
        }
        if (
            !empty($booking["email"]) &&
            !filter_var($booking["email"], FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException("Érvénytelen e-mail-cím.");
        }
        if (
            empty($booking["services"]) ||
            !is_array($booking["services"]) ||
            count($booking["services"]) > 20
        ) {
            throw new InvalidArgumentException("Válassz szolgáltatást.");
        }
        if (
            !in_array(
                $booking["staff"] ?? "",
                array_column(
                    $historical
                        ? $state["staff"]
                        : Settings::activeStaff($state),
                    "id",
                ),
                true,
            )
        ) {
            throw new InvalidArgumentException("Válassz munkatársat.");
        }
        $duration = 0;
        foreach ($booking["services"] as $id) {
            $service = $this->service($state["services"], $id);
            if (!empty($service["archived"]) && !$ignore && !$historical) {
                throw new InvalidArgumentException(
                    "Archivált szolgáltatás nem foglalható.",
                );
            }
            if (!in_array($booking["staff"], $service["staff"], true)) {
                throw new InvalidArgumentException(
                    "A munkatárs nem végzi az összes kiválasztott szolgáltatást.",
                );
            }
            $duration += $this->service(
                $booking["serviceSnapshot"] ?? $state["services"],
                $id,
            )["duration"];
        }
        if (
            empty($booking["start"]) ||
            !is_string($booking["start"]) ||
            !preg_match("/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/", $booking["start"])
        ) {
            throw new InvalidArgumentException("Érvénytelen időpont.");
        }
        try {
            $start = CarbonImmutable::parse($booking["start"])->setTimezone(
                "Europe/Budapest",
            );
        } catch (\Exception) {
            throw new InvalidArgumentException("Érvénytelen időpont.");
        }
        if (!$historical && $start->getTimestamp() < time() - 60) {
            throw new InvalidArgumentException(
                "Múltbeli időpontra nem lehet foglalni.",
            );
        }
        $minutes = $start->hour * 60 + $start->minute;
        if (
            $minutes <
                Settings::minutes(
                    $state["settings"]["openingTime"] ??
                        sprintf("%02d:00", $state["settings"]["open"] ?? 8),
                ) ||
            $minutes + $duration >
                Settings::minutes(
                    $state["settings"]["closingTime"] ??
                        sprintf("%02d:00", $state["settings"]["close"] ?? 20),
                )
        ) {
            throw new InvalidArgumentException(
                "Az időpont nem fér a nyitvatartásba.",
            );
        }
        $ranges = $this->occupied($booking, $state);
        foreach ($state["bookings"] as $old) {
            if (
                $old["id"] === $ignore ||
                $old["staff"] !== $booking["staff"] ||
                in_array($old["status"], ["cancelled", "rejected"], true)
            ) {
                continue;
            }
            foreach ($this->occupied($old, $state) as $a) {
                foreach ($ranges as $b) {
                    if ($a[0] < $b[1] && $b[0] < $a[1]) {
                        throw new InvalidArgumentException(
                            "Ez az időpont már foglalt. Válassz másikat.",
                        );
                    }
                }
            }
        }
    }
    public function status(array $services, array $state): string
    {
        if (!$state["settings"]["confirmation"]) {
            return "confirmed";
        }
        foreach ($services as $id) {
            if (empty($this->service($state["services"], $id)["automatic"])) {
                return "pending";
            }
        }
        return "confirmed";
    }
}
