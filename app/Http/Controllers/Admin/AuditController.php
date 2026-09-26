<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::with('actor:id,name,role')->latest('id');
        if ($request->filled('action')) {
            $q->where('action', 'like', $request->string('action').'%');
        }
        if ($request->filled('actor')) {
            $q->whereHas('actor', fn ($a) => $a->where('email', 'like', '%'.$request->string('actor').'%')->orWhere('name', 'like', '%'.$request->string('actor').'%'));
        }
        if ($request->filled('entity')) {
            $q->where('entity_type', $request->string('entity'));
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->date('to'));
        }

        return view('admin.audit', ['logs' => $q->paginate(50)->withQueryString(), 'entities' => AuditLog::distinct()->whereNotNull('entity_type')->pluck('entity_type')]);
    }
}
