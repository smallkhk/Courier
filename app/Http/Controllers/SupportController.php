<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Services\SupportService;
use App\Support\SpamGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    private function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'subject' => 'required|string|max:150',
            'message' => 'required|string|min:10|max:5000',
            'tracking_number' => 'nullable|string|max:20',
        ];
    }

    public function contact(Request $request)
    {
        return view('public.contact', ['tracking' => $request->query('tracking')]);
    }

    public function submit(Request $request, SupportService $support)
    {
        SpamGuard::check($request);
        $data = $request->validate($this->rules() + [
            'contact_name' => 'required|string|max:120',
            'contact_email' => 'required|email:rfc|max:190',
            'contact_phone' => 'nullable|string|max:32',
        ]);
        $ticket = $support->open($data, $request->user());

        return redirect()->route('support.contact')->with('success', "Thanks — your request {$ticket->reference} has been received. We'll reply by email.");
    }

    public function index(Request $request)
    {
        return view('account.support.index', [
            'tickets' => SupportTicket::where('user_id', $request->user()->id)->latest()->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        return view('account.support.create', [
            'shipments' => Shipment::visibleTo($request->user())->latest()->limit(50)->pluck('tracking_number'),
            'tracking' => $request->query('tracking'),
        ]);
    }

    public function store(Request $request, SupportService $support)
    {
        $data = $request->validate($this->rules());
        $user = $request->user();
        $ticket = $support->open($data + ['contact_name' => $user->name, 'contact_email' => $user->email, 'contact_phone' => $user->phone], $user);

        return redirect()->route('account.support.show', $ticket)->with('success', 'Your support request has been opened.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        return view('account.support.show', ['ticket' => $ticket->load(['publicMessages.author', 'shipment'])]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportService $support)
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        abort_if($ticket->status === 'closed', 422, 'This ticket is closed. Please open a new request.');
        $request->validate(['body' => 'required|string|min:2|max:5000']);
        $support->reply($ticket, $request->user(), $request->input('body'));

        return back()->with('success', 'Reply sent.');
    }
}
