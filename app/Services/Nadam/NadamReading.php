<?php

namespace App\Services\Nadam;

/**
 * Une moyenne NADAM validée et normalisée, prête à être rattachée à une station.
 */
final class NadamReading
{
    /** @var int odl_station_id */
    public $stationExternalId;

    /** @var string odl_station_code (majuscules) */
    public $stationCode;

    /** @var string odl_station_name */
    public $stationName;

    /** @var string horodatage UTC « Y-m-d H:i:s » */
    public $measuredAtUtc;

    /** @var string horodatage NAZ d'origine (avec offset), pour les logs */
    public $sourceDate;

    /** @var string type de moyenne Radenviro (ex. « hourly ») */
    public $averageType;

    /** @var string valeur en nSv/h, sous forme décimale (pas de float) */
    public $value;

    /** @var string|null précipitations en mm ; null = absent (≠ « 0 ») */
    public $precipitation;

    /** @var float|null */
    public $latitude;

    /** @var float|null */
    public $longitude;

    /** @var string[] anomalies non bloquantes */
    public $warnings = [];

    /**
     * Clé d'unicité métier (avant résolution de la station).
     */
    public function key(): string
    {
        return $this->stationExternalId.'|'.$this->averageType.'|'.$this->measuredAtUtc;
    }
}
