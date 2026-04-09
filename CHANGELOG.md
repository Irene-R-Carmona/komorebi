# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [1.0.0] — 2026-03-28

### Added

- Full MVC framework built on PHP 8.4 with PSR-7/PSR-15 middleware stack
- FrankenPHP + Caddy as the application server (HTTP/2 + Brotli)
- Multi-role RBAC system: admin, manager, supervisor, reception, kitchen, keeper, user
- Reservation system with time slots, waitlist, and capacity management
- Loyalty card system with configurable reward catalog
- Animal care module: health checks, incidents, inter-species compatibility rules
- Staff shift management with supervisor assignment tracking
- Review and moderation workflow (pending → approved/rejected)
- Async job queue via Redis: email dispatch, notifications, telegram bot
- PSR-14 event system (Symfony EventDispatcher under the hood)
- Newsletter subscription management with GDPR compliance
- OpenAPI 3.1 specification with Postman collection for API testing
- End-to-end tests with Playwright (console errors + WCAG 2.1 AA accessibility)
- PHPStan level 5 + Psalm level 5 static analysis

### Security

- Argon2id password hashing
- CSRF protection on all state-changing forms
- Rate limiting on authentication endpoints
- HTTP security headers (CSP, HSTS, X-Frame-Options, etc.)
- Prepared statements throughout (no raw SQL in controllers)
- Secrets loaded via `SecretLoader` (env var or `/run/secrets/` Docker secrets)

---

## [Unreleased]

<!-- Add entries here as work progresses -->
