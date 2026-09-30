<?php

declare(strict_types=1);

use Backend\Widgets\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use System\Controllers\Settings;
use Wobqqq\Fortify\Dto\WidgetGroupItemDto;
use Wobqqq\Fortify\Enums\FortifyEvent;
use Wobqqq\Fortify\Enums\WidgetItemColor;
use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\Fortify\Transformers\FortifyTransformer as CoreTransformer;
use Wobqqq\FortifySmartIpBlocker\Cache\SmartIpBlockerDtoCache;
use Wobqqq\FortifySmartIpBlocker\Transformers\FortifyTransformer;

function smartIpBlockerSettings(string $ip = '198.51.100.20'): Fortify
{
    $model = smartIpBlockerSettingsForm($ip)->model;

    return $model instanceof Fortify ? $model : throw new UnexpectedValueException('The form is not the Fortify settings.');
}

function smartIpBlockerSettingsForm(string $ip = '198.51.100.20'): Form
{
    app()->instance('request', Request::create('/admin/system/settings', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]));

    $form = new Form(new Settings(), Fortify::instance());
    Event::dispatch('backend.form.extendFields', [$form]);

    return $form;
}

it('adds its section to the Fortify settings form', function (): void {
    $form = smartIpBlockerSettingsForm();

    expect($form->tabFields)->toHaveKeys([
        'ip_firewall[smart_ip_blocker_enabled]',
        'ip_firewall[smart_ip_blocker_requests_limit]',
        'ip_firewall[smart_ip_blocker_ban_hours]',
        'ip_firewall[smart_ip_blocker_excluded_ips]',
        'ip_firewall[smart_ip_blocker_excluded_headers]',
    ])->and($form->tabFields['ip_firewall[smart_ip_blocker_requests_limit]']['default'])->toBe(FortifyTransformer::DEFAULT_REQUESTS_LIMIT);
});

it('starts disabled with the defaults and the administrator excluded', function (): void {
    expect(smartIpBlockerSettings('198.51.100.20')->ip_firewall)->toMatchArray([
        'smart_ip_blocker_enabled' => false,
        'smart_ip_blocker_requests_limit' => FortifyTransformer::DEFAULT_REQUESTS_LIMIT,
        'smart_ip_blocker_ban_hours' => FortifyTransformer::DEFAULT_BAN_HOURS,
        'smart_ip_blocker_excluded_ips' => [['ip' => '198.51.100.20']],
    ]);
});

it('does not add the administrator twice when a subnet already covers them', function (): void {
    Fortify::set('ip_firewall', ['smart_ip_blocker_excluded_ips' => [['ip' => '198.51.100.0/24']]]);

    expect(smartIpBlockerSettings('198.51.100.20')->ip_firewall)
        ->toHaveKey('smart_ip_blocker_excluded_ips', [['ip' => '198.51.100.0/24']]);
});

it('validates the limits, the addresses and the headers', function (string $field, mixed $value, bool $passes): void {
    $model = smartIpBlockerSettings();
    (new ReflectionProperty(app(), 'isRunningInConsole'))->setValue(app(), false);

    $data = ['config' => ['password_policy_min_length' => 12, 'session_lifetime' => 30], 'ip_firewall' => [
        'smart_ip_blocker_view' => 'wobqqq.fortify::denied',
        'smart_ip_blocker_requests_limit' => 100,
        'smart_ip_blocker_ban_hours' => 1,
        'smart_ip_blocker_excluded_ips' => [['ip' => '198.51.100.20']],
    ]];
    data_set($data, $field, $value);

    /** @var array<string, mixed> $data */
    expect(Validator::make($data, $model->rules)->passes())->toBe($passes);
})->with([
    ['ip_firewall.smart_ip_blocker_requests_limit', 0, false],
    ['ip_firewall.smart_ip_blocker_requests_limit', 10001, false],
    ['ip_firewall.smart_ip_blocker_ban_hours', 101, false],
    ['ip_firewall.smart_ip_blocker_excluded_ips', [['ip' => '203.0.113.1']], false],
    ['ip_firewall.smart_ip_blocker_excluded_ips', [['ip' => '198.51.100.0/24']], true],
    ['ip_firewall.smart_ip_blocker_excluded_ips.0.ip', '198.51.100.20; drop', false],
    ['ip_firewall.smart_ip_blocker_excluded_headers', [['header' => 'User-Agent', 'value' => 'Googlebot']], true],
    ['ip_firewall.smart_ip_blocker_excluded_headers', [['header' => "X-Bad\r\nHeader", 'value' => 'x']], false],
]);

it('names the address in the lock-out message', function (): void {
    $model = smartIpBlockerSettings('198.51.100.20');
    (new ReflectionProperty(app(), 'isRunningInConsole'))->setValue(app(), false);

    $validator = Validator::make([
        'config' => ['password_policy_min_length' => 12, 'session_lifetime' => 30],
        'ip_firewall' => [
            'smart_ip_blocker_view' => 'wobqqq.fortify::denied',
            'smart_ip_blocker_requests_limit' => 100,
            'smart_ip_blocker_ban_hours' => 1,
            'smart_ip_blocker_excluded_ips' => [['ip' => '203.0.113.1']],
        ],
    ], $model->rules);

    expect($validator->errors()->first('ip_firewall.smart_ip_blocker_excluded_ips'))->toContain('198.51.100.20');
});

it('shows on the dashboard whether it is on', function (): void {
    $item = CoreTransformer::widgetGroupItemDto('placeholder');
    Event::dispatch(FortifyEvent::SERVICES_WIDGET_GROUP_ITEM_SMART_IP_BLOCKER->value, [&$item]);

    expect($item)->toBeInstanceOf(WidgetGroupItemDto::class)
        ->and($item->color)->toBe(WidgetItemColor::DANGER);

    configureSmartIpBlocker([]);
    Event::dispatch(FortifyEvent::SERVICES_WIDGET_GROUP_ITEM_SMART_IP_BLOCKER->value, [&$item]);

    expect($item->color)->toBe(WidgetItemColor::SUCCESS);
});

it('falls back to safe values for a broken setting', function (): void {
    configureSmartIpBlocker([
        'smart_ip_blocker_view' => 'acme.theme::missing',
        'smart_ip_blocker_requests_limit' => 'many',
        'smart_ip_blocker_ban_hours' => -3,
        'smart_ip_blocker_excluded_ips' => 'not a table',
    ]);

    $dto = FortifyTransformer::smartIpBlockerDto();

    expect($dto->view)->toBe('wobqqq.fortify::denied')
        ->and($dto->requestsLimit)->toBe(FortifyTransformer::DEFAULT_REQUESTS_LIMIT)
        ->and($dto->banHours)->toBe(FortifyTransformer::DEFAULT_BAN_HOURS)
        ->and($dto->excludedExactIps)->toBe([]);
});

it('rebuilds a cached rule set the previous version wrote in another shape', function (): void {
    configureSmartIpBlocker(['smart_ip_blocker_requests_limit' => 7]);

    Cache::shouldReceive('remember')->once()->andThrow(new TypeError('Cannot assign string to property'));
    Cache::shouldReceive('forget')->once();

    expect(app(SmartIpBlockerDtoCache::class)->get()->requestsLimit)->toBe(7);
});
