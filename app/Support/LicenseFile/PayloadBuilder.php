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
