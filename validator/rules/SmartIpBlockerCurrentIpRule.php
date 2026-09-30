<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Validator\Rules;

use Lang;
use Request;
use Symfony\Component\HttpFoundation\IpUtils;

final class SmartIpBlockerCurrentIpRule
{
    /**
     * The administrator who saves the list must stay excluded from the rate limit.
     *
     * @param array<mixed, mixed> $params
     */
    public function validate(string $attribute, mixed $value, array $params): bool
    {
        $currentIp = Request::ip();

        if (app()->runningInConsole() || !is_string($currentIp) || $currentIp === '' || !is_array($value) || $value === []) {
            return true;
        }

        $ips = [];

        foreach ($value as $row) {
            $ip = is_array($row) && is_string($row['ip'] ?? null) ? trim($row['ip']) : '';

            if ($ip !== '') {
                $ips[] = $ip;
            }
        }

        return $ips === [] || IpUtils::checkIp($currentIp, $ips);
    }

    public function message(): string
    {
        /** @var string $message */
        $message = Lang::get(
            'wobqqq.fortify::lang.validator_rules.smart_ip_blocker_current_ip',
            ['ip' => e((string)Request::ip())],
        );

        return $message;
    }
}
