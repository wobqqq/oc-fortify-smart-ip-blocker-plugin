<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Transformers;

use Arr;
use Illuminate\Support\Facades\View as IlluminateView;
use Str;
use Wobqqq\Fortify\Enums\View;
use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\FortifySmartIpBlocker\Dto\SmartIpBlockerDto;

final readonly class FortifyTransformer
{
    public static function smartIpBlockerDto(): SmartIpBlockerDto
    {
        /** @var bool|int|null $enabled */
        $enabled = Fortify::get('ip_firewall.smart_ip_blocker_enabled');
        $enabled = (bool)$enabled;

        /** @var string|null $view */
        $view = Fortify::get('ip_firewall.smart_ip_blocker_view');
        $view = (string)$view;
        $view = empty($view) || !IlluminateView::exists($view) ? View::DENIED->value : $view;

        /** @var bool|int|null $cacheControl */
        $cacheControl = Fortify::get('ip_firewall.smart_ip_blocker_cache_control');
        $cacheControl = (bool)$cacheControl;

        /** @var string|int|null $requestsLimit */
        $requestsLimit = Fortify::get('ip_firewall.smart_ip_blocker_requests_limit');
        $requestsLimit = (int)$requestsLimit;

        /** @var string|int|null $banHours */
        $banHours = Fortify::get('ip_firewall.smart_ip_blocker_ban_hours');
        $banHours = (int)$banHours;

        if ($enabled) {
            /** @var array<int, string>|null $excludedIps */
            $excludedIps = Fortify::get('ip_firewall.smart_ip_blocker_excluded_ips');
            $excludedIps = (empty($excludedIps) || !is_array($excludedIps)) ? [] : $excludedIps;
            /** @var array<int, string> $excludedIps */
            $excludedIps = array_column($excludedIps, 'ip');
            $excludedIps = array_unique($excludedIps);
            $excludedIps = array_filter($excludedIps);

            $excludedCidrRanges = [];
            $excludedExactIps = [];

            foreach ($excludedIps as $ip) {
                if (str_contains($ip, '/')) {
                    $excludedCidrRanges[] = $ip;
                } else {
                    $excludedExactIps[$ip] = 1;
                }
            }

            /** @var array<int, array<string, string|null>>|null $excludedHeadersTable */
            $excludedHeadersTable = Fortify::get('ip_firewall.smart_ip_blocker_excluded_headers');
            $excludedHeadersTable = (empty($excludedHeadersTable) || !is_array($excludedHeadersTable))
                ? []
                : $excludedHeadersTable;
            $excludedHeaders = [];

            foreach ($excludedHeadersTable as $excludedHeadersTableRow) {
                /** @var string|null $header */
                $header = Arr::get($excludedHeadersTableRow, 'header');
                /** @var string|null $value */
                $value = Arr::get($excludedHeadersTableRow, 'value');

                if (empty($header) || empty($value)) {
                    continue;
                }

                $header = trim((string)$header);
                $header = Str::lower($header);
                $value = trim((string)$value);
                $value = Str::lower($value);

                $excludedHeaders[$header] = $value;
            }

            $excludedHeaders = array_unique($excludedHeaders);
            $excludedHeaders = array_filter($excludedHeaders);
        } else {
            $excludedCidrRanges = [];
            $excludedExactIps = [];
            $excludedHeaders = [];
        }

        return new SmartIpBlockerDto(
            $enabled,
            $view,
            $cacheControl,
            empty($requestsLimit) ? 80 : $requestsLimit,
            empty($banHours) ? 6 : $banHours,
            $excludedCidrRanges,
            $excludedExactIps,
            $excludedHeaders,
        );
    }
}
