<?php

declare(strict_types=1);

use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\FortifySmartIpBlocker\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/**
 * @param array<string, mixed> $settings
 */
function configureSmartIpBlocker(array $settings): void
{
    Fortify::set('ip_firewall', array_merge([
        'smart_ip_blocker_enabled' => true,
        'smart_ip_blocker_view' => 'wobqqq.fortify::denied',
        'smart_ip_blocker_requests_limit' => 3,
        'smart_ip_blocker_ban_hours' => 2,
        'smart_ip_blocker_cache_control' => false,
        'smart_ip_blocker_excluded_ips' => [],
        'smart_ip_blocker_excluded_headers' => [],
    ], $settings));

    TestCase::bootPlugins();
}
