<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        // Super admins can access without tenant context
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        // For authenticated users, ensure they have a tenant
        if ($user && !$user->tenant_id) {
            return response()->json([
                'message' => 'User must be associated with a tenant'
            ], 403);
        }

        // Get tenant from user context
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Invalid tenant'
                ], 403);
            }

            // Check if tenant is active
            if ($tenant->status !== 'active') {
                return response()->json([
                    'message' => 'Tenant account is not active'
                ], 403);
            }

            // Add tenant to request for use in controllers
            $request->merge(['current_tenant' => $tenant]);
        }

        return $next($request);
    }
}
