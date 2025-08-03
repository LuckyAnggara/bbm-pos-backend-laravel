<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sale_id',
        'branch_id',
        'product_id',
        'product_name',
        'branch_name',
        'sku',
        'quantity',
        'price_at_sale',
        'cost_at_sale',
        'discount_amount',
        'subtotal',
        'category_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'price_at_sale' => 'double',
        'cost_at_sale' => 'double',
        'discount_amount' => 'double',
        'subtotal' => 'double',
    ];

    /**
     * Get the main sale transaction header.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product associated with this detail.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
