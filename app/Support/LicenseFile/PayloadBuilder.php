<?php

namespace App\Support\LicenseFile;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Builds the licence payload (LICENSE_FORMAT.md, "Payload") and its exact JSON bytes.
 * No framework code, so the cross-repo end-to-end check can run it directly.
 */
final class PayloadBuilder
{
    public const PRODUCT = 'atglance-console';

    public const ACTIVATION_WINDOW_SECONDS = 10800;

    /**
     * @param  list<string>  $features
     * @return array<string, mixed>
     */
    public static function build(
        string $licenseId,
        string $keyId,
        string $customer,
        string $email,
        string $plan,
        int $seats,
        array $features,
        int $validDays,
        string $minVersion,
        ?string $installId,
        DateTimeInterface $now,
    ): array {
        // Whole seconds in UTC: the format has no fractions.
        $issued = (new DateTimeImmutable('@'.$now->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));

        return [
            'format_version' => 1,
            'license_id' => $licenseId,
            'product' => self::PRODUCT,
            'key_id' => $keyId,
            'customer' => mb_substr($customer, 0, 255),
            'email' => $email,
            'plan' => $plan,
            'seats' => max(1, $seats),
            'features' => array_values(array_map('strval', $features)),
            'issued_at' => self::date($issued),
            'activate_before' => self::date($issued->modify('+'.self::ACTIVATION_WINDOW_SECONDS.' seconds')),
            'expires_at' => self::date($issued->modify('+'.max(1, $validDays).' days')),
            'min_version' => $minVersion,
            'install_id' => $installId === null || $installId === '' ? null : strtolower($installId),
        ];
    }

    /**
     * Payload version 2 (LICENSE_FORMAT.md): limits, policy and features; no seats.
     *
     * @param  list<string>  $features  catalog keys
     * @param  \DateTimeInterface|null  $expiresAt  explicit expiry (custom policy), else issue time + $validityMonths
     * @return array<string, mixed>
     */
    public static function buildV2(
        string $licenseId,
        string $keyId,
        string $customer,
        string $email,
        string $plan,
        array $features,
        int $maxNodes,
        int $maxUsers,
        string $policyId,
        string $policyType,
        int $validityMonths,
        int $activationWindowMinutes,
        string $minVersion,
        ?string $installId,
        DateTimeInterface $now,
        ?DateTimeInterface $expiresAt = null,
    ): array {
        $issued = (new DateTimeImmutable('@'.$now->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));
        $window = max(5, min(180, $activationWindowMinutes)) * 60;
        $expires = $expiresAt !== null
            ? (new DateTimeImmutable('@'.$expiresAt->getTimestamp()))->setTimezone(new DateTimeZone('UTC'))
            : self::addMonths($issued, $validityMonths);

        return [
            'format_version' => 2,
            'license_id' => $licenseId,
            'product' => self::PRODUCT,
            'key_id' => $keyId,
            'customer' => mb_substr($customer, 0, 255),
            'email' => $email,
            'plan' => $plan,
            'features' => array_values(array_unique(array_map('strval', $features))),
            'issued_at' => self::date($issued),
            'activate_before' => self::date($issued->modify('+'.$window.' seconds')),
            'expires_at' => self::date($expires),
            'min_version' => $minVersion,
            'install_id' => $installId === null || $installId === '' ? null : strtolower($installId),
            'max_nodes' => max(0, $maxNodes),
            'max_users' => max(0, $maxUsers),
            'policy_id' => $policyId,
            'policy_type' => $policyType,
            'validity_months' => max(1, min(120, $validityMonths)),
        ];
    }

    /** $months calendar months later; the day is clamped to the month's last day (Jan 31 + 1 = Feb 28/29). */
    public static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $first = $date->modify('first day of this month')->modify("+{$months} months");
        $day = min((int) $date->format('j'), (int) $first->format('t'));

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day);
    }

    /** The payload bytes that are signed. */
    public static function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function date(DateTimeInterface $date): string
    {
        return DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
