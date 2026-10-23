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
            'features' => ['present', 'array'],
            'features.*' => ['string', Rule::in(FeatureCatalog::keys())],
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
        $validator = Validator::make($data, self::rules($custom));
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

        $validated['features'] = array_values(array_unique($validated['features'] ?? []));

        return $validated;
    }
}
