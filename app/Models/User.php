<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'tenant_id',
        'user_type',
        'is_tenant_owner',
        'avatar_url',
        'local_printer_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_tenant_owner' => 'boolean',
    ];

    /**
     * Get the tenant that the user belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the branch that the user belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    /**
     * Check if user is a tenant admin.
     */
    public function isTenantAdmin(): bool
    {
        return $this->user_type === 'tenant_admin';
    }

    /**
     * Check if user is a tenant owner.
     */
    public function isTenantOwner(): bool
    {
        return $this->is_tenant_owner;
    }

    /**
     * Check if user has admin privileges.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']) || 
               $this->isSuperAdmin() || 
               $this->isTenantAdmin();
    }

    /**
     * Check if user can access admin panel.
     */
    public function canAccessAdminPanel(): bool
    {
        return $this->isSuperAdmin() || $this->isTenantAdmin() || $this->role === 'admin';
    }
}
