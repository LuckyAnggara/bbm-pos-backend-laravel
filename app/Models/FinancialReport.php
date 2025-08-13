<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'report_type',
        'start_date',
        'end_date',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
