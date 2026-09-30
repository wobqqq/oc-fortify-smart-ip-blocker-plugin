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
    protected $description = 'Lift the ban and reset the request count of an IP.';

    public function handle(SmartIpBlockerService $smartIpBlockerService): int
    {
        $ip = $this->argument('ip');
        $ip = is_string($ip) ? trim($ip) : '';

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->error(sprintf('%s is not an IP address.', $ip));

            return self::FAILURE;
        }

        $smartIpBlockerService->removeIp($ip);

        $this->info(sprintf('IP %s has been removed from the blacklist.', $ip));

        return self::SUCCESS;
    }
}
