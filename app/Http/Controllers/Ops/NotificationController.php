<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = NotificationLog::with('shipment:id,tracking_number')->latest();
        foreach (['status', 'channel', 'event'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->string($f));
            }
        }

        return view('ops.notifications', [
            'logs' => $q->paginate(40)->withQueryString(),
            'counts' => NotificationLog::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'drivers' => ['email' => config('mail.default'), 'sms' => config('courier.sms.driver')],
        ]);
    }

    public function retry(NotificationLog $log)
    {
        abort_unless(in_array($log->status, ['failed', 'skipped'], true), 422);
        $log->forceFill(['status' => 'queued', 'attempts' => 0, 'next_attempt_at' => now(), 'last_error' => null])->save();

        return back()->with('success', 'Queued for another attempt.');
    }
}
