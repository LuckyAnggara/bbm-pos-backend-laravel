<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'year',
        'type',
        'quantity',
        'cost_price',
        'value_amount',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'cost_price' => 'decimal:2',
        'value_amount' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
