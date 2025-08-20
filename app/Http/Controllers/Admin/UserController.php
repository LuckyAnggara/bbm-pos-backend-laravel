<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['tenant:id,name', 'branch:id,name'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($request->role, function ($query, $role) {
                $query->where('role', $role);
            })
            ->when($request->tenant_id, function ($query, $tenantId) {
                $query->where('tenant_id', $tenantId);
            });

        $users = $query->latest()->paginate(15);
        $tenants = Tenant::select('id', 'name')->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'role', 'tenant_id'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:admin,cashier,manager,viewer',
            'tenant_id' => 'required|exists:tenants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'is_tenant_owner' => 'boolean',
        ]);

        // Generate temporary password
        $tempPassword = \Str::random(12);
        
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($tempPassword),
            'role' => $validated['role'],
            'tenant_id' => $validated['tenant_id'],
            'branch_id' => $validated['branch_id'],
            'is_tenant_owner' => $validated['is_tenant_owner'] ?? false,
        ]);

        // TODO: Send invitation email with temporary password
        
        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully. Invitation email sent.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:admin,cashier,manager,viewer',
            'tenant_id' => 'required|exists:tenants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'is_tenant_owner' => 'boolean',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        // Prevent deletion of super admin users
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Cannot delete super admin users');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully');
    }
}