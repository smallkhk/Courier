<?php

namespace App\Services;

use App\Models\Shipment;
use App\Support\Countries;
use App\Support\Phone;
use App\Support\Regions;
use App\Support\Units;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validation + normalisation of booking details, shared by the web booking form,
 * the JSON API and the bulk CSV import:
 *  - addresses anywhere in the world (ISO country, region, postal code, optional geo point)
 *  - phones validated for the address country and stored in E.164
 *  - weights/dimensions accepted in lb/in or kg/cm, stored in kg/cm
 *  - coverage zones resolved from the address (customers never choose a zone)
 *  - customs details required for cross-border shipments
 */
class ShipmentDetails
{
    public function __construct(private ZoneResolver $zones) {}

    public static function addressRules(string $side, bool $withContact = true): array
    {
        $rules = [
            "{$side}_address" => 'required|string|max:250',
            "{$side}_address2" => 'nullable|string|max:120',
            "{$side}_city" => 'required|string|max:100',
            "{$side}_region" => 'nullable|string|max:100',
            "{$side}_postal_code" => 'nullable|string|max:20',
            "{$side}_country" => ['required', 'string', 'size:2', function ($attr, $v, $fail) {
                if (! Countries::valid($v)) {
                    $fail('Choose a valid country.');
                }
            }],
            "{$side}_lat" => 'nullable|numeric|between:-90,90',
            "{$side}_lng" => 'nullable|numeric|between:-180,180',
            "{$side}_place_id" => 'nullable|string|max:255',
        ];

        return $rules;
    }

    public static function rules(): array
    {
        return self::addressRules('pickup') + self::addressRules('delivery') + [
            'sender_name' => 'required|string|max:120',
            'sender_phone' => 'required|string|max:32',
            'sender_email' => 'required|email:rfc|max:190',
            'pickup_instructions' => 'nullable|string|max:500',
            'recipient_name' => 'required|string|max:120',
            'recipient_phone' => 'required|string|max:32',
            'recipient_email' => 'nullable|email:rfc|max:190',
            'delivery_instructions' => 'nullable|string|max:500',
            'package_description' => 'required|string|max:200',
            'package_category' => ['required', Rule::in(array_keys(Shipment::CATEGORIES))],
            'units' => 'nullable|in:imperial,metric',
            'parcels' => 'required|array|min:1|max:20',
            'parcels.*.weight' => 'required|numeric|min:0.01|max:5000',
            'parcels.*.length' => 'nullable|numeric|min:0.1|max:1000',
            'parcels.*.width' => 'nullable|numeric|min:0.1|max:1000',
            'parcels.*.height' => 'nullable|numeric|min:0.1|max:1000',
            'service_id' => 'required|integer|exists:services,id',
            'declared_value' => 'nullable|numeric|min:0|max:100000000',
            'insured' => 'nullable|boolean',
            'special_handling' => 'nullable|array',
            'special_handling.*' => Rule::in(array_keys(Shipment::HANDLING)),
            'pickup_requested' => 'nullable|boolean',
            'pickup_date' => 'nullable|required_if:pickup_requested,1|date|after_or_equal:today|before:+30 days',
            'pickup_window' => ['nullable', Rule::in(array_keys(Shipment::PICKUP_WINDOWS))],
            'customs_contents_type' => ['nullable', Rule::in(array_keys(Shipment::CUSTOMS_CONTENTS))],
            'customs_description' => 'nullable|string|max:300',
            'customs_hs_code' => ['nullable', 'string', 'max:14', 'regex:/^[0-9.]{4,14}$/'],
        ];
    }

    /**
     * Normalise validated input. Adds origin_zone_id / destination_zone_id and a
     * kg/cm `parcels` array. Throws ValidationException with field-level messages.
     */
    public function normalize(array $d): array
    {
        $errors = [];
        foreach (['pickup', 'delivery'] as $side) {
            $c = strtoupper($d["{$side}_country"]);
            $d["{$side}_country"] = $c;
            $d["{$side}_region"] = Regions::normalize($c, $d["{$side}_region"] ?? null);
            $postal = strtoupper(trim((string) ($d["{$side}_postal_code"] ?? '')));
            $d["{$side}_postal_code"] = $postal !== '' ? $postal : null;
            if (Regions::postalRequired($c) && ! $d["{$side}_postal_code"]) {
                $errors["{$side}_postal_code"] = 'A ZIP / postal code is required for '.Countries::name($c).'.';
            } elseif ($d["{$side}_postal_code"] && ! Regions::postalValid($c, $d["{$side}_postal_code"])) {
                $errors["{$side}_postal_code"] = 'That ZIP / postal code doesn\'t look right for '.Countries::name($c).'.';
            }
            if (Regions::for($c) && ! $d["{$side}_region"]) {
                $errors["{$side}_region"] = 'Choose a state / province.';
            }
        }

        foreach (['sender_phone' => 'pickup_country', 'recipient_phone' => 'delivery_country'] as $field => $countryField) {
            $e164 = Phone::toE164($d[$field] ?? null, $d[$countryField]);
            if (! $e164) {
                $errors[$field] = 'Enter a valid phone number (include the country code for numbers outside '.Countries::name($d[$countryField]).').';
            } else {
                $d[$field] = $e164;
            }
        }

        $system = ($d['units'] ?? null) ?: Units::system();
        $d['units'] = $system;
        $d['parcels'] = array_values(array_map(fn ($p) => array_filter([
            'weight_kg' => Units::toKg($p['weight'] ?? $p['weight_kg'] ?? null, isset($p['weight_kg']) ? 'metric' : $system),
            'length_cm' => Units::toCm($p['length'] ?? $p['length_cm'] ?? null, isset($p['length_cm']) ? 'metric' : $system),
            'width_cm' => Units::toCm($p['width'] ?? $p['width_cm'] ?? null, isset($p['width_cm']) ? 'metric' : $system),
            'height_cm' => Units::toCm($p['height'] ?? $p['height_cm'] ?? null, isset($p['height_cm']) ? 'metric' : $system),
        ], fn ($v) => $v !== null), $d['parcels']));

        // Cross-border shipments need a customs declaration.
        if ($d['pickup_country'] !== $d['delivery_country']) {
            if (empty($d['customs_contents_type'])) {
                $errors['customs_contents_type'] = 'International shipments need a customs contents type.';
            }
            if (empty($d['customs_description']) || mb_strlen($d['customs_description']) < 5) {
                $errors['customs_description'] = 'Describe the contents in detail for customs (e.g. "2 cotton T-shirts").';
            }
            if (($d['customs_contents_type'] ?? '') !== 'documents' && (float) ($d['declared_value'] ?? 0) <= 0) {
                $errors['declared_value'] = 'Enter the value of the contents — customs requires it for international shipments.';
            }
        }

        if (! $errors) {
            $origin = $this->zones->resolve($d['pickup_country'], $d['pickup_region'], $d['pickup_postal_code'], $d['pickup_city']);
            $dest = $this->zones->resolve($d['delivery_country'], $d['delivery_region'], $d['delivery_postal_code'], $d['delivery_city']);
            if (! $origin) {
                $errors['pickup_address'] = 'Sorry — we don\'t pick up from '.$this->area($d, 'pickup').' yet.';
            }
            if (! $dest) {
                $errors['delivery_address'] = 'Sorry — we don\'t deliver to '.$this->area($d, 'delivery').' yet.';
            }
            $d['origin_zone_id'] = $origin?->id;
            $d['destination_zone_id'] = $dest?->id;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $d;
    }

    /** Customs payload stored on the shipment (null for domestic). */
    public static function customs(array $d, string $currency): ?array
    {
        if ($d['pickup_country'] === $d['delivery_country']) {
            return null;
        }

        return [
            'contents_type' => $d['customs_contents_type'],
            'description' => $d['customs_description'],
            'hs_code' => $d['customs_hs_code'] ?? null,
            'value' => (string) ($d['declared_value'] ?? '0'),
            'currency' => $currency,
            'origin_country' => $d['pickup_country'],
            'destination_country' => $d['delivery_country'],
        ];
    }

    private function area(array $d, string $side): string
    {
        return collect([$d["{$side}_city"] ?? null, $d["{$side}_region"] ?? null, Countries::name($d["{$side}_country"])])->filter()->implode(', ');
    }
}
