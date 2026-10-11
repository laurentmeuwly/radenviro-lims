<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Station de réseau automatique (table gérée par Symfony/Doctrine — ne pas migrer ici).
 */
class AutomaticNetworkStation extends Model
{
    protected $connection = 'radenviro';
    protected $table = 'automatic_network_station';
}
