<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'product_id',
        'branch_id',
        'product_name',
        'system_quantity',
        'counted_quantity',
        'difference',
        'notes'
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockOpnameSession::class, 'session_id');
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
