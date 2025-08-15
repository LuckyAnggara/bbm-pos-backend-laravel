<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CustomerPayment;

class Sale extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_number',
        'notes',
        'status',
        'branch_id',
        'user_id',
        'shift_id',
        'customer_id',
        'user_name',
        'customer_name',
        'subtotal',
        'total_discount_amount',
        'tax_amount',
        'shipping_cost',
        'total_amount',
        'total_cogs',
        'payment_method',
        'payment_status',
        'amount_paid',
        'change_given',
        'items_discount_amount',
        'voucher_code',
        'voucher_discount_amount',
        'is_credit_sale',
        'credit_due_date',
        'outstanding_amount',
        'bank_transaction_ref',
        'bank_name',
        'returned_at',
        'returned_reason',
        'returned_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'double',
        'total_discount_amount' => 'double',
        'tax_amount' => 'double',
        'shipping_cost' => 'double',
        'total_amount' => 'double',
        'total_cogs' => 'double',
        'amount_paid' => 'double',
        'change_given' => 'double',
        'items_discount_amount' => 'double',
        'voucher_discount_amount' => 'double',
        'outstanding_amount' => 'double',
        'is_credit_sale' => 'boolean',
        'credit_due_date' => 'datetime',
        'returned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the details for the sale.
     * This is the list of items sold.
     */
    public function saleDetails(): HasMany
    {
        // Asumsi nama modelnya adalah SaleDetail
        return $this->hasMany(SaleDetail::class);
    }

    /**
     * Payments recorded toward this credit sale.
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the shift where this sale occurred.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
