<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-project-location-references')]
#[Description('Command description')]
class BackfillProjectLocationReferences extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
