<?php

namespace Modules\Insurance\Console;

use Illuminate\Console\Command;
use Modules\Insurance\Services\Otac\OtacClient;

class RefreshOtacToken extends Command
{
    protected $signature = 'insurance:otac-refresh-token';

    protected $description = 'Re-login to the NHIA OTAC API and refresh the cached access token.';

    public function handle(OtacClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->info('NHIA OTAC is not configured; skipping token refresh.');

            return self::SUCCESS;
        }

        if ($client->login(force: true) === null) {
            $this->error('NHIA OTAC login failed; see the log for details.');

            return self::FAILURE;
        }

        $this->info('NHIA OTAC access token refreshed.');

        return self::SUCCESS;
    }
}
