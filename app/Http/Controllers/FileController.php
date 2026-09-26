<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAttempt;
use App\Models\DeliveryProof;
use App\Models\Shipment;
use App\Services\FileStorage;
use App\Support\Audit;
use Illuminate\Http\Request;

/** Streams private delivery evidence after an authorisation check on every request. */
class FileController extends Controller
{
    public function proof(Request $request, DeliveryProof $proof, string $kind, FileStorage $files)
    {
        $user = $request->user();
        $allowed = $user->isStaff()
            || $proof->submitted_by === $user->id
            || ($user->isRole('customer') && Shipment::visibleTo($user)->whereKey($proof->shipment_id)->exists());
        abort_unless($allowed, 404);

        $path = $kind === 'photo' ? $proof->photo_path : $proof->signature_path;
        abort_unless($files->exists($path), 404);
        if ($user->isStaff()) {
            Audit::log('file.proof_viewed', $proof, ['kind' => $kind]);
        }

        return $files->response($path);
    }

    public function attempt(Request $request, DeliveryAttempt $attempt, FileStorage $files)
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $attempt->rider_id === $user->id, 404);
        abort_unless($files->exists($attempt->evidence_path), 404);

        return $files->response($attempt->evidence_path);
    }
}
