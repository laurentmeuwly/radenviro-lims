<?php

namespace App\Services\Nadam;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Workflow d'import des fichiers NADAM (GeoJSON) — indépendant du workflow LIMS (XML).
 *
 * Pour chaque fichier NAZ-YYYYMMDD-HHMMSS.json stable (transfert SCP terminé),
 * traité par ordre chronologique de nom (un renvoi plus récent corrige un plus ancien) :
 *  - fichier illisible / pas une FeatureCollection → error/
 *  - erreur technique (base indisponible...) → fichier laissé en place, retenté au passage suivant
 *  - sinon chaque feature est validée ; les features invalides ou de stations inconnues
 *    sont ignorées et journalisées, les autres sont écrites par upsert dans une transaction
 *  - fichier traité → processed/
 */
class NadamImporter
{
    private const TABLE = 'automatic_network_measurement';
    private const CONNECTION = 'radenviro';

    /** @var array */
    private $config;

    /** @var NadamFeatureParser */
    private $parser;

    /** @var Filesystem */
    private $disk;

    public function __construct()
    {
        $this->config = config('nadam');
        $this->parser = new NadamFeatureParser($this->config);
        $this->disk = Storage::disk('nadam');
    }

    /**
     * @return array<int, array> un rapport par fichier traité
     */
    public function run(bool $dryRun = false): array
    {
        $reports = [];

        foreach ($this->pendingFiles() as $file) {
            $reports[] = $this->processFile($file, $dryRun);
        }

        return $reports;
    }

    /**
     * Fichiers à la racine du disque, au bon format de nom, stables, triés chronologiquement.
     *
     * @return string[]
     */
    public function pendingFiles(): array
    {
        $minAge = (int) $this->config['min_file_age_seconds'];
        $files = [];

        foreach ($this->disk->files() as $file) {
            if (1 !== preg_match($this->config['file_pattern'], basename($file))) {
                continue;
            }
            if (time() - $this->disk->lastModified($file) < $minAge) {
                continue; // probablement en cours de transfert, sera repris au prochain passage
            }
            $files[] = $file;
        }

        sort($files, SORT_STRING); // NAZ-YYYYMMDD-HHMMSS : ordre alphabétique = ordre chronologique

        return $files;
    }

    public function processFile(string $file, bool $dryRun = false): array
    {
        $report = [
            'file' => $file,
            'status' => 'processed',
            'features' => 0,
            'written' => 0,
            'rejected' => 0,
            'unknown_station' => 0,
            'linked' => [],
            'warnings' => [],
            'error' => null,
        ];

        try {
            $features = $this->readFeatures($file);
            $report['features'] = \count($features);

            $resolver = new NadamStationResolver((int) $this->config['automatic_network_id'], $dryRun);

            $write = function () use ($features, $resolver, $file, $dryRun, &$report) {
                $rows = [];
                $now = now('UTC')->format('Y-m-d H:i:s');

                foreach ($features as $index => $feature) {
                    try {
                        $reading = $this->parser->parse($feature);
                    } catch (InvalidNadamFeature $e) {
                        ++$report['rejected'];
                        $report['warnings'][] = sprintf('feature #%d rejetée : %s', $index, $e->getMessage());
                        continue;
                    }

                    foreach ($reading->warnings as $warning) {
                        $report['warnings'][] = $warning;
                    }

                    $stationId = $resolver->resolve($reading);
                    if (null === $stationId) {
                        ++$report['unknown_station'];
                        continue;
                    }

                    // dédoublonnage dans le fichier : la dernière occurrence l'emporte
                    $rows[$stationId.'|'.$reading->averageType.'|'.$reading->measuredAtUtc] = [
                        'automatic_network_station_id' => $stationId,
                        'measured_at' => $reading->measuredAtUtc,
                        'average_type' => $reading->averageType,
                        'value' => $reading->value,
                        'precipitation' => $reading->precipitation,
                        'source_file' => basename($file),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $report['written'] = \count($rows);
                $report['linked'] = $resolver->linked();
                $report['warnings'] = array_merge($report['warnings'], $resolver->warnings());

                if ($dryRun) {
                    return;
                }

                foreach (array_chunk(array_values($rows), (int) $this->config['chunk_size']) as $chunk) {
                    DB::connection(self::CONNECTION)->table(self::TABLE)->upsert(
                        $chunk,
                        ['automatic_network_station_id', 'average_type', 'measured_at'],
                        ['value', 'precipitation', 'source_file', 'updated_at']
                    );
                }
            };

            if ($dryRun) {
                $write();
            } else {
                DB::connection(self::CONNECTION)->transaction($write);
                $this->disk->move($file, 'processed/'.basename($file));
            }
        } catch (InvalidNadamFile $e) {
            // fichier inexploitable : écarté, à examiner manuellement
            $report['status'] = 'error';
            $report['error'] = $e->getMessage();

            if (!$dryRun) {
                $this->disk->move($file, 'error/'.basename($file));
            }
        } catch (\Throwable $e) {
            // erreur technique (base indisponible...) : transaction annulée, fichier laissé
            // en place pour être retenté au prochain passage
            $report['status'] = 'retry';
            $report['error'] = $e->getMessage();
        }

        if (!$dryRun) {
            $this->log($report);
        }

        return $report;
    }

    /**
     * @throws InvalidNadamFile
     */
    private function readFeatures(string $file): array
    {
        $content = $this->disk->get($file);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidNadamFile('JSON invalide : '.$e->getMessage());
        }

        if (!\is_array($data) || ($data['type'] ?? null) !== 'FeatureCollection' || !isset($data['features']) || !\is_array($data['features'])) {
            throw new InvalidNadamFile('pas une FeatureCollection GeoJSON');
        }

        return $data['features'];
    }

    private function log(array $report): void
    {
        $logger = Log::channel('nadam');
        $context = array_intersect_key($report, array_flip(['features', 'written', 'rejected', 'unknown_station']));

        if ('error' === $report['status']) {
            $logger->error(sprintf('%s → error/ : %s', $report['file'], $report['error']));

            return;
        }
        if ('retry' === $report['status']) {
            $logger->error(sprintf('%s laissé en place (sera retenté) : %s', $report['file'], $report['error']));

            return;
        }

        $logger->info(sprintf('%s → processed/', $report['file']), $context);

        foreach ($report['linked'] as $link) {
            $logger->notice('station rattachée par code : '.$link);
        }
        foreach ($report['warnings'] as $warning) {
            $logger->warning($warning);
        }
    }

    /**
     * Supprime les fichiers de processed/ plus vieux que keep_processed_days.
     * Les fichiers en error/ ne sont jamais purgés automatiquement.
     */
    public function purge(): int
    {
        $limit = now()->subDays((int) $this->config['keep_processed_days'])->getTimestamp();
        $deleted = 0;

        foreach ($this->disk->files('processed') as $file) {
            if ($this->disk->lastModified($file) < $limit) {
                $this->disk->delete($file);
                ++$deleted;
            }
        }

        return $deleted;
    }
}
