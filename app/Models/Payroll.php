<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'payroll_code',
        'title',
        'description',
        'payment_type',
        'payment_date',
        'period_start',
        'period_end',
        'total_amount',
        'notes',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'payroll_details')
            ->withPivot([
                'base_salary',
                'meal_allowance',
                'bonus',
                'overtime_amount',
                'loan_deduction',
                'other_deduction',
                'total_amount',
                'notes',
            ])
            ->withTimestamps();
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payroll) {
            if (empty($payroll->payroll_code)) {
                $payroll->payroll_code = self::generatePayrollCode($payroll->branch_id);
            }
        });
    }

    private static function generatePayrollCode($branchId): string
    {
        $prefix = 'PAY';
        $branchCode = str_pad($branchId, 2, '0', STR_PAD_LEFT);
        $date = date('Ymd');
        $count = self::where('branch_id', $branchId)
            ->whereDate('created_at', today())
            ->count() + 1;
        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);

        return $prefix.$branchCode.$date.$sequence;
    }
}
