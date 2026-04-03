<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NSEDownload extends Model
{
    use HasFactory;

    protected $table = 'nse_downloads';

    protected $fillable = ['url'];
}
