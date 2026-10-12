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
 *
 * Données géographiques : le fichier NADAM fait foi. Latitude, longitude et altitude
 * reçues écrasent celles de la station (une fois par station et par fichier), sauf valeur
 * absente ou invalide dans le fichier, auquel cas la valeur existante est conservée.
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

    /** @var array<int, true> stations dont la géographie a déjà été synchronisée pour ce fichier */
    private $geoSynced = [];

    /** @var string[] mises à jour géographiques effectuées (ou prévues en dry-run) */
    private $geoUpdates = [];

    /** écart sous lequel deux coordonnées sont considérées identiques (évite des écritures inutiles) */
    private const COORDINATE_EPSILON = 1e-9;

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
            ->get(['id', 'code', 'external_id', 'latitude', 'longitude', 'altitude']);

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

        $this->syncGeography($station, $reading);

        return (int) $station->id;
    }

    private function syncGeography($station, NadamReading $reading): void
    {
        if (isset($this->geoSynced[$station->id])) {
            return;
        }
        $this->geoSynced[$station->id] = true;

        $changes = [];
        $labels = [];

        foreach (['latitude', 'longitude'] as $field) {
            $new = $reading->$field;
            if (null !== $new && (null === $station->$field || abs((float) $station->$field - (float) $new) > self::COORDINATE_EPSILON)) {
                $changes[$field] = $new;
                $labels[] = sprintf('%s %s → %s', $field, $this->display($station->$field), $new);
            }
        }

        if (null !== $reading->altitude && (null === $station->altitude || (int) $station->altitude !== $reading->altitude)) {
            $changes['altitude'] = $reading->altitude;
            $labels[] = sprintf('altitude %s → %d', $this->display($station->altitude), $reading->altitude);
        }

        if ([] === $changes) {
            return;
        }

        if (!$this->dryRun) {
            AutomaticNetworkStation::query()->whereKey($station->id)->update($changes);
        }
        foreach ($changes as $field => $value) {
            $station->$field = $value;
        }

        $this->geoUpdates[] = sprintf('station %s : %s', $reading->stationCode, implode(', ', $labels));
    }

    private function display($value): string
    {
        return null === $value ? '—' : rtrim(rtrim((string) $value, '0'), '.');
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

    /** @return string[] */
    public function geoUpdates(): array
    {
        return $this->geoUpdates;
    }
}
