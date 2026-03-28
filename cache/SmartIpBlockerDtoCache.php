<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Cache;

use Illuminate\Support\Facades\Cache;
use Wobqqq\Fortify\Cache\BasicCache;
use Wobqqq\FortifySmartIpBlocker\Dto\SmartIpBlockerDto;
use Wobqqq\FortifySmartIpBlocker\Transformers\FortifyTransformer;

final class SmartIpBlockerDtoCache extends BasicCache
{
    public function get(): SmartIpBlockerDto
    {
        $cacheKey = $this->cacheKey();

        /** @var SmartIpBlockerDto $smartIpBlockerDto */
        $smartIpBlockerDto = Cache::remember($cacheKey, self::TTL, function () {
            return FortifyTransformer::smartIpBlockerDto();
        });

        return $smartIpBlockerDto;
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey());
    }
}
