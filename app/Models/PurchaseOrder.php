<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'po_number',
        'branch_id',
        'supplier_id',
        'supplier_name',
        'order_date',
        'expected_delivery_date',
        'payment_due_date',
        'notes',
        'user_id',
        'is_credit_purchase',
        'payment_terms',
        'supplier_invoice_number',
        'payment_status',
        'status',
        'subtotal',
        'tax_discount_amount',
        'shipping_cost_charged',
        'other_costs',
        'total_amount',
        'outstanding_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'order_date' => 'datetime',
        'expected_delivery_date' => 'datetime',
        'payment_due_date' => 'datetime',
        'is_credit_purchase' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_discount_amount' => 'decimal:2',
        'shipping_cost_charged' => 'decimal:2',
        'other_costs' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    /**
     * Get the user who created the purchase order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch that owns the purchase order.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the supplier for the purchase order.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }


    /**
     * Get the details for the purchase order.
     */
    public function purchaseOrderDetails(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }
}
