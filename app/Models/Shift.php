<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'start_shift',
        'starting_balance',
        'total_sales',
        'total_cash_payments',
        'total_other_payments',
        'total_bank_payments',
        'total_credit_payments',
        'total_card_payments',
        'total_qris_payments',
        'branch_id',
        'user_id',
        'user_name',
        'discount_amount',
        'end_shift',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_shift' => 'datetime',
        'end_shift' => 'datetime',
        'starting_balance' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_cash_payments' => 'decimal:2',
        'total_other_payments' => 'decimal:2',
        'total_bank_payments' => 'decimal:2',
        'total_credit_payments' => 'decimal:2',
        'total_card_payments' => 'decimal:2',
        'total_qris_payments' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    /**
     * Get the user who owns the shift.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch where the shift occurred.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all of the sales for the shift.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
