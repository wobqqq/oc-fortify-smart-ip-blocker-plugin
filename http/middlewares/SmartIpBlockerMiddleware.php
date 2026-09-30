<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as IlluminateView;
use Symfony\Component\HttpFoundation\Response;
use Wobqqq\Fortify\Enums\View;
use Wobqqq\FortifySmartIpBlocker\Instances\SmartIpBlockerDtoInstance;
use Wobqqq\FortifySmartIpBlocker\Services\SmartIpBlockerService;

final readonly class SmartIpBlockerMiddleware
{
    public const ALIAS = 'fortify_smart_ip_blocker';

    public function __construct(private SmartIpBlockerService $smartIpBlockerService)
    {
    }

    /**
     * @param Closure(Request): mixed $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $ip = (string)$request->ip();

        /** @var array<string, array<int, string|null>> $headers */
        $headers = $request->headers->all();

        if ($this->smartIpBlockerService->check($ip, $headers)) {
            return $next($request);
        }

        $smartIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();

        $view = IlluminateView::exists($smartIpBlockerDto->view)
            ? $smartIpBlockerDto->view
            : View::DENIED->value;

        /** @var \Illuminate\Routing\ResponseFactory $response */
        $response = response();

        return $response->view($view, [], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string)max(1, $this->smartIpBlockerService->retryAfter($ip)),
        ]);
    }
}
