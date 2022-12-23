<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResultUnit extends Model
{
    use HasFactory;

    protected $connection = 'radenviro';
    protected $table = 'result_unit';
}
