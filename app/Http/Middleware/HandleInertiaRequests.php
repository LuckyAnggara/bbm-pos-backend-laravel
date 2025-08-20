<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'is_super_admin' => $request->user()->isSuperAdmin(),
                    'is_tenant_admin' => $request->user()->isTenantAdmin(),
                    'is_tenant_owner' => $request->user()->isTenantOwner(),
                    'tenant_id' => $request->user()->tenant_id,
                ] : null,
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
                'info' => session('info'),
                'warning' => session('warning'),
            ],
            'tenants' => $request->user() && $request->user()->isSuperAdmin() 
                ? \App\Models\Tenant::select('id', 'name')->get() 
                : null,
            'current_tenant' => session('selected_tenant_id') 
                ? \App\Models\Tenant::find(session('selected_tenant_id'))
                : null,
        ]);
    }
}