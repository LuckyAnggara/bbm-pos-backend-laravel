<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'base_salary',
        'meal_allowance',
        'bonus',
        'overtime_amount',
        'loan_deduction',
        'other_deduction',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
        'bonus' => 'decimal:2',
        'overtime_amount' => 'decimal:2',
        'loan_deduction' => 'decimal:2',
        'other_deduction' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
