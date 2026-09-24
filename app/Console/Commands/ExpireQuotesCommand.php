<?php

namespace App\Console\Commands;

use App\Services\ExpireQuotesService;
use Illuminate\Console\Command;

class ExpireQuotesCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'quotes:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark past-validity quotations as Expired (Approved quotes are skipped)';

    public function handle(ExpireQuotesService $service): int
    {
        $count = $service->run();

        $this->info($count === 0
            ? 'No quotations needed expiring.'
            : "Expired {$count} quotation(s).");

        return self::SUCCESS;
    }
}
