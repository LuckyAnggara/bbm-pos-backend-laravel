<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id',
        'branch_id',
        'product_id',
        'product_name',
        'ordered_quantity',
        'received_quantity',
        'received_date',
        'purchase_price',
        'total_price',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordered_quantity' => 'integer',
        'received_quantity' => 'integer',
        'received_date' => 'datetime',
        'purchase_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the main purchase order header.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the product for this detail line.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
