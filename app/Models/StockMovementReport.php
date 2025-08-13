<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovementReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'product_id',
        'start_date',
        'end_date',
        'data',
        'generated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'generated_at' => 'datetime',
    ];
}
