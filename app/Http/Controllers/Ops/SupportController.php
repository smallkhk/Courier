<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request)
    {
        $q = SupportTicket::with('assignee', 'shipment')->latest('updated_at');
        $status = $request->input('status', 'open_all');
        if ($status === 'open_all') {
            $q->whereNotIn('status', ['resolved', 'closed']);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($request->filled('category')) {
            $q->where('category', $request->string('category'));
        }
        if ($request->boolean('mine')) {
            $q->where('assigned_to', $request->user()->id);
        }

        return view('ops.support.index', ['tickets' => $q->paginate(30)->withQueryString()]);
    }

    public function show(SupportTicket $ticket)
    {
        return view('ops.support.show', [
            't' => $ticket->load('messages.author', 'shipment', 'user', 'assignee'),
            'staff' => User::whereIn('role', ['dispatcher', 'admin'])->where('status', 'active')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportService $support)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'internal' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
        ]);
        $support->reply($ticket, $request->user(), $data['body'], $request->boolean('internal'), $data['status'] ?? null);

        return back()->with('success', $request->boolean('internal') ? 'Internal note added.' : 'Reply sent to the customer.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))],
        ]);
        $before = $ticket->only(array_keys($data));
        $ticket->update($data);
        Audit::log('support.updated', $ticket, ['before' => $before, 'after' => $data]);

        return back()->with('success', 'Ticket updated.');
    }
}
