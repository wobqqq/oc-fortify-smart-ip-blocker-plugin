<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Services;

use App;
use Cache;
use Config;
use October\Rain\Router\CoreRouter;
use Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\FortifySmartIpBlocker\Http\Middlewares\SmartIpBlockerMiddleware;
use Wobqqq\FortifySmartIpBlocker\Instances\SmartIpBlockerDtoInstance;

final class SmartIpBlockerService
{
    private const CACHE_CONTROL_IPS = 1000;

    private static bool $addMiddleware = false;

    public function addMiddleware(): void
    {
        if (self::$addMiddleware) {
            return;
        }

        self::$addMiddleware = true;

        $smartIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();

        if (!$smartIpBlockerDto->enabled) {
            return;
        }

        /** @var CoreRouter $coreRoute */
        $coreRoute = App::make('router');
        $coreRoute->aliasMiddleware(SmartIpBlockerMiddleware::ALIAS, SmartIpBlockerMiddleware::class);

        $this->overrideConfig('cms.middleware_group');
        $this->overrideConfig('backend.middleware_group');
    }

    /**
     * @param string $ip
     * @param array<string, string|array<int, string>> $headers
     * @return bool
     */
    public function check(string $ip, array $headers): bool
    {
        $smartIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();

        if (!$smartIpBlockerDto->enabled) {
            return true;
        }

        if (isset($smartIpBlockerDto->excludedExactIps[$ip])) {
            return true;
        }

        if (!empty($smartIpBlockerDto->excludedCidrRanges)
            && IpUtils::checkIp($ip, $smartIpBlockerDto->excludedCidrRanges)) {
            return true;
        }

        foreach ($headers as $header => $values) {
            $values = is_array($values) ? $values : [$values];
            $header = Str::lower($header);

            foreach ($values as $value) {
                if (!empty($value)) {
                    $value = Str::lower($value);

                    if (isset($smartIpBlockerDto->excludedHeaders[$header])
                        && $smartIpBlockerDto->excludedHeaders[$header] === $value) {
                        return true;
                    }
                }
            }
        }

        $rateKey = sprintf('rate:%s', $ip);
        $banKey = sprintf('ban:%s', $ip);

        if (Cache::has($banKey)) {
            return false;
        }

        $count = Cache::increment($rateKey);
        $count = empty($count) ? 1 : $count;

        if ($count === 1) {
            Cache::put($rateKey, $count, ($smartIpBlockerDto->banHours * 3600));
        }

        if ($count > $smartIpBlockerDto->requestsLimit) {
            Cache::put($banKey, true, ($smartIpBlockerDto->banHours * 3600));

            return false;
        }

        if ($smartIpBlockerDto->cacheControl) {
            $this->cacheControl($ip);
        }

        return true;
    }

    public function removeIp(string $ip): void
    {
        Cache::forget(sprintf('rate:%s', $ip));
        Cache::forget(sprintf('ban:%s', $ip));
    }

    public function disable(): void
    {
        /** @var array<string, mixed>|\Illuminate\Support\Collection<int, mixed> $ipFirewall */
        $ipFirewall = Fortify::get('ip_firewall');

        if ($ipFirewall instanceof \Illuminate\Support\Collection) {
            $ipFirewall = $ipFirewall->toArray();
        }

        $ipFirewall = !is_array($ipFirewall) ? [] : $ipFirewall;

        $ipFirewall['smart_ip_blocker_enabled'] = false;

        Fortify::set('ip_firewall', $ipFirewall);
    }

    private function cacheControl(string $ip): void
    {
        /** @var array<string, int> $ips */
        $ips = Cache::get('wobqqq.fortify.sib.rate_ip_list', []);

        if (count($ips) >= self::CACHE_CONTROL_IPS) {
            foreach ($ips as $storedIp => $value) {
                if (!Cache::has(sprintf('rate:%s', $storedIp))) {
                    unset($ips[$storedIp]);
                }

                if (!Cache::has(sprintf('ban:%s', $storedIp))) {
                    unset($ips[$storedIp]);
                }
            }
        }

        if (count($ips) >= self::CACHE_CONTROL_IPS) {
            $oldestIp = array_key_first($ips);
            unset($ips[$oldestIp]);
            Cache::forget(sprintf('rate:%s', $oldestIp));
            Cache::forget(sprintf('ban:%s', $oldestIp));
        }

        $ips[$ip] = 1;

        Cache::put('wobqqq.fortify.sib.rate_ip_list', $ips, 36000);
    }

    private function overrideConfig(string $configName): void
    {
        /** @var string|null|array<int, string> $middleware */
        $middleware = Config::get($configName, []);

        if (is_string($middleware)) {
            $middleware = [$middleware];
        }

        if (empty($middleware)) {
            $middleware = [];
        }

        $middleware[] = SmartIpBlockerMiddleware::ALIAS;
        /** @var array<int, string> $middleware */
        $middleware = array_unique($middleware);
        $middleware = array_filter($middleware);

        Config::set($configName, $middleware);
    }
}
