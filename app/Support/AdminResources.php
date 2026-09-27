<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Business;
use App\Models\ContentBlock;
use App\Models\Faq;
use App\Models\NotificationTemplate;
use App\Models\PricingRule;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Services\Notifications\NotificationService;

/**
 * Declarative definitions for administrator-managed configuration.
 * Field types: text, textarea, number, money, percent, bool, select, date, list (comma-separated → JSON array),
 * hours (weekly opening hours), closures (one "YYYY-MM-DD note" per line), relation (many-to-many ids).
 */
class AdminResources
{
    public static function all(): array
    {
        $zones = fn () => ServiceZone::orderBy('name')->pluck('name', 'id')->all();
        $services = fn () => Service::orderBy('name')->pluck('name', 'id')->all();
        $countries = fn () => Countries::options();

        return [
            'services' => [
                'title' => 'Services', 'singular' => 'service', 'model' => Service::class, 'order' => 'sort_order',
                'columns' => ['code' => 'Code', 'name' => 'Name', 'transit_days_min' => 'Min days', 'transit_days_max' => 'Max days', 'active' => 'Active'],
                'help' => 'Transit days are published as estimates. Leave both blank if you do not commit to a delivery time — no estimate will be shown.',
                'fields' => [
                    'code' => ['text', 'Code', 'required|alpha_dash|max:32|unique:services,code,{id}'],
                    'name' => ['text', 'Name', 'required|max:120'],
                    'description' => ['textarea', 'Description', 'nullable|max:1000'],
                    'transit_days_min' => ['number', 'Minimum transit (business days)', 'nullable|integer|min:0|max:60'],
                    'transit_days_max' => ['number', 'Maximum transit (business days)', 'nullable|integer|min:0|max:60|gte:transit_days_min'],
                    'max_weight_kg' => ['number', 'Max chargeable weight in kg (1 kg = 2.2 lb)', 'nullable|numeric|min:0'],
                    'sort_order' => ['number', 'Sort order', 'required|integer|min:0'],
                    'zones' => ['relation', 'Available in zones', 'nullable|array', $zones],
                    'active' => ['bool', 'Active', 'boolean'],
                ],
            ],
            'pricing-rules' => [
                'title' => 'Pricing rules', 'singular' => 'pricing rule', 'model' => PricingRule::class, 'order' => 'service_id', 'with' => ['service', 'originZone', 'destinationZone', 'business'],
                'columns' => ['name' => 'Name', 'service.name' => 'Service', 'originZone.name' => 'From', 'destinationZone.name' => 'To', 'business.name' => 'Business', 'currency' => 'Cur.', 'base_fee' => 'Base', 'active' => 'Active'],
                'help' => 'The most specific active rule wins: business-specific, then exact route, then origin/destination only, then “any”. Leave zones blank for “any”. Prices apply to new quotes only; existing bookings keep their price.',
                'fields' => [
                    'name' => ['text', 'Rule name', 'required|max:150'],
                    'service_id' => ['select', 'Service', 'required|exists:services,id', $services],
                    'origin_zone_id' => ['select', 'Origin zone (blank = any)', 'nullable|exists:service_zones,id', $zones],
                    'destination_zone_id' => ['select', 'Destination zone (blank = any)', 'nullable|exists:service_zones,id', $zones],
                    'business_id' => ['select', 'Negotiated rate for business (blank = public)', 'nullable|exists:businesses,id', fn () => Business::orderBy('name')->pluck('name', 'id')->all()],
                    'currency' => ['text', 'Currency (ISO code, e.g. USD)', 'required|size:3|alpha'],
                    'weight_unit' => ['select', 'Weight unit for this rule', 'required|in:lb,kg', fn () => ['lb' => 'Pounds (dimensions in inches)', 'kg' => 'Kilograms (dimensions in cm)']],
                    'base_fee' => ['money', 'Base fee', 'required|numeric|min:0'],
                    'included_weight' => ['number', 'Weight included in base fee (in the rule\'s unit)', 'required|numeric|min:0'],
                    'per_weight_fee' => ['money', 'Fee per additional lb / kg (started)', 'required|numeric|min:0'],
                    'extra_parcel_fee' => ['money', 'Fee per additional parcel', 'required|numeric|min:0'],
                    'min_charge' => ['money', 'Minimum charge', 'required|numeric|min:0'],
                    'remote_surcharge' => ['money', 'Remote-area surcharge', 'required|numeric|min:0'],
                    'pickup_surcharge' => ['money', 'Pickup surcharge', 'required|numeric|min:0'],
                    'insurance_rate_percent' => ['percent', 'Insurance rate (% of declared value)', 'required|numeric|min:0|max:100'],
                    'insurance_min_fee' => ['money', 'Minimum insurance fee', 'required|numeric|min:0'],
                    'discount_percent' => ['percent', 'Discount on freight (%)', 'required|numeric|min:0|max:100'],
                    'tax_rate_percent' => ['percent', 'Tax rate (%)', 'required|numeric|min:0|max:100'],
                    'volumetric_divisor' => ['number', 'Dimensional-weight divisor (139 for lb/in³ in the US; 5000 for kg/cm³)', 'required|integer|min:100|max:10000'],
                    'effective_from' => ['date', 'Effective from', 'nullable|date'],
                    'effective_to' => ['date', 'Effective to', 'nullable|date|after_or_equal:effective_from'],
                    'active' => ['bool', 'Active', 'boolean'],
                ],
                'defaults' => ['currency' => Settings::currency(), 'included_weight' => 0, 'per_weight_fee' => 0, 'extra_parcel_fee' => 0, 'min_charge' => 0, 'remote_surcharge' => 0, 'pickup_surcharge' => 0, 'insurance_rate_percent' => 0, 'insurance_min_fee' => 0, 'discount_percent' => 0, 'tax_rate_percent' => 0, 'weight_unit' => 'lb', 'volumetric_divisor' => 139, 'active' => true],
            ],
            'zones' => [
                'title' => 'Coverage zones', 'singular' => 'zone', 'model' => ServiceZone::class, 'order' => 'name',
                'columns' => ['code' => 'Code', 'name' => 'Name', 'country_code' => 'Country', 'is_remote' => 'Remote', 'pickup_enabled' => 'Pickup', 'delivery_enabled' => 'Delivery', 'active' => 'Active'],
                'help' => 'Addresses are matched to zones automatically. A zone covers a country (or every country if left blank), optionally narrowed to states/provinces, ZIP/postal-code prefixes and/or cities. When several zones match, the most specific wins (postal prefix > city > state > country > any). Examples: "US-NYC" = US + prefixes 100,101,102,103,104,110,111,112,113,114,116; "US-REST" = US only; "INTL" = blank country.',
                'fields' => [
                    'code' => ['text', 'Code', 'required|alpha_dash|max:32|unique:service_zones,code,{id}'],
                    'name' => ['text', 'Name', 'required|max:120'],
                    'country_code' => ['select', 'Country (blank = any country)', 'nullable|size:2', $countries],
                    'regions' => ['list', 'States / provinces (comma separated codes, e.g. NY, NJ, CT)', 'nullable|string|max:3000'],
                    'postal_prefixes' => ['list', 'ZIP / postal code prefixes (comma separated, e.g. 100, 101, 112)', 'nullable|string|max:5000'],
                    'cities' => ['list', 'Cities (comma separated)', 'nullable|string|max:3000'],
                    'center_lat' => ['number', 'Map centre latitude', 'nullable|numeric|between:-90,90'],
                    'center_lng' => ['number', 'Map centre longitude', 'nullable|numeric|between:-180,180'],
                    'is_remote' => ['bool', 'Remote / extended area (surcharge applies)', 'boolean'],
                    'pickup_enabled' => ['bool', 'Pickup available', 'boolean'],
                    'delivery_enabled' => ['bool', 'Delivery available', 'boolean'],
                    'active' => ['bool', 'Active', 'boolean'],
                ],
                'defaults' => ['pickup_enabled' => true, 'delivery_enabled' => true, 'active' => true],
            ],
            'branches' => [
                'title' => 'Branches & pickup points', 'singular' => 'branch', 'model' => Branch::class, 'order' => 'name', 'with' => ['zone'],
                'columns' => ['code' => 'Code', 'name' => 'Name', 'city' => 'City', 'zone.name' => 'Zone', 'is_pickup_point' => 'Pickup point', 'active' => 'Active'],
                'fields' => [
                    'code' => ['text', 'Code', 'required|alpha_dash|max:32|unique:branches,code,{id}'],
                    'name' => ['text', 'Name', 'required|max:150'],
                    'service_zone_id' => ['select', 'Zone', 'nullable|exists:service_zones,id', $zones],
                    'address' => ['text', 'Street address', 'required|max:250'],
                    'city' => ['text', 'City', 'required|max:100'],
                    'region' => ['text', 'State / province / region', 'nullable|max:100'],
                    'postal_code' => ['text', 'ZIP / postal code', 'nullable|max:20'],
                    'country_code' => ['select', 'Country', 'required|size:2', $countries],
                    'phone' => ['text', 'Phone', 'nullable|max:32'],
                    'email' => ['text', 'Email', 'nullable|email|max:190'],
                    'lat' => ['number', 'Latitude', 'nullable|numeric|between:-90,90'],
                    'lng' => ['number', 'Longitude', 'nullable|numeric|between:-180,180'],
                    'opening_hours' => ['hours', 'Opening hours (e.g. 08:00–18:00; blank = closed)', 'nullable|array'],
                    'holiday_closures' => ['closures', 'Holiday closures (one per line: YYYY-MM-DD note)', 'nullable|string|max:3000'],
                    'is_pickup_point' => ['bool', 'Customers can drop off / collect here', 'boolean'],
                    'active' => ['bool', 'Active', 'boolean'],
                ],
                'defaults' => ['is_pickup_point' => true, 'active' => true],
            ],
            'templates' => [
                'title' => 'Message templates', 'singular' => 'template', 'model' => NotificationTemplate::class, 'order' => 'event',
                'columns' => ['event' => 'Event', 'channel' => 'Channel', 'subject' => 'Subject', 'active' => 'Active'],
                'help' => 'Placeholders: {{business_name}} {{tracking_number}} {{tracking_url}} {{sender_name}} {{recipient_name}} {{status}} {{status_description}} {{origin_city}} {{destination_city}} {{total}} {{estimated_delivery}} {{payment_reference}} {{delivery_code}} {{support_email}} {{ticket_reference}} {{ticket_subject}} {{ticket_status}} {{ticket_url}} {{customer_name}} {{rider_name}} {{portal_url}}. Never include passwords or card data. Keep SMS under 160 characters where possible.',
                'fields' => [
                    'event' => ['select', 'Event', 'required', fn () => NotificationService::EVENTS],
                    'channel' => ['select', 'Channel', 'required|in:email,sms', fn () => ['email' => 'Email', 'sms' => 'SMS']],
                    'subject' => ['text', 'Email subject', 'nullable|required_if:channel,email|max:190'],
                    'body' => ['textarea', 'Body', 'required|max:5000'],
                    'active' => ['bool', 'Active', 'boolean'],
                ],
                'unique' => ['event', 'channel'],
                'defaults' => ['active' => true],
            ],
            'content' => [
                'title' => 'Website content', 'singular' => 'page', 'model' => ContentBlock::class, 'order' => 'key',
                'columns' => ['key' => 'Key', 'title' => 'Title', 'updated_at' => 'Updated'],
                'help' => 'Pages: about, terms, privacy, delivery-policy. Plain text: blank lines separate paragraphs, lines starting with "## " become headings, lines starting with "- " become bullet lists. Legal pages should be reviewed by a qualified lawyer.',
                'fields' => [
                    'key' => ['select', 'Page', 'required|unique:content_blocks,key,{id}', fn () => ['about' => 'About', 'terms' => 'Terms of service', 'privacy' => 'Privacy policy', 'delivery-policy' => 'Delivery & claims policy']],
                    'title' => ['text', 'Title', 'required|max:190'],
                    'body' => ['textarea', 'Body', 'required|max:60000'],
                ],
            ],
            'faqs' => [
                'title' => 'FAQs', 'singular' => 'FAQ', 'model' => Faq::class, 'order' => 'sort_order',
                'columns' => ['question' => 'Question', 'sort_order' => 'Order', 'active' => 'Active'],
                'fields' => [
                    'question' => ['text', 'Question', 'required|max:250'],
                    'answer' => ['textarea', 'Answer', 'required|max:5000'],
                    'sort_order' => ['number', 'Sort order', 'required|integer|min:0'],
                    'active' => ['bool', 'Active', 'boolean'],
                ],
                'defaults' => ['active' => true, 'sort_order' => 0],
            ],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? abort(404);
    }
}
