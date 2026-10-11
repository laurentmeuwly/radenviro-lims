<?php

namespace App\Console\Commands;

use App\Services\Nadam\NadamImporter;
use Illuminate\Console\Command;

class NadamPurge extends Command
{
    protected $signature = 'nadam:purge';

    protected $description = 'Supprime les fichiers NADAM traités (processed/) plus anciens que la durée de conservation';

    public function handle(NadamImporter $importer)
    {
        $deleted = $importer->purge();
        $this->line(sprintf('%d fichier(s) supprimé(s).', $deleted));

        return Command::SUCCESS;
    }
}
