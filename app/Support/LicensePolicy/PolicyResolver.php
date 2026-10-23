<?php

namespace App\Support\LicensePolicy;

use App\Models\CustomLicensePolicy;
use App\Models\LicensePolicy;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The policy a user's next licence file is issued with, in this order:
 * the user's custom policy, then their plan's default, then the global default.
 * Only stored policies are used; nothing comes from the request.
 */
class PolicyResolver
{
    /**
     * @return array{policy_id: string, policy_type: string, label: string, max_nodes: int, max_users: int,
     *     validity_months: int, expires_on: ?Carbon, activation_window_minutes: int, features: list<string>,
     *     install_id: ?string, custom: ?CustomLicensePolicy}
     */
    public static function for(User $user): array
    {
        $custom = CustomLicensePolicy::query()->active()->where('user_id', $user->id)->latest('id')->first();
        if ($custom) {
            return [
                'policy_id' => $custom->policyId(),
                'policy_type' => 'custom',
                'label' => 'Custom',
                'max_nodes' => $custom->max_nodes,
                'max_users' => $custom->max_users,
                'validity_months' => $custom->expires_on
                    ? self::monthsUntil($custom->expires_on)
                    : $custom->validity_value * ($custom->validity_unit === 'years' ? 12 : 1),
                'expires_on' => $custom->expires_on ? Carbon::parse($custom->expires_on->toDateString(), 'UTC') : null,
                'activation_window_minutes' => $custom->activation_window_minutes,
                'features' => array_values((array) $custom->features),
                'install_id' => $custom->install_id,
                'custom' => $custom,
            ];
        }

        $plan = LicensePolicy::forPlan((string) $user->plan);
        $policy = $plan && ! $plan->inherit_global ? $plan : LicensePolicy::global();

        return [
            'policy_id' => $policy->scope,
            'policy_type' => 'default',
            'label' => 'Standard',
            'max_nodes' => $policy->max_nodes,
            'max_users' => $policy->max_users,
            'validity_months' => $policy->validityMonths(),
            'expires_on' => null,
            'activation_window_minutes' => $policy->activation_window_minutes,
            'features' => array_values((array) $policy->features),
            'install_id' => null,
            'custom' => null,
        ];
    }

    /** Whole months from today until $date, at least 1 and at most 120 (informational). */
    public static function monthsUntil(\DateTimeInterface $date): int
    {
        $months = (int) ceil(now()->floatDiffInMonths(Carbon::instance($date), false));

        return max(1, min(PolicyRules::MAX_VALIDITY_MONTHS, $months));
    }
}
