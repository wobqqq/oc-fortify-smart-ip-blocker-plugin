# Smart IP Blocker

[![CI](https://github.com/wobqqq/oc-fortify-smart-ip-blocker-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/wobqqq/oc-fortify-smart-ip-blocker-plugin/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/wobqqq/fortifysmartipblocker-plugin)](https://packagist.org/packages/wobqqq/fortifysmartipblocker-plugin)
[![Downloads](https://img.shields.io/packagist/dt/wobqqq/fortifysmartipblocker-plugin)](https://packagist.org/packages/wobqqq/fortifysmartipblocker-plugin)
[![Marketplace](https://img.shields.io/badge/October%20CMS-Marketplace-e24848)](https://octobercms.com/plugin/wobqqq-fortifysmartipblocker)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://github.com/wobqqq/oc-fortify-smart-ip-blocker-plugin/blob/main/composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](https://github.com/wobqqq/oc-fortify-smart-ip-blocker-plugin/blob/main/phpstan.neon.dist)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/wobqqq/oc-fortify-smart-ip-blocker-plugin/blob/main/LICENSE.md)

**Smart IP Blocker** automatically blocks IP addresses that exceed a defined request rate threshold.

This extension enhances your protection against brute-force attacks and traffic abuse as part of the [Fortify](https://octobercms.com/plugin/wobqqq-fortify) suite.

## 📊 Security Dashboard Widget

Fortify includes a dashboard widget that gives you an overview of your application’s security status.

- Highlights critical vulnerabilities and misconfigurations
- Provides quick access to all security checks and tools
- Helps you identify and fix issues in one place

This widget acts as a central hub, allowing you to monitor and manage your application's security at a glance.

## 🚀 Features

- Automatic IP blocking based on request rate
- Configurable request thresholds
- Slows down brute-force attacks and request floods coming from one address
- Works together with [IP Blocker](https://octobercms.com/plugin/wobqqq-fortifyipblocker) for manual protection

## 🔗 Related Plugins

- [Fortify](https://octobercms.com/plugin/wobqqq-fortify) – comprehensive security suite
- [Admin IP Access](https://octobercms.com/plugin/wobqqq-fortifyadminipaccess) – restrict admin panel access by IP
- [IP Blocker](https://octobercms.com/plugin/wobqqq-fortifyipblocker) – manually block specific IP addresses
- [Input Sanitizer](https://octobercms.com/plugin/wobqqq-fortifyinputsanitizer) – block and sanitize malicious input
- [CSP](https://octobercms.com/plugin/wobqqq-fortifycsp) – add Content Security Policy headers to prevent XSS

## 📦 Requirements

- PHP 8.2 or higher
- October CMS 3.x or 4.x
- [Fortify](https://octobercms.com/plugin/wobqqq-fortify)

## 📥 Installation

| From | How |
|---|---|
| **October CMS Marketplace** | [octobercms.com/plugin/wobqqq-fortifysmartipblocker](https://octobercms.com/plugin/wobqqq-fortifysmartipblocker), or **Settings → Updates & Plugins → Install plugins** in the backend and search for “Fortify Smart IP Blocker” |
| **Artisan** | `php artisan plugin:install Wobqqq.FortifySmartIpBlocker` |
| **Composer** | `composer require wobqqq/fortifysmartipblocker-plugin` then `php artisan october:migrate` |

It needs the [Fortify](https://octobercms.com/plugin/wobqqq-fortify) core plugin: Composer installs it with the module; when installing from the marketplace, install **Fortify** first.

## 💻 Usage

All configuration and management is handled via the October CMS admin panel.

**Admin Panel:**
Navigate to `Settings -> Fortify` and enable **Smart IP Blocker**. Configure request limits in the interface.

**Console Commands:**

- Lift the ban on an IP and reset its request count:
```bash
php artisan wobqqq.fortify:smart-ip-blocker:remove-ip {ip}
```

- Disable Smart IP Blocker module:

```bash
php artisan wobqqq.fortify:smart-ip-blocker:disable
```

## ⬆️ Upgrading

- **1.0.4** — installing the module with Composer installs the Fortify core with it. Nothing changes on an existing site.
- **1.0.3** — the request limit is counted per minute, as the setting says; before, the count was kept for the whole ban duration, so a regular visitor could be banned after enough requests spread over hours. A banned visitor now gets `429 Too Many Requests` with a `Retry-After` header instead of `403`. An excluded header matches when its value is contained in the request's header (`Googlebot` matches the full Googlebot user agent), and several values may be listed for the same header. Bans issued by the previous version are lifted by the update.

## ⚠️ Good to know

- Behind a load balancer, proxy or CDN, configure October's trusted proxies so that the visitor's IP, not the proxy's, is counted.
- Excluded headers are sent by the client and can be forged: use them for convenience (well-behaved bots), and the excluded IPs for anything you rely on.

## 🔒 Security

Please report a vulnerability privately, as described in [SECURITY.md](https://github.com/wobqqq/oc-fortify-smart-ip-blocker-plugin/blob/main/SECURITY.md).

## 🛠️ Development

The toolchain runs in Docker, the host needs nothing but `docker` and `make`. The module is tested together with the [Fortify core](https://github.com/wobqqq/oc-fortify-plugin), which Composer installs from Packagist.

```bash
make install        # composer install
make code.fix       # composer normalize, Rector, PHP CS Fixer
make code.check     # composer validate/audit, php -l, YAML lint, PHP CS Fixer, Rector, PHPStan (level max)
make test.coverage  # Pest with coverage (90 % minimum)
make ready          # everything above
```

Every pull request runs the same checks on GitHub Actions, plus a syntax check on PHP 8.2 and a run against the latest core. Pushing a tag that matches the last version in `updates/version.yaml` publishes it as a GitHub release and to the October CMS marketplace once CI has passed.

