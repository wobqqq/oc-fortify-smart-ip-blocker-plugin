<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Instances;

use October\Rain\Support\Traits\Singleton;
use Wobqqq\FortifySmartIpBlocker\Cache\SmartIpBlockerDtoCache;
use Wobqqq\FortifySmartIpBlocker\Dto\SmartIpBlockerDto;

final class SmartIpBlockerDtoInstance
{
    use Singleton;

    private ?SmartIpBlockerDto $smartIpBlockerDto = null;

    public function get(): SmartIpBlockerDto
    {
        if ($this->smartIpBlockerDto instanceof SmartIpBlockerDto) {
            return $this->smartIpBlockerDto;
        }

        /** @var SmartIpBlockerDtoCache $smartIpBlockerDtoCache */
        $smartIpBlockerDtoCache = app(SmartIpBlockerDtoCache::class);

        return $this->smartIpBlockerDto = $smartIpBlockerDtoCache->get();
    }
}
