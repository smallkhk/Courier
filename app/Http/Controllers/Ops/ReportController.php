<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAttempt;
use App\Models\RiderAssignment;
use App\Models\RiderProfile;
use App\Services\ReportService;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $from = $request->date('from') ?? now()->subDays(29);
        $to = $request->date('to') ?? now();

        return view('ops.reports', [
            'from' => $from, 'to' => $to,
            'summary' => $reports->summary($from, $to),
            'riders' => RiderProfile::with('user')->get()->map(fn ($p) => [
                'name' => $p->user->name,
                'open' => $p->activeAssignmentCount(),
                'completed' => RiderAssignment::where('rider_id', $p->user_id)->where('status', 'completed')->whereBetween('ended_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
                'failed' => DeliveryAttempt::where('rider_id', $p->user_id)->whereBetween('attempted_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            ]),
        ]);
    }

    public function export(Request $request, ReportService $reports)
    {
        $from = $request->date('from') ?? now()->subDays(29);
        $to = $request->date('to') ?? now();
        $s = $reports->summary($from, $to);
        Audit::log('ops.report_exported', null, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $rows = [
            ['Period', $from->toDateString().' to '.$to->toDateString()],
            ['Booked', $s['booked']], ['Delivered', $s['delivered']], ['Failed attempts', $s['failed_attempts']],
            ['On-time rate %', $s['on_time_rate'] ?? 'n/a'], ['Exception rate %', $s['exception_rate'] ?? 'n/a'],
        ];
        foreach ($s['revenue'] as $cur => $amt) {
            $rows[] = ["Verified payments ({$cur})", $amt];
        }
        foreach ($s['by_status'] as $st => $n) {
            $rows[] = ["Created in period, now {$st}", $n];
        }

        return Csv::download('report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Metric', 'Value'], $rows);
    }
}
