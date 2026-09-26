<?php

namespace App\Support;

use App\Models\User;

/** Sidebar navigation per portal. Items are also protected server-side by route middleware. */
class Navigation
{
    /** @return list<array{label:string, route:string, icon:string, match?:string}> */
    public static function for(string $portal, ?User $user): array
    {
        return match ($portal) {
            'account' => [
                ['label' => 'Dashboard', 'route' => 'account.dashboard', 'icon' => 'home'],
                ['label' => 'Send a parcel', 'route' => 'book.start', 'icon' => 'plus'],
                ['label' => 'My shipments', 'route' => 'account.shipments.index', 'icon' => 'package', 'match' => 'account.shipments.*'],
                ['label' => 'Saved addresses', 'route' => 'account.addresses.index', 'icon' => 'map-pin', 'match' => 'account.addresses.*'],
                ['label' => 'Payments & receipts', 'route' => 'account.payments.index', 'icon' => 'receipt', 'match' => 'account.payments.*'],
                ['label' => 'Notifications', 'route' => 'account.notifications', 'icon' => 'bell'],
                ['label' => 'Support', 'route' => 'account.support.index', 'icon' => 'life-buoy', 'match' => 'account.support.*'],
                ['label' => 'Profile & settings', 'route' => 'account.profile', 'icon' => 'user'],
            ],
            'business' => self::business($user),
            'rider' => [
                ['label' => 'Today', 'route' => 'rider.dashboard', 'icon' => 'home'],
                ['label' => 'Delivery history', 'route' => 'rider.history', 'icon' => 'history'],
            ],
            'ops' => self::ops($user),
            default => [],
        };
    }

    private static function business(?User $user): array
    {
        $m = $user?->primaryMembership();
        $items = [['label' => 'Company dashboard', 'route' => 'business.dashboard', 'icon' => 'home']];
        if ($m?->can('create_shipments')) {
            $items[] = ['label' => 'New shipment', 'route' => 'book.start', 'icon' => 'plus'];
            $items[] = ['label' => 'Bulk upload (CSV)', 'route' => 'business.bulk.create', 'icon' => 'upload', 'match' => 'business.bulk.*'];
        }
        $items[] = ['label' => 'Shipments', 'route' => 'business.shipments.index', 'icon' => 'package', 'match' => 'business.shipments.*'];
        if ($m?->can('create_shipments')) {
            $items[] = ['label' => 'Pickup addresses', 'route' => 'business.addresses.index', 'icon' => 'map-pin', 'match' => 'business.addresses.*'];
        }
        if ($m?->can('view_invoices')) {
            $items[] = ['label' => 'Invoices & payments', 'route' => 'business.invoices.index', 'icon' => 'receipt', 'match' => 'business.invoices.*'];
        }
        if ($m?->can('view_reports')) {
            $items[] = ['label' => 'Reports', 'route' => 'business.reports', 'icon' => 'chart'];
        }
        if ($m?->can('manage_team')) {
            $items[] = ['label' => 'Team & permissions', 'route' => 'business.team.index', 'icon' => 'users', 'match' => 'business.team.*'];
        }
        if ($m?->can('manage_company')) {
            $items[] = ['label' => 'Company profile', 'route' => 'business.profile', 'icon' => 'building'];
        }

        return $items;
    }

    private static function ops(?User $user): array
    {
        $items = [
            ['label' => 'Operations', 'route' => 'ops.dashboard', 'icon' => 'home'],
            ['label' => 'Shipments', 'route' => 'ops.shipments.index', 'icon' => 'package', 'match' => 'ops.shipments.*'],
            ['label' => 'Dispatch board', 'route' => 'ops.dispatch', 'icon' => 'route'],
            ['label' => 'Rider map', 'route' => 'ops.map', 'icon' => 'map'],
            ['label' => 'Exceptions & returns', 'route' => 'ops.exceptions', 'icon' => 'alert'],
            ['label' => 'Payments', 'route' => 'ops.payments.index', 'icon' => 'credit-card', 'match' => 'ops.payments.*'],
            ['label' => 'Cash on delivery', 'route' => 'ops.cod.index', 'icon' => 'wallet', 'match' => 'ops.cod.*'],
            ['label' => 'Support tickets', 'route' => 'ops.support.index', 'icon' => 'life-buoy', 'match' => 'ops.support.*'],
            ['label' => 'Customers', 'route' => 'ops.customers.index', 'icon' => 'users', 'match' => 'ops.customers.*'],
            ['label' => 'Businesses', 'route' => 'ops.businesses.index', 'icon' => 'building', 'match' => 'ops.businesses.*'],
            ['label' => 'Riders', 'route' => 'ops.riders.index', 'icon' => 'bike', 'match' => 'ops.riders.*'],
            ['label' => 'Reports', 'route' => 'ops.reports', 'icon' => 'chart'],
            ['label' => 'Notifications log', 'route' => 'ops.notifications', 'icon' => 'bell'],
        ];
        if ($user?->isRole('admin')) {
            $items = array_merge($items, [
                ['label' => 'Services', 'route' => 'admin.resource.index', 'params' => ['resource' => 'services'], 'icon' => 'layers', 'section' => 'Configuration'],
                ['label' => 'Pricing rules', 'route' => 'admin.resource.index', 'params' => ['resource' => 'pricing-rules'], 'icon' => 'tag'],
                ['label' => 'Coverage zones', 'route' => 'admin.resource.index', 'params' => ['resource' => 'zones'], 'icon' => 'globe'],
                ['label' => 'Branches', 'route' => 'admin.resource.index', 'params' => ['resource' => 'branches'], 'icon' => 'warehouse'],
                ['label' => 'Message templates', 'route' => 'admin.resource.index', 'params' => ['resource' => 'templates'], 'icon' => 'mail'],
                ['label' => 'Website content', 'route' => 'admin.resource.index', 'params' => ['resource' => 'content'], 'icon' => 'file'],
                ['label' => 'FAQs', 'route' => 'admin.resource.index', 'params' => ['resource' => 'faqs'], 'icon' => 'info'],
                ['label' => 'Users & roles', 'route' => 'admin.users.index', 'icon' => 'shield', 'match' => 'admin.users.*'],
                ['label' => 'Status workflow', 'route' => 'admin.workflow', 'icon' => 'route'],
                ['label' => 'Audit log', 'route' => 'admin.audit', 'icon' => 'history'],
                ['label' => 'System settings', 'route' => 'admin.settings', 'icon' => 'settings'],
            ]);
        }

        return $items;
    }
}
