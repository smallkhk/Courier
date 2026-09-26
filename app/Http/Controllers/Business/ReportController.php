<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $from = $request->date('from') ?? now()->subDays(29);
        $to = $request->date('to') ?? now();

        return view('business.reports', [
            'from' => $from, 'to' => $to,
            'summary' => $reports->summary($from, $to, $request->attributes->get('membership')->business_id),
        ]);
    }
}
