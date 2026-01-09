<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    protected $casts = [
        'published_at' => 'immutable_datetime',
        'data' => 'array',
    ];
}
