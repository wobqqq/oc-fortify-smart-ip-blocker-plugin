# Smart IP Blocker

**Smart IP Blocker** automatically blocks IP addresses that exceed a defined request rate threshold.

This extension enhances your protection against brute-force attacks and traffic abuse as part of the [Fortify](https://octobercms.com/plugin/wobqqq-fortify) suite.

## 📊 Security Dashboard Widget

Fortify includes a built-in dashboard widget that gives you a real-time overview of your system’s security status.

- Highlights critical vulnerabilities and misconfigurations
- Provides quick access to all security checks and tools
- Helps you identify and fix issues in one place

This widget acts as a central hub, allowing you to monitor and manage your application's security at a glance.

## 🚀 Features

- Automatic IP blocking based on request rate
- Configurable request thresholds
- Protection against DDoS and brute-force attacks
- Real-time monitoring
- Works together with [IP Blocker](https://octobercms.com/plugin/wobqqq-fortifyipblocker) for manual protection

## 🔗 Related Plugins

- [Fortify](https://octobercms.com/plugin/wobqqq-fortify) – comprehensive security suite
- [Admin IP Access](https://octobercms.com/plugin/wobqqq-fortifyadminipaccess) – restrict admin panel access by IP
- [IP Blocker](https://octobercms.com/plugin/wobqqq-fortifyipblocker) – manually block specific IP addresses
- [Input Sanitizer](https://octobercms.com/plugin/wobqqq-fortifyinputsanitizer) – block and sanitize malicious input
- [CSP](https://octobercms.com/plugin/wobqqq-fortifycsp) – add Content Security Policy headers to prevent XSS

## 📦 Requirements

- PHP 8.2 or higher
- October CMS 3.0 or higher

## 💻 Usage

All configuration and management is handled via the October CMS admin panel.

**Admin Panel:**
Navigate to `Settings -> Fortify` and enable **Smart IP Blocker**. Configure request limits in the interface.

**Console Commands:**

- Remove an IP from the block list:
```bash
php artisan wobqqq.fortify:smart-ip-blocker:remove-ip {ip}
```

- Disable Smart IP Blocker module:

```bash
php artisan wobqqq.fortify:smart-ip-blocker:disable
```
