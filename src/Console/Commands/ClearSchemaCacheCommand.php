<?php

namespace PawelJadanowski\ScrambleExtras\Console\Commands;

use Illuminate\Console\Command;
use PawelJadanowski\ScrambleExtras\SchemaCache;

class ClearSchemaCacheCommand extends Command
{
    protected $signature = 'scramble-extras:cache:clear';

    protected $description = 'Clear the persistent Spatie Data schema cache used by scramble-extras.';

    public function handle(SchemaCache $cache): int
    {
        $path = $cache->path();
        $cache->clear();
        $this->info('Cleared cache at '.$path);

        return self::SUCCESS;
    }
}
