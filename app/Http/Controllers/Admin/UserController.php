<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->orderBy('name');
        if ($request->filled('role')) {
            $q->where('role', $request->string('role'));
        }
        if ($request->filled('q')) {
            $t = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('name', 'like', $t)->orWhere('email', 'like', $t));
        }

        return view('admin.users.index', ['users' => $q->paginate(40)->withQueryString()]);
    }

    public function create()
    {
        return view('admin.users.form', ['u' => new User(['role' => 'dispatcher', 'status' => 'active'])]);
    }

    /** Create staff accounts. Riders are created from the Riders page (they need a profile). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email',
            'phone' => 'nullable|string|max:32',
            'role' => ['required', Rule::in(['dispatcher', 'admin', 'customer'])],
        ]);
        $user = User::create($data + ['password' => Str::password(32), 'email_verified_at' => now()]);
        Password::sendResetLink(['email' => $user->email]);
        Audit::log('admin.user_created', $user, ['role' => $user->role]);

        return redirect()->route('admin.users.index')->with('success', 'User created and emailed a link to set their password.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['u' => $user]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:32',
            'role' => ['required', Rule::in(User::ROLES)],
            'status' => 'required|in:active,suspended',
        ]);
        if ($user->id === $request->user()->id && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            return back()->with('error', 'You cannot remove your own admin access or suspend yourself.');
        }
        if ($data['role'] === 'rider' && ! $user->riderProfile) {
            return back()->with('error', 'Create riders from the Riders page so they get a rider profile.');
        }
        $before = $user->only(['role', 'status']);
        DB::transaction(function () use ($user, $data) {
            $user->update($data);
            if ($data['status'] === 'suspended') {
                DB::table('sessions')->where('user_id', $user->id)->delete(); // Sign out everywhere.
            }
        });
        Audit::log('admin.user_updated', $user, ['before' => $before, 'after' => $user->only(['role', 'status'])]);

        return back()->with('success', 'User updated.');
    }
}
