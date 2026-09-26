<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function notifications(Request $request)
    {
        $rows = NotificationLog::where('user_id', $request->user()->id)->latest()->paginate(20);

        return response()->json([
            'data' => collect($rows->items())->map(fn ($n) => ['event' => $n->event, 'channel' => $n->channel, 'subject' => $n->subject, 'status' => $n->status, 'created_at' => $n->created_at->toIso8601String()]),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage()],
        ]);
    }

    public function index(Request $request)
    {
        return response()->json(['data' => SupportTicket::where('user_id', $request->user()->id)->latest()->get(['reference', 'subject', 'category', 'status', 'created_at', 'updated_at'])]);
    }

    public function store(Request $request, SupportService $support)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:5000',
            'tracking_number' => 'nullable|string|max:20',
        ]);
        $u = $request->user();
        $t = $support->open($data + ['contact_name' => $u->name, 'contact_email' => $u->email, 'contact_phone' => $u->phone], $u);

        return response()->json(['reference' => $t->reference, 'status' => $t->status], 201);
    }

    public function message(Request $request, SupportTicket $ticket, SupportService $support)
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $ticket->user_id === $user->id, 404);
        $data = $request->validate(['body' => 'required|string|max:5000', 'internal' => 'nullable|boolean']);
        $msg = $support->reply($ticket, $user, $data['body'], (bool) ($data['internal'] ?? false));

        return response()->json(['id' => $msg->id, 'internal' => $msg->is_internal], 201);
    }
}
