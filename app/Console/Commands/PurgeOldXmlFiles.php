<?php

namespace App\Console\Commands;

use App\Services\DirectoryParser;
use Illuminate\Console\Command;

class PurgeOldXmlFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lims:purge-xml';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $parser = new DirectoryParser();
        $parser->purgeDir();
        return Command::SUCCESS;
    }
}
