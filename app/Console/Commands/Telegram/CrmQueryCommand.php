<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\CrmApiClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * What the CRM API says about a request, as JSON: to check the .env
 * credentials and to see the fields the penalty routing reads.
 */
final class CrmQueryCommand extends Command
{
    protected $signature = 'crm:query {search : a request number, e.g. LOG00665}';

    protected $description = 'Look up a request in the CRM API and print the answer';

    public function handle(CrmApiClient $crm): int
    {
        try {
            $body = $crm->searchQueries((string) $this->argument('search'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line((string) json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
