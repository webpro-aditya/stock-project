<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NseContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'segment', 'parent_folder', 'name', 'type', 'path', 'size', 'nse_created_at', 'nse_modified_at'
    ];

    protected $casts = [
        'nse_created_at' => 'datetime',
        'nse_modified_at' => 'datetime'
    ];
}
