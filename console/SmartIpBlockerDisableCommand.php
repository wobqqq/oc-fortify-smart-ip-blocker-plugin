<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Console;

use Illuminate\Console\Command;
use Wobqqq\FortifySmartIpBlocker\Services\SmartIpBlockerService;

final class SmartIpBlockerDisableCommand extends Command
{
    /** @var string */
    protected $name = 'wobqqq.fortify:smart-ip-blocker:disable';

    /** @var string */
    protected $description = 'Disable Smart IP Blocker.';

    public function handle(SmartIpBlockerService $smartIpBlockerService): void
    {
        $smartIpBlockerService->disable();

        $this->info('Smart IP Blocker disabled.');
    }
}
