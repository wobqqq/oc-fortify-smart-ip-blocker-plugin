<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Cache;

use Illuminate\Support\Facades\Cache;
use Throwable;
use Wobqqq\Fortify\Cache\BasicCache;
use Wobqqq\FortifySmartIpBlocker\Dto\SmartIpBlockerDto;
use Wobqqq\FortifySmartIpBlocker\Transformers\FortifyTransformer;

final class SmartIpBlockerDtoCache extends BasicCache
{
    public function get(): SmartIpBlockerDto
    {
        $cacheKey = $this->cacheKey();

        try {
            $smartIpBlockerDto = Cache::remember($cacheKey, self::TTL, FortifyTransformer::smartIpBlockerDto(...));
        } catch (Throwable) {
            Cache::forget($cacheKey);
            $smartIpBlockerDto = null;
        }

        return $smartIpBlockerDto instanceof SmartIpBlockerDto ? $smartIpBlockerDto : FortifyTransformer::smartIpBlockerDto();
    }

    public function clear(): void
    {
        Cache::forget($this->cacheKey());
    }
}
