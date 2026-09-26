<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMember extends Model
{
    protected $guarded = ['id'];

    /** Business team roles and what each may do. */
    public const PERMISSIONS = [
        'owner' => ['view_all', 'create_shipments', 'manage_team', 'view_invoices', 'view_reports', 'manage_company'],
        'admin' => ['view_all', 'create_shipments', 'manage_team', 'view_invoices', 'view_reports'],
        'shipper' => ['create_shipments'],
        'finance' => ['view_all', 'view_invoices', 'view_reports'],
        'viewer' => ['view_all'],
    ];

    public const ROLE_LABELS = [
        'owner' => 'Owner — full access',
        'admin' => 'Admin — team, shipments, invoices, reports',
        'shipper' => 'Shipper — create and view own shipments',
        'finance' => 'Finance — invoices, reports, view shipments',
        'viewer' => 'Viewer — read-only shipment access',
    ];

    public function can(string $permission): bool
    {
        return in_array($permission, self::PERMISSIONS[$this->role] ?? [], true);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
