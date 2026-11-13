<?php

namespace App\Support\LicensePolicy;

use App\Models\FeatureCatalog;

/**
 * The three kinds of licence. Features follow the kind, never a plan's policy form:
 *
 *   free     Free plan. Every free feature, now and later. No premium features.
 *   premium  Premium plans (Enterprise). The Premium Features bundle: every premium feature, now
 *            and later, plus every free feature.
 *   custom   A user's custom licence (Users → Create custom license). Premium features are picked
 *            there (the bundle or single features). Free features are always included.
 *
 * Free features are never written into a licence: the console always turns them on, so features
 * added to the free tier later are included in every licence automatically. Limits (licences,
 * nodes, users, validity) still come from the plan's licence policy.
 */
class LicenseCategory
{
    public const FREE = 'free';

    public const PREMIUM = 'premium';

    public const CUSTOM = 'custom';

    public const LABELS = [self::FREE => 'Free', self::PREMIUM => 'Premium', self::CUSTOM => 'Custom'];

    /** The category of a plan (config atglance.plans.<code>.tier). Unknown plans are free. */
    public static function forPlan(?string $plan): string
    {
        return config("atglance.plans.{$plan}.tier") === self::PREMIUM ? self::PREMIUM : self::FREE;
    }

    public static function label(string $category): string
    {
        return self::LABELS[$category] ?? 'Free';
    }

    /**
     * Bundles and single grants of a plan category (custom licences store their own).
     *
     * @return array{bundles: list<string>, features: list<string>}
     */
    public static function grants(string $category): array
    {
        return $category === self::PREMIUM
            ? ['bundles' => [FeatureCatalog::BUNDLE_PREMIUM], 'features' => []]
            : ['bundles' => [], 'features' => []];
    }

    /** One line for the admin panel. */
    public static function describe(string $category): string
    {
        return match ($category) {
            self::PREMIUM => 'Premium licence: every premium feature, now and later, plus every free feature. '.self::bundleNames(),
            self::CUSTOM => 'Custom licence: free features always, premium features as chosen for this user.',
            default => 'Free licence: every free feature, now and later. No premium features.',
        };
    }

    private static function bundleNames(): string
    {
        $names = array_values(FeatureCatalog::bundleMembers(FeatureCatalog::BUNDLE_PREMIUM));

        return $names === [] ? '' : 'Today: '.implode(', ', $names).'.';
    }
}
