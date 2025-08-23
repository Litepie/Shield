<?php

namespace Litepie\Shield\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class CacheReset extends Command
{
    protected $signature = 'shield:cache-reset';

    protected $description = 'Reset the permission cache';

    public function handle()
    {
        if (app()->runningInConsole()) {
            app()[\Litepie\Shield\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $this->info('Permission cache flushed.');
    }
}
