<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker;

use Event;
use System\Classes\PluginBase;
use Validator;
use Wobqqq\FortifySmartIpBlocker\Console\SmartIpBlockerDisableCommand;
use Wobqqq\FortifySmartIpBlocker\Console\SmartIpBlockerRemoveIpCommand;
use Wobqqq\FortifySmartIpBlocker\Listeners\FortifyListener;
use Wobqqq\FortifySmartIpBlocker\Services\SmartIpBlockerService;
use Wobqqq\FortifySmartIpBlocker\Validator\Rules\SmartIpBlockerCurrentIpRule;

final class Plugin extends PluginBase
{
    /** @var array<int, string> */
    public $require = ['Wobqqq.Fortify'];

    public function register(): void
    {
        $this->registerConsoleCommand('wobqqq.fortify:smart-ip-blocker:remove-ip', SmartIpBlockerRemoveIpCommand::class);
        $this->registerConsoleCommand('wobqqq.fortify:smart-ip-blocker:disable', SmartIpBlockerDisableCommand::class);
    }

    public function boot(): void
    {
        $this->registerEvents();
        $this->registerValidatorRules();
        $this->runService();
    }

    private function registerEvents(): void
    {
        Event::subscribe(FortifyListener::class);
    }

    private function registerValidatorRules(): void
    {
        Validator::extend('smart_ip_blocker_current_ip', SmartIpBlockerCurrentIpRule::class);
    }

    private function runService(): void
    {
        /** @var SmartIpBlockerService $smartIpBlockerService */
        $smartIpBlockerService = app(SmartIpBlockerService::class);
        $smartIpBlockerService->addMiddleware();
    }
}
