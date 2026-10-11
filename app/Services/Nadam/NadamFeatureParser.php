<?php

namespace App\Services\Nadam;

/**
 * Valide et normalise une feature GeoJSON NADAM. Aucune dépendance base de données.
 *
 * Points d'attention :
 * - average_date est en heure locale suisse AVEC offset explicite (+01:00 / +02:00).
 *   On exige l'offset et on convertit en UTC : à la nuit du changement d'heure d'octobre,
 *   « 02:00+02:00 » et « 02:00+01:00 » sont deux instants distincts et le restent.
 * - value et precipitation sont conservés en chaîne décimale (pas de passage par float).
 * - precipitation null reste null (donnée absente), 0 reste « 0 » (pas de pluie).
 */
class NadamFeatureParser
{
    private const DATE_FORMATS = [
        'Y-m-d\TH:i:s.vP',   // 2026-09-07T12:00:00.000+02:00 (format NAZ actuel)
        'Y-m-d\TH:i:sP',     // 2026-09-07T12:00:00+02:00
    ];

    private const DECIMAL_PATTERN = '/^\d{1,15}(\.\d{1,3})?$/';

    /** @var array */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param mixed $feature élément de « features » (tableau associatif)
     *
     * @throws InvalidNadamFeature
     */
    public function parse($feature): NadamReading
    {
        if (!\is_array($feature) || !isset($feature['properties']) || !\is_array($feature['properties'])) {
            throw new InvalidNadamFeature('feature sans « properties »');
        }
        $p = $feature['properties'];

        $reading = new NadamReading();

        // --- identification de la station
        if (!isset($p['odl_station_id']) || (!\is_int($p['odl_station_id']) && !ctype_digit((string) $p['odl_station_id']))) {
            throw new InvalidNadamFeature('odl_station_id manquant ou invalide');
        }
        $reading->stationExternalId = (int) $p['odl_station_id'];

        $code = isset($p['odl_station_code']) ? strtoupper(trim((string) $p['odl_station_code'])) : '';
        if ('' === $code) {
            throw new InvalidNadamFeature(sprintf('odl_station_code manquant (station %d)', $reading->stationExternalId));
        }
        $reading->stationCode = $code;
        $reading->stationName = isset($p['odl_station_name']) ? (string) $p['odl_station_name'] : $code;

        $label = sprintf('%s (%d)', $code, $reading->stationExternalId);

        // --- contrôles statiques
        $networkName = $p['odl_network_name'] ?? null;
        if ($networkName !== $this->config['network_name']) {
            throw new InvalidNadamFeature(sprintf('%s : réseau inattendu « %s »', $label, (string) $networkName));
        }

        $unit = $p['value_unit'] ?? null;
        if ($unit !== $this->config['value_unit']) {
            throw new InvalidNadamFeature(sprintf('%s : unité inattendue « %s »', $label, (string) $unit));
        }

        $sourceType = $p['average_type'] ?? null;
        if (!\is_string($sourceType) || !isset($this->config['average_types'][$sourceType])) {
            throw new InvalidNadamFeature(sprintf('%s : average_type non géré « %s »', $label, (string) $sourceType));
        }
        $reading->averageType = $this->config['average_types'][$sourceType];

        // --- horodatage
        $reading->sourceDate = isset($p['average_date']) ? (string) $p['average_date'] : '';
        $reading->measuredAtUtc = $this->parseDate($reading->sourceDate, $label);

        // --- valeur (obligatoire, >= 0)
        $value = $this->normalizeDecimal($p['value'] ?? null);
        if (null === $value) {
            throw new InvalidNadamFeature(sprintf('%s @ %s : value manquante ou invalide', $label, $reading->sourceDate));
        }
        $reading->value = $value;

        // --- précipitations (facultatives) : une anomalie n'invalide pas la mesure de dose
        if (!array_key_exists('precipitation', $p) || null === $p['precipitation']) {
            $reading->precipitation = null;
        } else {
            $precipitation = $this->normalizeDecimal($p['precipitation']);
            if (null === $precipitation) {
                $reading->warnings[] = sprintf('%s @ %s : precipitation invalide « %s », enregistrée comme absente',
                    $label, $reading->sourceDate, json_encode($p['precipitation']));
            }
            $reading->precipitation = $precipitation;
        }

        // --- coordonnées (contrôle uniquement)
        $reading->latitude = isset($p['latitude']) && is_numeric($p['latitude']) ? (float) $p['latitude'] : null;
        $reading->longitude = isset($p['longitude']) && is_numeric($p['longitude']) ? (float) $p['longitude'] : null;

        return $reading;
    }

    private function parseDate(string $raw, string $label): string
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $raw);
            $errors = \DateTimeImmutable::getLastErrors();
            $hasErrors = \is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);

            if (false !== $date && !$hasErrors) {
                return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }

        throw new InvalidNadamFeature(sprintf('%s : average_date invalide ou sans offset « %s »', $label, $raw));
    }

    /**
     * Retourne une chaîne décimale >= 0 (max. 3 décimales) ou null si invalide.
     *
     * @param mixed $raw
     */
    private function normalizeDecimal($raw): ?string
    {
        if (\is_int($raw)) {
            $raw = (string) $raw;
        } elseif (\is_float($raw)) {
            $raw = rtrim(rtrim(sprintf('%.3F', $raw), '0'), '.');
        }

        if (!\is_string($raw)) {
            return null;
        }

        $raw = trim($raw);

        return 1 === preg_match(self::DECIMAL_PATTERN, $raw) ? $raw : null;
    }
}
