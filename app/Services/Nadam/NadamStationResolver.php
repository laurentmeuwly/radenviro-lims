<?php

namespace App\Services\Nadam;

use App\Models\AutomaticNetworkStation;

/**
 * Rattache une mesure NADAM à une ligne de automatic_network_station (réseau NADAM uniquement).
 *
 * Ordre de résolution :
 *  1. par external_id (= odl_station_id) — clé stable une fois connue ;
 *  2. sinon par code (= odl_station_code, saisi dans l'admin) sur une station dont
 *     external_id est encore vide → external_id est alors renseigné (premier rattachement).
 *
 * Une station inconnue n'est jamais créée automatiquement : la création reste un acte
 * d'administration (groupe « nadam » dans l'admin Radenviro).
 */
class NadamStationResolver
{
    /** @var int */
    private $networkId;

    /** @var bool */
    private $dryRun;

    /** @var array<int, object> external_id => station */
    private $byExternalId = [];

    /** @var array<string, object> code => station (sans external_id) */
    private $byCode = [];

    /** @var array<string, string> anomalies de rattachement, dédoublonnées par station */
    private $warnings = [];

    /** @var string[] odl_station_id rattachés pour la première fois */
    private $linked = [];

    public function __construct(int $networkId, bool $dryRun = false)
    {
        $this->networkId = $networkId;
        $this->dryRun = $dryRun;
        $this->load();
    }

    private function load(): void
    {
        $stations = AutomaticNetworkStation::query()
            ->where('automatic_network_id', $this->networkId)
            ->get(['id', 'code', 'external_id', 'latitude', 'longitude']);

        foreach ($stations as $station) {
            if (null !== $station->external_id) {
                $this->byExternalId[(int) $station->external_id] = $station;
            } elseif (null !== $station->code && '' !== $station->code) {
                $this->byCode[strtoupper($station->code)] = $station;
            }
        }
    }

    /**
     * @return int|null id de automatic_network_station, ou null si la station est inconnue
     */
    public function resolve(NadamReading $reading): ?int
    {
        $externalId = $reading->stationExternalId;

        if (isset($this->byExternalId[$externalId])) {
            $station = $this->byExternalId[$externalId];

            if (null !== $station->code && strtoupper($station->code) !== $reading->stationCode) {
                $this->warn($externalId, sprintf(
                    'station %d : code reçu « %s » ≠ code Radenviro « %s » (rattachement par identifiant conservé)',
                    $externalId, $reading->stationCode, $station->code
                ));
            }
        } elseif (isset($this->byCode[$reading->stationCode])) {
            $station = $this->byCode[$reading->stationCode];

            if (!$this->dryRun) {
                AutomaticNetworkStation::query()
                    ->whereKey($station->id)
                    ->whereNull('external_id')
                    ->update(['external_id' => $externalId]);
            }
            $station->external_id = $externalId;

            unset($this->byCode[$reading->stationCode]);
            $this->byExternalId[$externalId] = $station;
            $this->linked[] = sprintf('%s → %d', $reading->stationCode, $externalId);
        } else {
            $this->warn($externalId, sprintf(
                'station inconnue %s « %s » (odl_station_id %d) : mesures ignorées — à créer dans l\'admin avec le code %s',
                $reading->stationCode, $reading->stationName, $externalId, $reading->stationCode
            ));

            return null;
        }

        $this->checkCoordinates($station, $reading);

        return (int) $station->id;
    }

    private function checkCoordinates($station, NadamReading $reading): void
    {
        if (null === $reading->latitude || null === $reading->longitude) {
            return;
        }

        $tolerance = (float) config('nadam.coordinate_tolerance');
        $dLat = abs((float) $station->latitude - $reading->latitude);
        $dLon = abs((float) $station->longitude - $reading->longitude);

        if ($dLat > $tolerance || $dLon > $tolerance) {
            $this->warn('coord-'.$reading->stationExternalId, sprintf(
                'station %s : coordonnées reçues (%s, %s) ≠ Radenviro (%s, %s)',
                $reading->stationCode, $reading->latitude, $reading->longitude, $station->latitude, $station->longitude
            ));
        }
    }

    private function warn($key, string $message): void
    {
        $this->warnings[(string) $key] = $message;
    }

    /** @return string[] */
    public function warnings(): array
    {
        return array_values($this->warnings);
    }

    /** @return string[] */
    public function linked(): array
    {
        return $this->linked;
    }
}
