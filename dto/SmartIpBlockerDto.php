<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Dto;

final readonly class SmartIpBlockerDto
{
    public function __construct(
        public bool   $enabled,
        public string $view,
        public bool $cacheControl,
        public int $requestsLimit,
        public int $banHours,
        /** @var array<int, string> $excludedCidrRanges */
        public array  $excludedCidrRanges = [],
        /** @var array<string, int> $excludedExactIps */
        public array  $excludedExactIps = [],
        /** @var array<string, string> $excludedHeaders */
        public array  $excludedHeaders = [],
    ) {
    }
}
