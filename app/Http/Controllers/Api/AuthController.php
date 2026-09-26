<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $u = $request->user();
        $m = $u->primaryMembership();

        return response()->json([
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'role' => $u->role,
            'email_verified' => (bool) $u->email_verified_at,
            'business' => $m ? ['name' => $m->business->name, 'status' => $m->business->status, 'role' => $m->role] : null,
        ]);
    }
}
