<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'title',
        'message',
        'category',
        'link_url',
        'is_read',
        'is_dismissed',
        'read_at',
        'dismissed_at',
        'created_by',
        'created_by_name'
    ];
}
