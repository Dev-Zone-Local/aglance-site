<?php

namespace App\Support\LicensePolicy;

use App\Models\FeatureCatalog;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation for default and custom licence policies. The limits are server-side and also
 * enforced by the licence format: the activation window can never exceed 3 hours.
 */
class PolicyRules
{
    public const MIN_WINDOW_MINUTES = 5;

    /** Hard cap: LICENSE_FORMAT.md allows at most 180 minutes. */
    public const MAX_WINDOW_MINUTES = 180;

    public const MAX_VALIDITY_MONTHS = 120;

    /** Highest licence limit an admin may set; users contact AtGlance Support to get more than their limit. */
    public const MAX_LICENCES = 1000;

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $custom = false): array
    {
        return [
            'max_licences' => ['required', 'integer', 'min:1', 'max:'.self::MAX_LICENCES],
            'max_nodes' => ['required', 'integer', 'min:0', 'max:1000000'],
            'max_users' => ['required', 'integer', 'min:0', 'max:1000000'],
            'validity_value' => [$custom ? 'required_without:expires_on' : 'required', 'nullable', 'integer', 'min:1', 'max:120'],
            'validity_unit' => ['required', Rule::in(['months', 'years'])],
            'activation_window_minutes' => ['required', 'integer', 'min:'.self::MIN_WINDOW_MINUTES, 'max:'.self::MAX_WINDOW_MINUTES],
            'install_id_mode' => ['required', Rule::in(['required', 'optional'])],
            // Bundles (e.g. premium = Premium Features) and individual premium grants. Free features
            // are always on and cannot be listed; a bundle key is never a feature key.
            'bundles' => ['present', 'array'],
            'bundles.*' => ['string', Rule::in(FeatureCatalog::bundleKeys())],
            // Default policies grant premium features only through bundles; custom policies may grant them one by one.
            'individual_features' => $custom ? ['present', 'array'] : ['present', 'array', 'max:0'],
            'individual_features.*' => ['string', Rule::in(FeatureCatalog::premiumKeys())],
        ] + ($custom ? [
            'expires_on' => ['nullable', 'date', 'after:today', 'before_or_equal:'.now()->addMonths(self::MAX_VALIDITY_MONTHS)->toDateString()],
            'install_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] : []);
    }

    /**
     * Validates a policy and checks the total validity (1..120 months).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function validate(array $data, bool $custom = false, string $prefix = ''): array
    {
        $validator = Validator::make($data, self::rules($custom), [
            'individual_features.max' => 'Default policies include premium features only through "Premium Features". Single premium features are granted with a custom license.',
        ]);
        $validator->after(function ($v) use ($data, $custom) {
            if ($custom && ! empty($data['expires_on'])) {
                return;
            }
            $months = (int) ($data['validity_value'] ?? 0) * (($data['validity_unit'] ?? 'months') === 'years' ? 12 : 1);
            if ($months < 1 || $months > self::MAX_VALIDITY_MONTHS) {
                $v->errors()->add('validity_value', 'The licence validity must be 1 to 120 months in total.');
            }
        });

        try {
            $validated = $validator->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($prefix === '') {
                throw $e;
            }
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[$prefix.$field] = $messages;
            }
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        // Known keys in catalog order, no duplicates. With a bundle, every member is included:
        // no separate grants are stored.
        $validated['bundles'] = FeatureCatalog::onlyBundles((array) ($validated['bundles'] ?? []));
        $validated['individual_features'] = $validated['bundles'] !== []
            ? []
            : FeatureCatalog::onlyPremium((array) ($validated['individual_features'] ?? []));

        return $validated;
    }

    /**
     * Form state to policy fields: the "Premium Features" toggle/checkbox (premium_bundle) becomes
     * bundles; the individual checkboxes become individual_features (none with the bundle).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function fromForm(array $input): array
    {
        $bundle = filter_var($input['premium_bundle'] ?? false, FILTER_VALIDATE_BOOL);
        unset($input['premium_bundle'], $input['premium_members']);

        return [
            'bundles' => $bundle ? [FeatureCatalog::BUNDLE_PREMIUM] : [],
            'individual_features' => $bundle ? [] : array_values((array) ($input['individual_features'] ?? [])),
        ] + $input;
    }

    /**
     * Policy fields to form state (see fromForm). With the bundle, every premium feature shows as
     * ticked (and disabled) in the custom form.
     *
     * @param  array<int, string>|null  $bundles
     * @param  array<int, string>|null  $features
     * @return array{premium_bundle: bool, individual_features: list<string>}
     */
    public static function toForm(?array $bundles, ?array $features): array
    {
        $bundle = in_array(FeatureCatalog::BUNDLE_PREMIUM, (array) $bundles, true);

        return [
            'premium_bundle' => $bundle,
            'individual_features' => $bundle ? FeatureCatalog::premiumKeys() : FeatureCatalog::onlyPremium((array) $features),
        ];
    }
}
