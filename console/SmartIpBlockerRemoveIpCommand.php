<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Console;

use Illuminate\Console\Command;
use Wobqqq\FortifySmartIpBlocker\Services\SmartIpBlockerService;

final class SmartIpBlockerRemoveIpCommand extends Command
{
    /** @var string */
    protected $name = 'wobqqq.fortify:smart-ip-blocker:remove-ip';

    /** @var string */
    protected $signature = 'wobqqq.fortify:smart-ip-blocker:remove-ip {ip}';

    /** @var string */
    protected $description = 'Remove an IP from the smart IP blocker blacklist.';

    public function handle(SmartIpBlockerService $smartIpBlockerService): void
    {
        /** @var string|null $ip */
        $ip = $this->argument('ip');

        $smartIpBlockerService->removeIp((string)$ip);

        $this->info(sprintf('IP %s has been removed from the blacklist.', $ip));
    }
}
