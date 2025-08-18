<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LookupController;

class BootstrapLookups extends Command
{
    protected $signature = 'lookups:bootstrap';

    protected $description = 'Popula os lookups (tabelas de apoio) a partir dos dados existentes em reports';

    public function handle(): int
    {
        $controller = app(LookupController::class);
        $response = $controller->bootstrapFromReports();
        $data = $response->getData(true);
        $this->info('Inseridos: ' . ($data['inserted'] ?? 0));
        return self::SUCCESS;
    }
} 