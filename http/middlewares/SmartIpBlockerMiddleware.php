<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as IlluminateView;
use Wobqqq\Fortify\Enums\View;
use Wobqqq\FortifySmartIpBlocker\Instances\SmartIpBlockerDtoInstance;
use Wobqqq\FortifySmartIpBlocker\Services\SmartIpBlockerService;

final class SmartIpBlockerMiddleware
{
    public const ALIAS = 'fortify_smart_ip_blocker';

    public function __construct(private readonly SmartIpBlockerService $smartIpBlockerService)
    {
    }

    /**
     * @param Request $request
     * @param Closure $next
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response|mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        /** @var null|array<string, string|array<int, string>> $headers */
        $headers = $request->headers->all();
        $headers = empty($headers) ? [] : $headers;

        if ($this->smartIpBlockerService->check((string)$ip, $headers)) {
            return $next($request);
        }

        $smartFortifyIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();

        $view = IlluminateView::exists($smartFortifyIpBlockerDto->view)
            ? $smartFortifyIpBlockerDto->view
            : View::DENIED->value;

        /** @var \Illuminate\Routing\ResponseFactory $response */
        $response = response();

        return $response->view($view, [], 403);
    }
}
