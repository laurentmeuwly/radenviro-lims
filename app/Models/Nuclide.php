<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nuclide extends Model
{
    use HasFactory;

    protected $connection = 'radenviro';
    protected $table = 'nuclide';
}
