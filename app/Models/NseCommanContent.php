<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NseCommanContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'segment', 'parent_folder', 'name', 'type', 'path', 'size', 'is_downloaded', 'download_attempts', 'nse_created_at', 'nse_modified_at'
    ];

    protected $casts = [
        'nse_created_at' => 'datetime',
        'nse_modified_at' => 'datetime'
    ];
}
