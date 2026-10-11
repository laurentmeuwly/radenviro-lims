<?php

namespace App\Console\Commands;

use App\Services\Nadam\NadamImporter;
use Illuminate\Console\Command;

class NadamParse extends Command
{
    protected $signature = 'nadam:parse
                            {--dry-run : Analyse les fichiers sans rien écrire en base ni déplacer}
                            {--file= : Ne traiter que ce fichier (nom, à la racine du dossier NADAM)}';

    protected $description = 'Importe les fichiers GeoJSON NADAM (moyennes horaires de débit de dose)';

    public function handle(NadamImporter $importer)
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($file = $this->option('file')) {
            $reports = [$importer->processFile($file, $dryRun)];
        } else {
            $reports = $importer->run($dryRun);
        }

        if ([] === $reports) {
            $this->line('Aucun fichier NADAM à traiter.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Fichier', 'Statut', 'Features', 'Écrites', 'Rejetées', 'Stations inconnues'],
            array_map(function (array $r) {
                return [$r['file'], $r['status'], $r['features'], $r['written'], $r['rejected'], $r['unknown_station']];
            }, $reports)
        );

        foreach ($reports as $r) {
            if (null !== $r['error']) {
                $this->error($r['file'].' : '.$r['error']);
            }
            foreach ($r['linked'] as $link) {
                $this->info('Rattachée : '.$link);
            }
            if ($this->getOutput()->isVerbose() || $dryRun) {
                foreach ($r['warnings'] as $warning) {
                    $this->warn($warning);
                }
            } elseif ([] !== $r['warnings']) {
                $this->warn(sprintf('%s : %d avertissement(s), voir storage/logs/nadam-*.log ou relancer avec -v', $r['file'], \count($r['warnings'])));
            }
        }

        if ($dryRun) {
            $this->comment('Mode --dry-run : aucune écriture, aucun fichier déplacé.');
        }

        foreach ($reports as $r) {
            if ('processed' !== $r['status']) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
