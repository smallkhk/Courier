<?php

namespace App\Services;

use App\Models\Address;
use App\Models\BulkImport;
use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CSV bulk shipment creation: upload → validate + price each row → preview → confirm.
 */
class BulkImportService
{
    public const COLUMNS = [
        'recipient_name', 'recipient_phone', 'recipient_email', 'delivery_address', 'delivery_city', 'delivery_state',
        'destination_zone_code', 'service_code', 'package_description', 'package_category', 'weight_kg',
        'length_cm', 'width_cm', 'height_cm', 'declared_value', 'insured', 'delivery_instructions', 'your_reference',
    ];

    public const MAX_ROWS = 500;

    public function __construct(private PricingService $pricing, private BookingService $booking) {}

    public function templateCsv(): string
    {
        $example = ['Ada Obi', '08030000000', 'ada@example.com', '12 Example Street', 'Ikeja', 'Lagos', 'ZONE-CODE', 'SERVICE-CODE', 'Shoes', 'clothing', '1.5', '30', '20', '10', '15000', 'no', 'Call on arrival', 'ORDER-1001'];

        return implode(',', self::COLUMNS)."\n".implode(',', $example)."\n";
    }

    public function preview(UploadedFile $file, Business $business, User $user, Address $pickup): BulkImport
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(['recipient_name', 'recipient_phone', 'delivery_address', 'delivery_city', 'delivery_state', 'destination_zone_code', 'service_code', 'package_description', 'weight_kg'], $header);
        if ($missing) {
            throw ValidationException::withMessages(['file' => 'Missing columns: '.implode(', ', $missing).'. Download the template.']);
        }

        $zones = ServiceZone::where('active', true)->get()->keyBy(fn ($z) => strtoupper($z->code));
        $services = Service::where('active', true)->get()->keyBy(fn ($s) => strtoupper($s->code));
        $seen = [];
        $rows = [];
        $line = 1;
        while (($raw = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'Maximum '.self::MAX_ROWS.' rows per upload.']);
            }
            $data = [];
            foreach ($header as $i => $col) {
                $data[$col] = trim((string) ($raw[$i] ?? ''));
            }
            $rows[] = $this->validateRow($line, $data, $zones, $services, $business, $pickup, $seen);
        }
        fclose($handle);

        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'No data rows found.']);
        }

        $valid = count(array_filter($rows, fn ($r) => ! $r['errors']));

        return BulkImport::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'filename' => mb_substr($file->getClientOriginalName(), 0, 200),
            'rows' => ['pickup_address_id' => $pickup->id, 'items' => $rows],
            'valid_count' => $valid,
            'error_count' => count($rows) - $valid,
        ]);
    }

    private function validateRow(int $line, array $d, $zones, $services, Business $business, Address $pickup, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $v = Validator::make($d, [
            'recipient_name' => 'required|max:120',
            'recipient_phone' => ['required', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'recipient_email' => 'nullable|email|max:190',
            'delivery_address' => 'required|max:250',
            'delivery_city' => 'required|max:100',
            'delivery_state' => 'required|max:100',
            'package_description' => 'required|max:200',
            'package_category' => 'nullable|in:'.implode(',', array_keys(Shipment::CATEGORIES)),
            'weight_kg' => 'required|numeric|min:0.01|max:1000',
            'length_cm' => 'nullable|numeric|min:1|max:500',
            'width_cm' => 'nullable|numeric|min:1|max:500',
            'height_cm' => 'nullable|numeric|min:1|max:500',
            'declared_value' => 'nullable|numeric|min:0|max:100000000',
            'insured' => 'nullable|in:yes,no,YES,NO,Yes,No,true,false,1,0',
        ]);
        $errors = $v->errors()->all();

        $zone = $zones[strtoupper($d['destination_zone_code'] ?? '')] ?? null;
        $service = $services[strtoupper($d['service_code'] ?? '')] ?? null;
        if (! $zone) {
            $errors[] = 'Unknown destination_zone_code.';
        } elseif (! $zone->coversCity($d['delivery_city'] ?? '')) {
            $errors[] = "City “{$d['delivery_city']}” is not in zone {$zone->code}.";
        }
        if (! $service) {
            $errors[] = 'Unknown service_code.';
        }

        $fingerprint = hash('sha256', mb_strtolower(preg_replace('/\D/', '', $d['recipient_phone'] ?? '').'|'.($d['delivery_address'] ?? '').'|'.($d['package_description'] ?? '')));
        if (isset($seen[$fingerprint])) {
            $errors[] = "Duplicate of row {$seen[$fingerprint]} in this file.";
        } else {
            $seen[$fingerprint] = $line;
        }

        $quote = null;
        $input = null;
        if (! $errors) {
            $input = [
                'service_id' => $service->id,
                'origin_zone_id' => $pickup->service_zone_id,
                'destination_zone_id' => $zone->id,
                'parcels' => [array_filter([
                    'weight_kg' => $d['weight_kg'],
                    'length_cm' => $d['length_cm'] ?: null,
                    'width_cm' => $d['width_cm'] ?: null,
                    'height_cm' => $d['height_cm'] ?: null,
                ], fn ($x) => $x !== null)],
                'declared_value' => $d['declared_value'] ?: 0,
                'insured' => in_array(strtolower($d['insured'] ?? ''), ['yes', 'true', '1'], true),
                'pickup_requested' => true,
            ];
            try {
                $q = $this->pricing->quote($input, null, $business);
                $quote = ['total' => $q->total, 'currency' => $q->currency];
                $q->delete(); // Preview only; a fresh quote is taken at confirmation.
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->validator->errors()->all());
            }
            $recent = Shipment::where('business_id', $business->id)->where('created_at', '>=', now()->subDays(7))
                ->where('recipient_phone', $d['recipient_phone'])->where('delivery_address', $d['delivery_address'])
                ->where('package_description', $d['package_description'])->exists();
            if ($recent) {
                $warnings[] = 'A shipment with the same recipient, address and description was created in the last 7 days.';
            }
        }

        return ['line' => $line, 'data' => $d, 'input' => $input, 'quote' => $quote, 'errors' => $errors, 'warnings' => $warnings];
    }

    /** @return list<Shipment> */
    public function confirm(BulkImport $import, User $user, Business $business, bool $includeWarnings): array
    {
        if ($import->status !== 'previewed') {
            throw ValidationException::withMessages(['import' => 'This import has already been processed.']);
        }
        $pickup = Address::where('business_id', $business->id)->findOrFail($import->rows['pickup_address_id']);
        $created = [];
        foreach ($import->rows['items'] as $i => $row) {
            if ($row['errors'] || ($row['warnings'] && ! $includeWarnings)) {
                continue;
            }
            $d = $row['data'];
            $quote = $this->pricing->quote($row['input'], $user->id, $business);
            $result = $this->booking->create([
                'sender_name' => $business->name.' — '.$pickup->contact_name,
                'sender_phone' => $pickup->phone,
                'sender_email' => $business->email,
                'pickup_address' => trim($pickup->line1.' '.$pickup->line2),
                'pickup_city' => $pickup->city,
                'pickup_state' => $pickup->state,
                'recipient_name' => $d['recipient_name'],
                'recipient_phone' => $d['recipient_phone'],
                'recipient_email' => $d['recipient_email'] ?: null,
                'delivery_address' => $d['delivery_address'],
                'delivery_city' => $d['delivery_city'],
                'delivery_state' => $d['delivery_state'],
                'delivery_instructions' => trim(($d['delivery_instructions'] ?? '').(($d['your_reference'] ?? '') !== '' ? ' [Ref: '.$d['your_reference'].']' : '')) ?: null,
                'package_description' => $d['package_description'],
                'package_category' => $d['package_category'] ?: 'other',
            ], $quote, $user, $business, 'online', 'bulk:'.$import->id.':'.$i);
            $created[] = $result['shipment'];
        }
        $import->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();
        Audit::log('business.bulk_import_confirmed', $business, ['import' => $import->id, 'created' => count($created)]);

        return $created;
    }
}
