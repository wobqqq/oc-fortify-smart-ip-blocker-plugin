<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Services;

use App;
use Cache;
use Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use October\Rain\Router\CoreRouter;
use Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\FortifySmartIpBlocker\Http\Middlewares\SmartIpBlockerMiddleware;
use Wobqqq\FortifySmartIpBlocker\Instances\SmartIpBlockerDtoInstance;

final class SmartIpBlockerService
{
    public const WINDOW_SECONDS = 60;

    private const CACHE_CONTROL_IPS = 1000;

    private const CACHE_CONTROL_KEY = 'wobqqq.fortify.sib.rate_ip_list';

    private const KEY_PREFIX = 'wobqqq.fortify.sib.';

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
     * @param array<string, array<int, string|null>|string|null> $headers
     */
    public function check(string $ip, array $headers): bool
    {
        $smartIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();

        if (!$smartIpBlockerDto->enabled || $this->isExcluded($ip, $headers)) {
            return true;
        }

        if (Cache::has($this->banKey($ip))) {
            return false;
        }

        $rateKey = $this->rateKey($ip);

        Cache::add($rateKey, 0, self::WINDOW_SECONDS);
        $count = (int)Cache::increment($rateKey);

        if ($count > $smartIpBlockerDto->requestsLimit) {
            Cache::put($this->banKey($ip), Carbon::now()->getTimestamp() + $smartIpBlockerDto->banHours * 3600, $smartIpBlockerDto->banHours * 3600);
            Cache::forget($rateKey);

            return false;
        }

        if ($smartIpBlockerDto->cacheControl) {
            $this->cacheControl($ip);
        }

        return true;
    }

    /**
     * Seconds until a banned IP may try again, 0 when it is not banned.
     */
    public function retryAfter(string $ip): int
    {
        $bannedUntil = Cache::get($this->banKey($ip));

        return is_int($bannedUntil) ? max(0, $bannedUntil - Carbon::now()->getTimestamp()) : 0;
    }

    public function removeIp(string $ip): void
    {
        Cache::forget($this->rateKey($ip));
        Cache::forget($this->banKey($ip));
        Cache::forget(sprintf('rate:%s', $ip));
        Cache::forget(sprintf('ban:%s', $ip));
    }

    public function disable(): void
    {
        /** @var array<string, mixed>|Collection<int, mixed>|null $ipFirewall */
        $ipFirewall = Fortify::get('ip_firewall');

        if ($ipFirewall instanceof Collection) {
            $ipFirewall = $ipFirewall->toArray();
        }

        $ipFirewall = is_array($ipFirewall) ? $ipFirewall : [];

        $ipFirewall['smart_ip_blocker_enabled'] = false;

        Fortify::set('ip_firewall', $ipFirewall);
    }

    /**
     * @param array<string, array<int, string|null>|string|null> $headers
     */
    private function isExcluded(string $ip, array $headers): bool
    {
        $smartIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();

        if (isset($smartIpBlockerDto->excludedExactIps[$ip])) {
            return true;
        }

        $excludedIps = array_merge($smartIpBlockerDto->excludedCidrRanges, array_keys($smartIpBlockerDto->excludedExactIps));

        if ($excludedIps !== [] && IpUtils::checkIp($ip, $excludedIps)) {
            return true;
        }

        foreach ($headers as $header => $values) {
            $excludedValue = $smartIpBlockerDto->excludedHeaders[Str::lower((string)$header)] ?? null;

            if ($excludedValue === null) {
                continue;
            }

            foreach ((array)$values as $value) {
                if (!is_string($value) || $value === '') {
                    continue;
                }

                foreach ((array)$excludedValue as $needle) {
                    if ($needle !== '' && str_contains(Str::lower($value), $needle)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function cacheControl(string $ip): void
    {
        /** @var array<string, int> $ips */
        $ips = Cache::get(self::CACHE_CONTROL_KEY, []);

        if (count($ips) >= self::CACHE_CONTROL_IPS) {
            foreach (array_keys($ips) as $storedIp) {
                if (!Cache::has($this->rateKey($storedIp)) && !Cache::has($this->banKey($storedIp))) {
                    unset($ips[$storedIp]);
                }
            }
        }

        if (count($ips) >= self::CACHE_CONTROL_IPS) {
            $oldestIp = array_key_first($ips);
            unset($ips[$oldestIp]);
            Cache::forget($this->rateKey($oldestIp));
        }

        $ips[$ip] = 1;

        Cache::put(self::CACHE_CONTROL_KEY, $ips, 36000);
    }

    private function rateKey(string $ip): string
    {
        return self::KEY_PREFIX . 'rate:' . $ip;
    }

    private function banKey(string $ip): string
    {
        return self::KEY_PREFIX . 'ban:' . $ip;
    }

    private function overrideConfig(string $configName): void
    {
        $middleware = Config::get($configName, []);
        $middleware = is_string($middleware) ? [$middleware] : (is_array($middleware) ? $middleware : []);
        $middleware = array_filter($middleware, static fn (mixed $name): bool => is_string($name) && $name !== '');

        $middleware[] = SmartIpBlockerMiddleware::ALIAS;

        Config::set($configName, array_values(array_unique($middleware)));
    }
}
