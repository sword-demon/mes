<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Service\InventoryAlertService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:scan-alerts')]
#[Description('Scan inventory and reconcile low-stock alerts')]
class ScanInventoryAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(InventoryAlertService $alerts): int
    {
        $counts = $alerts->scan();

        $this->info("Created {$counts['created']}, updated {$counts['updated']}, resolved {$counts['resolved']} inventory alerts.");

        return self::SUCCESS;
    }
}
