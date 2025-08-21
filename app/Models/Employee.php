<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'employee_code',
        'name',
        'email',
        'phone',
        'address',
        'position',
        'is_sales',
        'employment_type',
        'daily_salary',
        'monthly_salary',
        'daily_meal_allowance',
        'monthly_meal_allowance',
        'bonus',
        'hire_date',
        'termination_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'daily_salary' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'daily_meal_allowance' => 'decimal:2',
        'monthly_meal_allowance' => 'decimal:2',
        'bonus' => 'decimal:2',
        'is_sales' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function savings(): HasMany
    {
        return $this->hasMany(EmployeeSavings::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'sales_id');
    }

    public function activeLoan()
    {
        return $this->loans()->where('status', 'active')->first();
    }

    public function totalSavings()
    {
        $deposits = $this->savings()->where('type', 'deposit')->sum('amount');
        $withdrawals = $this->savings()->where('type', 'withdrawal')->sum('amount');

        return $deposits - $withdrawals;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($employee) {
            if (empty($employee->employee_code)) {
                $employee->employee_code = self::generateEmployeeCode($employee->branch_id);
            }
        });
    }

    private static function generateEmployeeCode($branchId): string
    {
        $prefix = 'EMP';
        $branchCode = str_pad($branchId, 2, '0', STR_PAD_LEFT);
        $count = self::where('branch_id', $branchId)->count() + 1;
        $sequence = str_pad($count, 4, '0', STR_PAD_LEFT);

        return $prefix.$branchCode.$sequence;
    }
}
