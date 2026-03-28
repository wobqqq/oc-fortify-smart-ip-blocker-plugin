<?php

declare(strict_types=1);

namespace Wobqqq\FortifySmartIpBlocker\Listeners;

use Arr;
use Backend;
use Backend\Widgets\Form;
use October\Rain\Events\Dispatcher;
use Request;
use System\Controllers\Settings;
use Wobqqq\Fortify\Dto\WidgetGroupItemDto;
use Wobqqq\Fortify\Enums\FortifyEvent;
use Wobqqq\Fortify\Enums\View;
use Wobqqq\Fortify\Enums\WidgetItemColor;
use Wobqqq\Fortify\Models\Fortify;
use Wobqqq\Fortify\Transformers\FortifyTransformer;
use Wobqqq\FortifySmartIpBlocker\Cache\SmartIpBlockerDtoCache;
use Wobqqq\FortifySmartIpBlocker\Instances\SmartIpBlockerDtoInstance;

final readonly class FortifyListener
{
    public function __construct(
        private SmartIpBlockerDtoCache $smartIpBlockerDtoCache,
    ) {
    }

    public function subscribe(Dispatcher $event): void
    {
        $event->listen(FortifyEvent::SERVICES_WIDGET_GROUP_ITEM_SMART_IP_BLOCKER->value, function (WidgetGroupItemDto &$widgetGroupItemDto) {
            $this->serveWidgetGroupItem($widgetGroupItemDto);
        });

        $event->listen(FortifyEvent::MODEL_FORTIFY_INIT_SETTINGS_DATA->value, function (Fortify &$fortify) {
            $this->serveModelInitSettingsData($fortify);
        });

        Fortify::extend(function (Fortify $fortify) {
            $this->serveModel($fortify);

            $fortify->bindEvent('model.afterSave', function () {
                $this->smartIpBlockerDtoCache->clear();
            });

            $fortify->bindEvent('model.afterDelete', function () {
                $this->smartIpBlockerDtoCache->clear();
            });
        });

        $event->listen('backend.form.extendFields', function (Form $form) {
            if (!$form->getController() instanceof Settings || !$form->model instanceof Fortify || $form->isNested) {
                return;
            }

            /** @var Fortify $fortify */
            $fortify = $form->model;

            $this->serveModelInitSettingsData($fortify);
            $this->serveFields($form);
            $this->presetCurrentIp($fortify);
        });
    }

    private function serveWidgetGroupItem(WidgetGroupItemDto &$widgetGroupItemDto): void
    {
        $settingsLink = FortifyTransformer::widgetItemLinkDto(
            'wobqqq.fortify::lang.buttons.edit',
            Backend::url('system/settings/update/wobqqq/fortify/fortify#primarytab-ip-firewall'),
            'icon-wrench',
        );
        $SmartIpBlockerDto = SmartIpBlockerDtoInstance::instance()->get();
        $color = $SmartIpBlockerDto->enabled === true
            ? WidgetItemColor::SUCCESS
            : WidgetItemColor::DANGER;
        $widgetGroupItemDto = FortifyTransformer::widgetGroupItemDto(
            'wobqqq.fortify::lang.fields.smart_ip_blocker',
            [$settingsLink],
            $color,
            'icon-ban',
        );
    }

    private function serveModelInitSettingsData(Fortify $fortify): void
    {
        $ipFirewall = (isset($fortify->ip_firewall) && is_array($fortify->ip_firewall)) ? $fortify->ip_firewall : [];

        if (!isset($ipFirewall['smart_ip_blocker_enabled'])) {
            $ipFirewall['smart_ip_blocker_enabled'] = false;
        }

        if (!isset($ipFirewall['smart_ip_blocker_view'])) {
            $ipFirewall['smart_ip_blocker_view'] = View::DENIED->value;
        }

        if (!isset($ipFirewall['smart_ip_blocker_requests_limit'])) {
            $ipFirewall['smart_ip_blocker_requests_limit'] = 100;
        }

        if (!isset($ipFirewall['smart_ip_blocker_ban_hours'])) {
            $ipFirewall['smart_ip_blocker_ban_hours'] = 1;
        }

        if (!isset($ipFirewall['smart_ip_blocker_cache_control'])) {
            $ipFirewall['smart_ip_blocker_cache_control'] = false;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        /** @phpstan-ignore-next-line */
        $fortify->ip_firewall = $ipFirewall;
    }

    private function serveModel(Fortify $fortify): void
    {
        $fortify->attributeNames['ip_firewall.smart_ip_blocker_excluded_ips.*.ip'] = 'wobqqq.fortify::lang.fields.ip';
        $fortify->attributeNames['ip_firewall.smart_ip_blocker_excluded_headers.*.header'] = 'wobqqq.fortify::lang.fields.header';
        $fortify->attributeNames['ip_firewall.smart_ip_blocker_excluded_headers.*.value'] = 'wobqqq.fortify::lang.fields.value';

        $fortify->rules['ip_firewall.smart_ip_blocker_view'] = 'required|string|max:100';
        $fortify->rules['ip_firewall.smart_ip_blocker_requests_limit'] = 'required|int|min:1|max:10000';
        $fortify->rules['ip_firewall.smart_ip_blocker_ban_hours'] = 'required|int|min:1|max:100';
        $fortify->rules['ip_firewall.smart_ip_blocker_excluded_ips.*.ip'] = 'nullable|regex:/^[0-9a-fA-F\.:]+(\/\d{1,3})?$/|max:100';
        $fortify->rules['ip_firewall.smart_ip_blocker_excluded_ips'] = 'nullable|smart_ip_blocker_current_ip|array|max:150';
        $fortify->rules['ip_firewall.smart_ip_blocker_excluded_headers.*.header'] = 'nullable|string|max:50|regex:/^[A-Za-z0-9-]+$/';
        $fortify->rules['ip_firewall.smart_ip_blocker_excluded_headers.*.value'] = 'nullable|string|max:255';
        $fortify->rules['ip_firewall.smart_ip_blocker_excluded_headers'] = 'nullable|array|max:150';
    }

    private function serveFields(Form $form): void
    {
        $form->removeField('ip_firewall[smart_ip_blocker_section]');
        $form->removeField('ip_firewall[smart_ip_blocker_plugin]');
        $form->addTabFields([
            'ip_firewall[smart_ip_blocker_section]' => [
                'label' => 'wobqqq.fortify::lang.fields.smart_ip_blocker',
                'type' => 'section',
                'span' => 'full',
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
            ],

            'ip_firewall[smart_ip_blocker_enabled]' => [
                'label' => 'wobqqq.fortify::lang.fields.enabled',
                'span' => 'full',
                'type' => 'switch',
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'default' => false,
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_enabled',
                'commentHtml' => true,
            ],

            'ip_firewall[smart_ip_blocker_view]' => [
                'label' => 'wobqqq.fortify::lang.fields.view',
                'span' => 'full',
                'required' => true,
                'type' => 'dropdown',
                'default' => 'wobqqq.fortify::denied',
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_view',
                'options' => 'getViewOptions',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'ip_firewall[smart_ip_blocker_enabled]',
                    'condition' => 'checked',
                ],
            ],

            'ip_firewall[smart_ip_blocker_requests_limit]' => [
                'label' => 'wobqqq.fortify::lang.fields.smart_ip_blocker_requests_limit',
                'placeholder' => 'wobqqq.fortify::lang.fields.smart_ip_blocker_requests_limit',
                'span' => 'auto',
                'required' => true,
                'type' => 'number',
                'default' => 80,
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_requests_limit',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'ip_firewall[smart_ip_blocker_enabled]',
                    'condition' => 'checked',
                ],
            ],

            'ip_firewall[smart_ip_blocker_ban_hours]' => [
                'label' => 'wobqqq.fortify::lang.fields.smart_ip_blocker_ban_hours',
                'placeholder' => 'wobqqq.fortify::lang.fields.smart_ip_blocker_ban_hours',
                'span' => 'auto',
                'required' => true,
                'type' => 'number',
                'default' => 6,
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_ban_hours',
                'trigger' => [
                    'action' => 'show',
                    'field' => 'ip_firewall[smart_ip_blocker_enabled]',
                    'condition' => 'checked',
                ],
            ],

            'ip_firewall[smart_ip_blocker_cache_control]' => [
                'label' => 'wobqqq.fortify::lang.fields.smart_ip_blocker_cache_control',
                'span' => 'full',
                'type' => 'switch',
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'default' => false,
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_cache_control',
                'commentHtml' => true,
                'trigger' => [
                    'action' => 'show',
                    'field' => 'ip_firewall[smart_ip_blocker_enabled]',
                    'condition' => 'checked',
                ],
            ],

            'ip_firewall[smart_ip_blocker_excluded_ips]' => [
                'label' => 'wobqqq.fortify::lang.fields.excluded_ips',
                'type' => 'datatable',
                'span' => 'full',
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'adding' => true,
                'deleting' => true,
                'searching' => false,
                'recordsPerPage' => 20,
                'commentAbove' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_excluded_ips',
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_excluded_ips_example',
                'commentHtml' => true,
                'trigger' => [
                    'action' => 'show',
                    'field' => 'ip_firewall[smart_ip_blocker_enabled]',
                    'condition' => 'checked',
                ],
                'columns' => [
                    'ip' => [
                        'type' => 'string',
                        'title' => 'wobqqq.fortify::lang.fields.ip',
                    ],
                ],
            ],

            'ip_firewall[smart_ip_blocker_excluded_headers]' => [
                'label' => 'wobqqq.fortify::lang.fields.excluded_headers',
                'type' => 'datatable',
                'span' => 'full',
                'tab' => 'wobqqq.fortify::lang.tabs.ip_firewall',
                'adding' => true,
                'deleting' => true,
                'searching' => false,
                'recordsPerPage' => 20,
                'commentAbove' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_excluded_headers',
                'comment' => 'wobqqq.fortify::lang.comments.smart_ip_blocker_excluded_headers_example',
                'commentHtml' => true,
                'trigger' => [
                    'action' => 'show',
                    'field' => 'ip_firewall[smart_ip_blocker_enabled]',
                    'condition' => 'checked',
                ],
                'columns' => [
                    'header' => [
                        'type' => 'string',
                        'title' => 'wobqqq.fortify::lang.fields.header',
                    ],
                    'value' => [
                        'type' => 'string',
                        'title' => 'wobqqq.fortify::lang.fields.value',
                    ],
                ],
            ],
        ]);
    }

    private function presetCurrentIp(Fortify $fortify): void
    {
        if (isset($fortify->ip_firewall) && is_array($fortify->ip_firewall)) {
            /** @var array<string, mixed> $ipFirewall */
            $ipFirewall = $fortify->ip_firewall;
        } else {
            $ipFirewall = [];
        }

        /** @var array<int, mixed> $ipTable */
        $ipTable = Arr::get($ipFirewall, 'smart_ip_blocker_excluded_ips', []);

        $ips = array_column($ipTable, 'ip');

        $ip = Request::ip();

        if (!in_array($ip, $ips)) {
            $ipTable[] = ['ip' => $ip];

            $ipFirewall['smart_ip_blocker_excluded_ips'] = $ipTable;
            /** @noinspection PhpUndefinedFieldInspection */
            /** @phpstan-ignore-next-line */
            $fortify->ip_firewall = $ipFirewall;
        }
    }
}
