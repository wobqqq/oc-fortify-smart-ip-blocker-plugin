# AGENTS.md

Guidance for AI coding agents (Claude Code, Codex, Junie, Cursor) working in this repository.

## What this is

**Smart IP Blocker** (`Wobqqq.FortifySmartIpBlocker`) is a free module of the Fortify security suite for October CMS 3.x/4.x (built and tested against 4.4 on Laravel 12, PHP 8.2+). It counts the requests of every IP per minute and bans an IP that exceeds the limit for a number of hours, on the site and in the backend, answering 429 with `Retry-After` and the page the administrator chose.

It requires the core plugin [`Wobqqq.Fortify`](https://github.com/wobqqq/oc-fortify-plugin): the settings live in the core's `Wobqqq\Fortify\Models\Fortify` record under the `ip_firewall.smart_ip_blocker_*` key and appear on **Settings → Fortify**, and the module draws its own item on the core's dashboard widget.

This is a **security product installed on production sites**. A bug here locks administrators or visitors out, or silently leaves a site unprotected. Security and safe upgrades come before everything else.

## The self-check gate (run before every commit)

Everything runs in Docker; the host needs no PHP.

```bash
make install        # composer install inside the php container (the core comes from Packagist, as `wobqqq/fortify-plugin`)
make code.fix       # composer normalize, rector, php-cs-fixer
make code.check     # validate, normalize --dry-run, audit, php -l, yaml-lint, cs, rector, PHPStan max
make test           # Pest
make test.coverage  # Pest with pcov, fails below 90 %
make ready          # all of the above
```

`make ready` must pass. PHPStan runs at `level: max` with strict rules and **no baseline**: fix the type, never add an ignore. Advisories reported by `composer audit` are fixed by updating the package, never ignored.

## How the code is laid out

| Path | Holds |
|------|-------|
| `Plugin.php` | Wiring: console commands, the `smart_ip_blocker_current_ip` validation rule, the listener, the middleware. |
| `services/SmartIpBlockerService.php` | The rate limit: exclusions, the per-minute counter, the ban, the bounded list of tracked IPs, the middleware registration. |
| `http/middlewares/SmartIpBlockerMiddleware.php` | Runs the check on every request of `cms.middleware_group` and `backend.middleware_group`. |
| `transformers/FortifyTransformer.php` | Turns the stored settings into `SmartIpBlockerDto`, with safe fallbacks for broken values. |
| `cache/, instances/` | The cached DTO (cleared on every settings save) and its per-request memo. |
| `listeners/FortifyListener.php` | Wires the events to `SettingsService` and clears the module's cache when the settings are saved or deleted. |
| `services/SettingsService.php` | The settings form fields, their validation rules and defaults, the dashboard item. |
| `validator/rules/SmartIpBlockerCurrentIpRule.php` | Refuses an exclusion list that no longer covers the administrator saving it. |
| `console/` | `remove-ip` and `disable`, the recovery path. |
| `updates/version.yaml` | The version history the marketplace reads from `main`. |

### Working with the core

- The core is a separate plugin that sites update on their own schedule. Use only the core's public API (listed in the core's AGENTS.md: the `Fortify` settings model, `FortifyEvent`, `View`, `WidgetItemColor`, the widget DTOs and `FortifyTransformer::widget*Dto()`, `BasicCache::cacheKey()`/`TTL`). A new core API is used only behind a check (`method_exists`, `enum_exists`) with a fallback, so the module keeps working on every released core.
- Settings are validated by rules the module adds to the core model. Add them in `Fortify::extend()` **and** when the settings form is built: the settings instance may exist before the module extends the model.
- Caches are cleared on the `eloquent.saved` / `eloquent.deleted` events of the core model, never with `bindEvent()` on an instance, for the same reason.

## Architecture

Read the architecture skills before changing how the module is structured: `application-layer`, `dependency-injection`, `error-handling`, `validation`, `events`, `testing-architecture`, `domain-layer-cqrs` and `plugin-boundaries`. The listener only wires events: the settings section (defaults, rules, fields, the dashboard line) lives in `services/SettingsService.php`.

## Upgrading installed sites safely

Read the `plugin-upgrades` skill before changing anything that reaches a site that already runs the module: a new version in `updates/version.yaml` for every shipped change, an update script for every change to what is stored, a new cache key for every change to a cached object's shape, and defaults that cannot lock anyone out.

## Security rules (always)

Read the `fortify-security` skill for the full checklist. For this module in particular:

- The IP is `Request::ip()`: behind a proxy or a CDN it is the proxy's unless October's trusted proxies are configured. Never read `X-Forwarded-For` yourself.
- An excluded header value is matched as a case-insensitive substring and **is sent by the client**, so it can be spoofed: it is a convenience for well-behaved bots, never a security boundary. Say so wherever it is offered.
- The cache keys are namespaced (`wobqqq.fortify.sib.`); the store is shared with the whole application.
- Every request of every visitor runs the check: keep it to a few cache operations, no database query, no loop over settings that grows with traffic.
- The administrator's own IP stays excluded (the validation rule and `presetCurrentIp()`): a change must not make it possible to save a list that bans the person saving it.

Recovery from the console, for an administrator who locked themselves out:

- `php artisan wobqqq.fortify:smart-ip-blocker:remove-ip {ip}` — lifts a ban and resets the IP's request count.
- `php artisan wobqqq.fortify:smart-ip-blocker:disable` — turns the module off.

## Tests

Pest 4 on Orchestra Testbench (Laravel 12) with the real `october/rain` and the core plugin from Composer. The licensed October modules are not installable in CI, so `tests/Stubs/October.php` reproduces the classes the plugins touch with October 4.4's behaviour (keep it identical to the core's copy). `tests/TestCase.php` boots the core and the module like October does. Read the `plugin-testing` skill.

## Git workflow

- `main` is protected: **never push to it and never force-push.** Every change goes through a pull request:
  1. branch off the latest `main`, named after the change (`fix/…`, `feat/…`, `chore/…`, `docs/…`);
  2. commit on the branch and `git push -u origin <branch>`;
  3. open a pull request with the template filled in (what changes, what it means for sites that upgrade);
  4. merge only once CI is green, then delete the branch.
- A release is a tag pushed on a merged commit of `main` (the `plugin-upgrades` skill says how); the tag is the only thing pushed outside a pull request.
- Code, comments, commit messages, pull requests, issues and documentation are written in **English**.

## Conventions

- `declare(strict_types=1);` in every PHP file; PSR-12 via php-cs-fixer (`(int)$x` without a space, imported classes).
- Code documents itself: names over comments. A comment explains a non-obvious *why*, in one sentence.
- DTOs are `final readonly`; services and transformers are `final`.
- October patterns over Laravel ones: model validation, form fields added in `backend.form.extendFields`, `Plugin.php` registration, the core's `lang` keys (read the `octobercms-*` skills).
- Commits: imperative subject saying what the change does for the site, a body with the why.
