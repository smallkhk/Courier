<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateController extends Controller
{
    public function index(UpdateService $updates)
    {
        $error = null;
        try {
            $latest = $updates->latest();
        } catch (Throwable $e) {
            $latest = null;
            $error = $e instanceof UpdateException ? $e->getMessage() : 'Could not check for updates right now.';
        }

        return view('admin.updates.index', [
            'current' => $updates->current(),
            'latest' => $latest,
            'available' => $latest && $updates->isNewer($latest['version']),
            'error' => $error,
            'pending' => $updates->pending(),
            'requirements' => $updates->requirements(),
            'backups' => $updates->backups(),
            'history' => AppUpdate::with('user')->latest('id')->limit(15)->get(),
        ]);
    }

    public function check(UpdateService $updates)
    {
        try {
            $latest = $updates->latest(fresh: true);
        } catch (UpdateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($latest && $updates->isNewer($latest['version']) ? 'success' : 'status',
            $latest && $updates->isNewer($latest['version']) ? "Version {$latest['version']} is available." : 'You are on the latest version.');
    }

    public function install(Request $request, UpdateService $updates)
    {
        $this->confirmPassword($request);
        $release = $updates->latest(fresh: true);
        if (! $release || ! $updates->isNewer($release['version'])) {
            return back()->with('status', 'You are already on the latest version.');
        }
        Audit::log('system.update_started', null, ['from' => $updates->current(), 'to' => $release['version']]);

        // Render the "finishing" page BEFORE the new files are copied in, so this response
        // never mixes old and new code. It posts straight to the finish step.
        $html = view('admin.updates.finishing', ['version' => $release['version']])->render();
        try {
            $updates->prepare($release, $request->user());
        } catch (UpdateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return response($html);
    }

    /** Runs in a fresh request, so the newly installed code is the code executing. */
    public function finish(UpdateService $updates)
    {
        try {
            $done = $updates->finish();
        } catch (UpdateException $e) {
            return redirect()->route('admin.updates.index')->with('error', $e->getMessage());
        }
        Audit::log('system.updated', $done, ['from' => $done->from_version, 'to' => $done->to_version]);

        return redirect()->route('admin.updates.index')->with('success', "Updated to version {$done->to_version}. Everything is back online.");
    }

    public function rollback(Request $request, UpdateService $updates)
    {
        $this->confirmPassword($request);
        $data = $request->validate(['backup' => 'required|string|max:120']);
        try {
            $done = $updates->rollback($data['backup'], $request->user());
        } catch (UpdateException $e) {
            return back()->with('error', $e->getMessage());
        }
        Audit::log('system.rolled_back', $done, ['to' => $done->to_version, 'backup' => $data['backup']]);

        return redirect()->route('admin.updates.index')->with('success', "Restored version {$done->to_version}.");
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['current_password' => 'required|string']);
        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'That password is not correct.']);
        }
    }
}
