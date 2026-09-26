<?php

namespace App\Services;

use App\Models\DeliveryAttempt;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Metric definitions (shown on the reports page):
 *  - Booked: shipments whose booked_at falls in the period.
 *  - Delivered: shipments whose delivered_at falls in the period.
 *  - Failed attempts: delivery_attempts recorded in the period.
 *  - On-time rate: delivered in period with an estimate, where delivered date <= estimated_delivery_to.
 *  - Exception rate: failed attempts ÷ (delivered + failed attempts) in the period.
 *  - Collected revenue: SUCCESSFUL, provider-verified payments (paid_at in period) minus processed refunds,
 *    per currency. Invoice-terms and COD shipments are not counted until paid/reconciled.
 */
class ReportService
{
    public function summary(Carbon $from, Carbon $to, ?int $businessId = null): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $scope = fn ($q) => $businessId ? $q->where('business_id', $businessId) : $q;

        $booked = $scope(Shipment::query())->whereBetween('booked_at', [$from, $to])->count();
        $delivered = $scope(Shipment::query())->whereBetween('delivered_at', [$from, $to])->count();
        $withEta = $scope(Shipment::query())->whereBetween('delivered_at', [$from, $to])->whereNotNull('estimated_delivery_to');
        $etaCount = (clone $withEta)->count();
        $onTime = (clone $withEta)->whereRaw('DATE(delivered_at) <= estimated_delivery_to')->count();
        $failed = DeliveryAttempt::whereBetween('attempted_at', [$from, $to])
            ->when($businessId, fn ($q) => $q->whereHas('shipment', fn ($s) => $s->where('business_id', $businessId)))->count();

        $revenue = [];
        Payment::whereIn('status', ['successful', 'refunded'])
            ->whereBetween('paid_at', [$from, $to])
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->get(['amount', 'currency'])
            ->each(function ($p) use (&$revenue) {
                $revenue[$p->currency] = ($revenue[$p->currency] ?? 0) + Money::toMinor($p->amount);
            });
        Refund::where('status', 'processed')->whereBetween('processed_at', [$from, $to])->with('payment')->get()
            ->filter(fn ($r) => ! $businessId || $r->payment->business_id === $businessId)
            ->each(function ($r) use (&$revenue) {
                $c = $r->payment->currency;
                $revenue[$c] = ($revenue[$c] ?? 0) - Money::toMinor($r->amount);
            });

        $byStatus = $scope(Shipment::query())->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        return [
            'booked' => $booked,
            'delivered' => $delivered,
            'failed_attempts' => $failed,
            'on_time_rate' => $etaCount ? round($onTime / $etaCount * 100, 1) : null,
            'exception_rate' => ($delivered + $failed) ? round($failed / ($delivered + $failed) * 100, 1) : null,
            'revenue' => array_map(fn ($m) => Money::fromMinor($m), $revenue),
            'by_status' => $byStatus,
        ];
    }

    /** Today's operational snapshot for the ops dashboard. */
    public function operations(): array
    {
        $count = fn (array $statuses) => Shipment::whereIn('status', $statuses)->count();

        return [
            'booked_today' => Shipment::whereDate('booked_at', today())->count(),
            'awaiting_pickup' => $count(['booked', 'pickup_scheduled', 'rider_assigned']),
            'in_transit' => $count(['picked_up', 'at_origin_facility', 'in_transit', 'at_destination_facility']),
            'out_for_delivery' => $count(['out_for_delivery']),
            'delivered_today' => Shipment::whereDate('delivered_at', today())->count(),
            'failed_today' => DeliveryAttempt::whereDate('attempted_at', today())->count(),
            'exceptions_open' => $count(['delivery_attempted', 'delivery_exception', 'on_hold']),
            'unassigned' => app(DispatchService::class)->unassignedQuery()->count(),
            'pending_payments' => Payment::where('status', 'pending')->count(),
            'pending_refunds' => Refund::where('status', 'pending')->count(),
            'open_tickets' => SupportTicket::whereNotIn('status', ['resolved', 'closed'])->count(),
        ];
    }
}
