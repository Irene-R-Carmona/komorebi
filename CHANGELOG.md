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

## [Unreleased] — 2026-04-16

### Added

- `LoggingPDO` + `LoggingPDOStatement`: slow query logging with configurable threshold (100 ms default)
- `RequestLogMiddleware`: correlation ID propagation and per-request structured logging
- `LogContextProcessor` (Monolog processor) + `LogContext` static registry for enriching log entries
- `BaseService` helpers: `logDebug()`, `logWarning()`, `logCritical()` with enforced context
- PHPStan rule enforcing non-empty context on `error`/`critical` log calls
- `DashboardServiceInterface`, `RecentlyViewedServiceInterface`, `UserModelInterface`, `SessionManagementServiceInterface` — full interface extraction with container bindings
- 13 controller constructors updated to inject interfaces (not concrete classes)
- 21 new unit tests for service interfaces and controller wiring
- `.github/workflows/ci.yml`: CI pipeline (PHPStan level 5 + PHPUnit)
- `public/images/ui/placeholder.svg`: accessible SVG placeholder for broken image fallback
- `migrations/017_product_stock.sql`: per-product stock control (`stock_quantity`, NULL = unlimited)
- `migrations/018_api_tokens.sql`: stateless bearer token auth for external clients (SHA-256 stored only)

### Changed

- PHPUnit 12 → 13, PHPStan 1 → 2 (zero errors at level 5, baseline cleared)
- PHPStan baseline `phpstan-baseline.neon` emptied — all issues resolved, not suppressed
- Psalm removed from quality gate (PHPStan level 5 covers equivalent rules)
- PSR-12 normalized across entire codebase (`make cs-fix`)
- Docker: pinned all base image versions; Dockerfile.prod corrected (app code now copied)
- `npm`: replaced `@lhci/cli` (3 critical vulns) with Lighthouse standalone; zero audit warnings
- `phpunit.xml`: coverage thresholds set (`lowUpperBound` ≥ 50 %, `highLowerBound` ≥ 70 %)
- `WaitlistSeeder`: `r.name` → `r.code` (column rename alignment)
- `menu.js`: loading state initialised to `true` to prevent flash of unfiltered content

### Fixed

- `LoggingPDOStatement`: removed deprecated `PDOStatement::bindParam` callback — migrated to `execute()` pattern
- Quiz resultado (`resources/views/public/quiz/resultado.php`): `$cafeData` keys corrected — `imagen`→`image_url`, `nombre`→`name`, `descripcion`→`description`, `ubicacion`→`location`, `rating`→`rating_avg`
- Admin review modal (`resources/views/components/admin/review-card.php`): JS injection — replaced `'<?= e($author) ?>'` with `<?= json_encode($author) ?>` in Alpine `@click` handler
- Keeper dashboard (`resources/views/backoffice/keeper/dashboard.php`): KPI cards referenced nonexistent keys — `total_animals`→`healthy`, removed `avg_interactions` (not in service contract) →`monitoring`
- Vista 404: added "Volver al inicio" navigation link
- `resources/views/public/loyalty/card.php`: removed dead code array `$tierEmojis`
- Café and menu images: added `onerror` fallback pointing to `placeholder.svg`
- `scripts/apply-db.php`: added migrations 016, 017, 018 to execution sequence; added `time_slots > 0` pre-check for `ReservationSeeder`

### Security

- JS injection (OWASP A03) in admin review modal: user-supplied `author` was interpolated raw into an Alpine.js event string. Now uses `json_encode()` which produces a properly escaped JSON string safe for JS context.
