<?php

namespace App\Services;

use App\Http\Controllers\BookingController;
use App\Models\Address;
use App\Models\BulkImport;
use App\Models\Business;
use App\Models\Service;
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
        'recipient_name', 'recipient_phone', 'recipient_email', 'delivery_address', 'delivery_address2', 'delivery_city',
        'delivery_region', 'delivery_postal_code', 'delivery_country', 'service_code', 'package_description', 'package_category',
        'units', 'weight', 'length', 'width', 'height', 'declared_value', 'insured',
        'customs_contents_type', 'customs_description', 'customs_hs_code', 'delivery_instructions', 'your_reference',
    ];

    public const REQUIRED = ['recipient_name', 'recipient_phone', 'delivery_address', 'delivery_city', 'delivery_country', 'service_code', 'package_description', 'weight'];

    public const MAX_ROWS = 500;

    public function __construct(private PricingService $pricing, private BookingService $booking, private ShipmentDetails $details) {}

    public function templateCsv(): string
    {
        $rows = [
            ['Jane Doe', '+1 212 555 0147', 'jane@example.com', '350 5th Ave', 'Apt 12B', 'New York', 'NY', '10118', 'US', 'SERVICE-CODE', 'Sneakers', 'clothing', 'lb', '3.2', '14', '10', '6', '120', 'no', '', '', '', 'Leave with doorman', 'ORDER-1001'],
            ['Tom Smith', '+44 20 7946 0958', '', '10 Downing St', '', 'London', '', 'SW1A 2AA', 'GB', 'SERVICE-CODE', 'Books', 'other', 'kg', '1.1', '30', '22', '6', '45', 'yes', 'gift', '3 paperback books', '4901.99', '', 'ORDER-1002'],
        ];
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, self::COLUMNS);
        foreach ($rows as $r) {
            fputcsv($csv, $r);
        }
        rewind($csv);

        return stream_get_contents($csv);
    }

    public function preview(UploadedFile $file, Business $business, User $user, Address $pickup): BulkImport
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        if (! $header) {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(self::REQUIRED, $header);
        if ($missing) {
            throw ValidationException::withMessages(['file' => 'Missing columns: '.implode(', ', $missing).'. Download the template.']);
        }

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
            $rows[] = $this->validateRow($line, $data, $services, $business, $pickup, $seen);
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

    private function validateRow(int $line, array $d, $services, Business $business, Address $pickup, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $service = $services[strtoupper($d['service_code'] ?? '')] ?? null;
        $units = strtolower($d['units'] ?? '');
        $form = [
            'sender_name' => $business->name, 'sender_phone' => $pickup->phone, 'sender_email' => $business->email,
            'pickup_address' => $pickup->line1, 'pickup_address2' => $pickup->line2, 'pickup_city' => $pickup->city, 'pickup_region' => $pickup->region,
            'pickup_postal_code' => $pickup->postal_code, 'pickup_country' => $pickup->country_code, 'pickup_lat' => $pickup->lat, 'pickup_lng' => $pickup->lng,
            'recipient_name' => $d['recipient_name'] ?? '', 'recipient_phone' => $d['recipient_phone'] ?? '', 'recipient_email' => ($d['recipient_email'] ?? '') ?: null,
            'delivery_address' => $d['delivery_address'] ?? '', 'delivery_address2' => ($d['delivery_address2'] ?? '') ?: null, 'delivery_city' => $d['delivery_city'] ?? '',
            'delivery_region' => ($d['delivery_region'] ?? '') ?: null, 'delivery_postal_code' => ($d['delivery_postal_code'] ?? '') ?: null,
            'delivery_country' => strtoupper($d['delivery_country'] ?? ''),
            'package_description' => $d['package_description'] ?? '', 'package_category' => ($d['package_category'] ?? '') ?: 'other',
            'units' => in_array($units, ['lb', 'imperial'], true) ? 'imperial' : (in_array($units, ['kg', 'metric'], true) ? 'metric' : null),
            'parcels' => [array_filter(['weight' => $d['weight'] ?? null, 'length' => ($d['length'] ?? '') ?: null, 'width' => ($d['width'] ?? '') ?: null, 'height' => ($d['height'] ?? '') ?: null], fn ($v) => $v !== null)],
            'service_id' => $service?->id,
            'declared_value' => ($d['declared_value'] ?? '') ?: 0,
            'insured' => in_array(strtolower($d['insured'] ?? ''), ['yes', 'true', '1'], true),
            'pickup_requested' => true,
            'customs_contents_type' => ($d['customs_contents_type'] ?? '') ?: null,
            'customs_description' => ($d['customs_description'] ?? '') ?: null,
            'customs_hs_code' => ($d['customs_hs_code'] ?? '') ?: null,
            'delivery_instructions' => trim(($d['delivery_instructions'] ?? '').(($d['your_reference'] ?? '') !== '' ? ' [Ref: '.$d['your_reference'].']' : '')) ?: null,
        ];
        if (! $service) {
            $errors[] = 'Unknown service_code.';
        }

        $rules = ShipmentDetails::rules();
        unset($rules['pickup_date']);
        $v = Validator::make($form, $rules);
        $messages = $v->errors();
        $messages->forget('service_id'); // reported above as "Unknown service_code"
        $errors = array_merge($errors, $messages->all());

        $fingerprint = hash('sha256', mb_strtolower(preg_replace('/\D/', '', $form['recipient_phone']).'|'.$form['delivery_address'].'|'.$form['delivery_postal_code'].'|'.$form['package_description']));
        if (isset($seen[$fingerprint])) {
            $errors[] = "Duplicate of row {$seen[$fingerprint]} in this file.";
        } else {
            $seen[$fingerprint] = $line;
        }

        $quote = null;
        $normalized = null;
        if (! $errors) {
            try {
                $normalized = $this->details->normalize($form);
                $q = $this->pricing->quote(BookingController::quoteInput($normalized), null, $business);
                $quote = ['total' => $q->total, 'currency' => $q->currency];
                $q->delete(); // Preview only; a fresh quote is taken at confirmation.
            } catch (ValidationException $e) {
                $errors = array_merge($errors, $e->validator->errors()->all());
                $normalized = null;
            }
        }
        if ($normalized) {
            $recent = Shipment::where('business_id', $business->id)->where('created_at', '>=', now()->subDays(7))
                ->where('recipient_phone', $normalized['recipient_phone'])->where('delivery_address', $normalized['delivery_address'])
                ->where('package_description', $normalized['package_description'])->exists();
            if ($recent) {
                $warnings[] = 'A shipment with the same recipient, address and description was created in the last 7 days.';
            }
        }

        return ['line' => $line, 'data' => $d, 'details' => $normalized, 'quote' => $quote, 'errors' => array_values(array_unique($errors)), 'warnings' => $warnings];
    }

    /** @return list<Shipment> */
    public function confirm(BulkImport $import, User $user, Business $business, bool $includeWarnings): array
    {
        if ($import->status !== 'previewed') {
            throw ValidationException::withMessages(['import' => 'This import has already been processed.']);
        }
        $created = [];
        foreach ($import->rows['items'] as $i => $row) {
            if ($row['errors'] || ! $row['details'] || ($row['warnings'] && ! $includeWarnings)) {
                continue;
            }
            $d = $this->details->normalize($row['details']); // re-check: zones or rules may have changed since preview
            $quote = $this->pricing->quote(BookingController::quoteInput($d), $user->id, $business);
            $result = $this->booking->create($d, $quote, $user, $business, 'online', 'bulk:'.$import->id.':'.$i);
            $created[] = $result['shipment'];
        }
        $import->forceFill(['status' => 'confirmed', 'confirmed_at' => now()])->save();
        Audit::log('business.bulk_import_confirmed', $business, ['import' => $import->id, 'created' => count($created)]);

        return $created;
    }
}
