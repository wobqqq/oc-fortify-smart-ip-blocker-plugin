<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Transformers;

use Illuminate\Support\Facades\View as IlluminateView;
use Str;
use Wobqqq\Fortify\Enums\View;
use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\FortifySmartIpBlocker\Dto\SmartIpBlockerDto;

final readonly class FortifyTransformer
{
    public const DEFAULT_REQUESTS_LIMIT = 100;

    public const DEFAULT_BAN_HOURS = 1;

    public static function smartIpBlockerDto(): SmartIpBlockerDto
    {
        $enabled = (bool)Fortify::get('ip_firewall.smart_ip_blocker_enabled');

        $view = Fortify::get('ip_firewall.smart_ip_blocker_view');
        $view = is_string($view) && $view !== '' && IlluminateView::exists($view) ? $view : View::DENIED->value;

        $requestsLimit = self::positiveInt(Fortify::get('ip_firewall.smart_ip_blocker_requests_limit'), self::DEFAULT_REQUESTS_LIMIT);
        $banHours = self::positiveInt(Fortify::get('ip_firewall.smart_ip_blocker_ban_hours'), self::DEFAULT_BAN_HOURS);

        $excludedCidrRanges = [];
        $excludedExactIps = [];
        $excludedHeaders = [];

        if ($enabled) {
            foreach (self::rows('ip_firewall.smart_ip_blocker_excluded_ips') as $row) {
                $ip = self::text($row['ip'] ?? null);

                if ($ip === '') {
                    continue;
                }

                if (str_contains($ip, '/')) {
                    $excludedCidrRanges[] = $ip;
                } else {
                    $excludedExactIps[$ip] = 1;
                }
            }

            foreach (self::rows('ip_firewall.smart_ip_blocker_excluded_headers') as $row) {
                $header = Str::lower(self::text($row['header'] ?? null));
                $value = Str::lower(self::text($row['value'] ?? null));

                if ($header !== '' && $value !== '') {
                    $excludedHeaders[$header][] = $value;
                }
            }
        }

        return new SmartIpBlockerDto(
            $enabled,
            $view,
            (bool)Fortify::get('ip_firewall.smart_ip_blocker_cache_control'),
            $requestsLimit,
            $banHours,
            array_values(array_unique($excludedCidrRanges)),
            $excludedExactIps,
            array_map(static fn (array $values): array => array_values(array_unique($values)), $excludedHeaders),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(string $setting): array
    {
        $rows = Fortify::get($setting);

        /** @var array<int, array<string, mixed>> */
        return array_values(array_filter(is_array($rows) ? $rows : [], is_array(...)));
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : $default;
    }
}
