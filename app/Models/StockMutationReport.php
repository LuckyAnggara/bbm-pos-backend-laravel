<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutationReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'start_date',
        'end_date',
        'data',
        'generated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'data' => 'array',
        'generated_at' => 'datetime',
    ];
}
