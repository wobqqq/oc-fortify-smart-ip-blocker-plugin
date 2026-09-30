<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Wobqqq\FortifySmartIpBlocker\Http\Middlewares\SmartIpBlockerMiddleware;
use Wobqqq\FortifySmartIpBlocker\Services\SmartIpBlockerService;

/**
 * @param array<string, string> $headers
 */
function smartIpBlockerAllows(string $ip, array $headers = []): bool
{
    return app(SmartIpBlockerService::class)->check($ip, array_map(static fn (string $value): array => [$value], $headers));
}

it('adds its middleware to the site and the backend only while enabled', function (): void {
    expect(Config::get('cms.middleware_group'))->toBe('web');

    configureSmartIpBlocker([]);

    expect(Config::get('cms.middleware_group'))->toBe(['web', SmartIpBlockerMiddleware::ALIAS])
        ->and(Config::get('backend.middleware_group'))->toBe(['web', SmartIpBlockerMiddleware::ALIAS]);
});

it('bans an IP once it exceeds the requests allowed per minute', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 3]);

    $answers = array_map(static fn (): bool => smartIpBlockerAllows('203.0.113.7'), range(1, 5));

    expect($answers)->toBe([true, true, true, false, false])
        ->and(smartIpBlockerAllows('203.0.113.8'))->toBeTrue();
});

it('counts the requests per minute, not per ban duration', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 2, 'smart_ip_blocker_ban_hours' => 6]);

    smartIpBlockerAllows('203.0.113.7');
    smartIpBlockerAllows('203.0.113.7');

    Carbon::setTestNow(Carbon::now()->addSeconds(SmartIpBlockerService::WINDOW_SECONDS + 1));

    expect(smartIpBlockerAllows('203.0.113.7'))->toBeTrue();
});

it('keeps the ban for the configured hours', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 1, 'smart_ip_blocker_ban_hours' => 2]);

    smartIpBlockerAllows('203.0.113.7');
    smartIpBlockerAllows('203.0.113.7');

    Carbon::setTestNow(Carbon::now()->addMinutes(119));
    expect(smartIpBlockerAllows('203.0.113.7'))->toBeFalse();

    Carbon::setTestNow(Carbon::now()->addMinutes(2));
    expect(smartIpBlockerAllows('203.0.113.7'))->toBeTrue();
});

it('never counts an excluded IP, subnet or header', function (): void {
    configureSmartIpBlocker([
        'smart_ip_blocker_requests_limit' => 1,
        'smart_ip_blocker_excluded_ips' => [['ip' => '198.51.100.10'], ['ip' => '10.0.0.0/8'], ['ip' => '2001:db8::1'], ['ip' => '']],
        'smart_ip_blocker_excluded_headers' => [
            ['header' => 'User-Agent', 'value' => 'Googlebot'],
            ['header' => 'User-Agent', 'value' => 'bingbot'],
            ['header' => 'X-Monitor', 'value' => ''],
        ],
    ]);

    foreach (range(1, 5) as $request) {
        expect(smartIpBlockerAllows('198.51.100.10'))->toBeTrue()
            ->and(smartIpBlockerAllows('10.20.30.40'))->toBeTrue()
            ->and(smartIpBlockerAllows('2001:0db8:0000:0000:0000:0000:0000:0001'))->toBeTrue()
            ->and(smartIpBlockerAllows('203.0.113.7', ['user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']))->toBeTrue()
            ->and(smartIpBlockerAllows('203.0.113.7', ['user-agent' => 'Mozilla/5.0 (compatible; bingbot/2.0)']))->toBeTrue();
    }

    expect(smartIpBlockerAllows('203.0.113.9', ['x-monitor' => 'yes']))->toBeTrue()
        ->and(smartIpBlockerAllows('203.0.113.9', ['x-monitor' => 'yes']))->toBeFalse();
});

it('answers a banned visitor 429 with the time left and the configured page', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 1, 'smart_ip_blocker_ban_hours' => 1]);

    $visit = static function (): Response {
        $request = Request::create('/page', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.7']);
        $response = app(SmartIpBlockerMiddleware::class)->handle($request, static fn (): Response => new Response('content'));

        return $response instanceof Response ? $response : throw new UnexpectedValueException('No response.');
    };

    $allowed = $visit();
    $blocked = $visit();

    expect($allowed->getStatusCode())->toBe(200)
        ->and($allowed->getContent())->toBe('content')
        ->and($blocked->getStatusCode())->toBe(429)
        ->and($blocked->headers->get('Retry-After'))->toBe('3600')
        ->and($blocked->getContent())->toContain('Access denied');
});

it('lifts a ban from the console, the previous versions\' ban included', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 1]);

    smartIpBlockerAllows('203.0.113.7');
    smartIpBlockerAllows('203.0.113.7');
    Cache::put('ban:203.0.113.7', true, 3600);

    expect(Artisan::call('wobqqq.fortify:smart-ip-blocker:remove-ip', ['ip' => '203.0.113.7']))->toBe(0)
        ->and(Cache::has('ban:203.0.113.7'))->toBeFalse()
        ->and(smartIpBlockerAllows('203.0.113.7'))->toBeTrue();
});

it('refuses to lift a ban for something that is not an IP', function (): void {
    expect(Artisan::call('wobqqq.fortify:smart-ip-blocker:remove-ip', ['ip' => 'not-an-ip']))->toBe(1)
        ->and(Artisan::output())->toContain('not-an-ip is not an IP address.');
});

it('turns itself off from the console and keeps the other firewall settings', function (): void {
    configureSmartIpBlocker(['admin_ip_access_enabled' => true]);

    expect(Artisan::call('wobqqq.fortify:smart-ip-blocker:disable'))->toBe(0)
        ->and(Wobqqq\Fortify\Models\Fortify::get('ip_firewall.smart_ip_blocker_enabled'))->toBeFalse()
        ->and(Wobqqq\Fortify\Models\Fortify::get('ip_firewall.admin_ip_access_enabled'))->toBeTrue();
});

it('keeps a bounded list of the IPs it tracks', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 100, 'smart_ip_blocker_cache_control' => true]);

    foreach (range(1, 1005) as $i) {
        smartIpBlockerAllows(sprintf('10.1.%d.%d', intdiv($i, 250), $i % 250));
    }

    /** @var array<string, int> $tracked */
    $tracked = Cache::get('wobqqq.fortify.sib.rate_ip_list');

    expect(count($tracked))->toBeLessThanOrEqual(1000);
});
