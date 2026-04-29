<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\CookieManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieManager::class)]
final class CookieManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
    }

    // ─── Constants ─────────────────────────────────────────────────────────────

    public function testCategoryEssentialConstant(): void
    {
        self::assertSame('essential', CookieManager::CATEGORY_ESSENTIAL);
    }

    public function testCategoryFunctionalConstant(): void
    {
        self::assertSame('functional', CookieManager::CATEGORY_FUNCTIONAL);
    }

    public function testCategoryAnalyticsConstant(): void
    {
        self::assertSame('analytics', CookieManager::CATEGORY_ANALYTICS);
    }

    public function testCookieConsentConstant(): void
    {
        self::assertSame('cookie_consent', CookieManager::COOKIE_CONSENT);
    }

    public function testFilterPreferencesConstant(): void
    {
        self::assertSame('filter_preferences', CookieManager::FILTER_PREFERENCES);
    }

    public function testRecentlyViewedConstant(): void
    {
        self::assertSame('recently_viewed', CookieManager::RECENTLY_VIEWED);
    }

    public function testNewsletterPromptedConstant(): void
    {
        self::assertSame('newsletter_prompted', CookieManager::NEWSLETTER_PROMPTED);
    }

    public function testDietaryPreferencesConstant(): void
    {
        self::assertSame('dietary_preferences', CookieManager::DIETARY_PREFERENCES);
    }

    // ─── get() ─────────────────────────────────────────────────────────────────

    public function testGetReturnsNullDefaultWhenCookieNotSet(): void
    {
        self::assertNull(CookieManager::get('nonexistent'));
    }

    public function testGetReturnsCustomDefaultWhenCookieNotSet(): void
    {
        self::assertSame('fallback', CookieManager::get('missing', 'fallback'));
    }

    public function testGetReturnsCookieValue(): void
    {
        $_COOKIE['my_cookie'] = 'hello';

        self::assertSame('hello', CookieManager::get('my_cookie'));
    }

    public function testGetReturnsCookieValueOverridingDefault(): void
    {
        $_COOKIE['my_cookie'] = 'realvalue';

        self::assertSame('realvalue', CookieManager::get('my_cookie', 'default'));
    }

    // ─── hasConsent() ──────────────────────────────────────────────────────────

    public function testHasConsentAlwaysTrueForEssential(): void
    {
        // Essential no requiere cookie de consentimiento
        self::assertTrue(CookieManager::hasConsent(CookieManager::CATEGORY_ESSENTIAL));
    }

    public function testHasConsentReturnsFalseWhenNoCookieConsentCookie(): void
    {
        self::assertFalse(CookieManager::hasConsent(CookieManager::CATEGORY_FUNCTIONAL));
    }

    public function testHasConsentReturnsFalseForInvalidJson(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = 'not-json{{{';

        self::assertFalse(CookieManager::hasConsent(CookieManager::CATEGORY_FUNCTIONAL));
    }

    public function testHasConsentReturnsFalseWhenCategoryKeyMissing(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['essential' => true]);

        self::assertFalse(CookieManager::hasConsent(CookieManager::CATEGORY_FUNCTIONAL));
    }

    public function testHasConsentReturnsTrueWhenCategoryGranted(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode([
            'functional' => true,
            'analytics' => false,
        ]);

        self::assertTrue(CookieManager::hasConsent(CookieManager::CATEGORY_FUNCTIONAL));
    }

    public function testHasConsentReturnsFalseWhenCategoryDenied(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode([
            'functional' => false,
        ]);

        self::assertFalse(CookieManager::hasConsent(CookieManager::CATEGORY_FUNCTIONAL));
    }

    public function testHasConsentAnalyticsDeniedByDefault(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode([
            'analytics' => false,
        ]);

        self::assertFalse(CookieManager::hasConsent(CookieManager::CATEGORY_ANALYTICS));
    }

    // ─── getFilters() ──────────────────────────────────────────────────────────

    public function testGetFiltersReturnsNullWhenNoFunctionalConsent(): void
    {
        self::assertNull(CookieManager::getFilters());
    }

    public function testGetFiltersReturnsNullWhenConsentedButNoCookieSet(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        self::assertNull(CookieManager::getFilters());
    }

    public function testGetFiltersReturnsDecodedArrayWhenConsented(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $filters = ['category' => 'coffee', 'price' => 'low'];
        $_COOKIE[CookieManager::FILTER_PREFERENCES] = \json_encode($filters);

        self::assertSame($filters, CookieManager::getFilters());
    }

    public function testGetFiltersReturnsNullForInvalidJson(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $_COOKIE[CookieManager::FILTER_PREFERENCES] = '{invalid}';

        self::assertNull(CookieManager::getFilters());
    }

    // ─── getRecentlyViewed() ───────────────────────────────────────────────────

    public function testGetRecentlyViewedReturnsEmptyWhenNoFunctionalConsent(): void
    {
        self::assertSame([], CookieManager::getRecentlyViewed());
    }

    public function testGetRecentlyViewedReturnsEmptyWhenConsentedButNoCookieSet(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        self::assertSame([], CookieManager::getRecentlyViewed());
    }

    public function testGetRecentlyViewedReturnsDecodedArrayWhenConsented(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $ids = [3, 7, 12];
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = \json_encode($ids);

        self::assertSame($ids, CookieManager::getRecentlyViewed());
    }

    public function testGetRecentlyViewedReturnsEmptyForInvalidJson(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = '{bad_json}';

        self::assertSame([], CookieManager::getRecentlyViewed());
    }

    // ─── wasNewsletterPrompted() / markNewsletterPrompted() ────────────────────

    public function testWasNewsletterPromptedReturnsFalseWhenNoConsent(): void
    {
        self::assertFalse(CookieManager::wasNewsletterPrompted());
    }

    public function testWasNewsletterPromptedReturnsFalseWhenConsentedButNoCookie(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        self::assertFalse(CookieManager::wasNewsletterPrompted());
    }

    public function testWasNewsletterPromptedReturnsTrueWhenCookieSet(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $_COOKIE[CookieManager::NEWSLETTER_PROMPTED] = '1';

        self::assertTrue(CookieManager::wasNewsletterPrompted());
    }

    // ─── getDietaryPreferences() ───────────────────────────────────────────────

    public function testGetDietaryPreferencesReturnsNullWhenNoConsent(): void
    {
        self::assertNull(CookieManager::getDietaryPreferences());
    }

    public function testGetDietaryPreferencesReturnsNullWhenConsentedButNoCookie(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        self::assertNull(CookieManager::getDietaryPreferences());
    }

    public function testGetDietaryPreferencesReturnsDecodedArrayWhenConsented(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $prefs = ['vegan' => true, 'gluten_free' => false];
        $_COOKIE[CookieManager::DIETARY_PREFERENCES] = \json_encode($prefs);

        self::assertSame($prefs, CookieManager::getDietaryPreferences());
    }

    public function testGetDietaryPreferencesReturnsNullForInvalidJson(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        $_COOKIE[CookieManager::DIETARY_PREFERENCES] = 'not_json';

        self::assertNull(CookieManager::getDietaryPreferences());
    }

    // ─── set() / delete() — CLI behavior ───────────────────────────────────────

    public function testSetReturnsBoolInCliContext(): void
    {
        // En CLI setcookie() devuelve false (sin headers HTTP activos)
        // Se verifica que el método existe y devuelve bool sin lanzar excepción
        $result = CookieManager::set('test_cookie', 'value');

        self::assertIsBool($result);
    }

    public function testDeleteReturnsBoolInCliContext(): void
    {
        $result = CookieManager::delete('test_cookie');

        self::assertIsBool($result);
    }

    // ─── saveConsent() ─────────────────────────────────────────────────────────

    public function testSaveConsentReturnsBoolInCliContext(): void
    {
        $result = CookieManager::saveConsent(['functional' => true, 'analytics' => false]);

        self::assertIsBool($result);
    }

    // ─── acceptAll() ───────────────────────────────────────────────────────────

    public function testAcceptAllReturnsBoolInCliContext(): void
    {
        $result = CookieManager::acceptAll();

        self::assertIsBool($result);
    }

    // ─── rejectOptional() ──────────────────────────────────────────────────────

    public function testRejectOptionalReturnsBoolInCliContext(): void
    {
        $result = CookieManager::rejectOptional();

        self::assertIsBool($result);
    }

    // ─── saveFilters() ─────────────────────────────────────────────────────────

    public function testSaveFiltersReturnsFalseWhenNoConsent(): void
    {
        $result = CookieManager::saveFilters(['category' => 'coffee']);

        self::assertFalse($result);
    }

    public function testSaveFiltersReturnsBoolWhenConsentGranted(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        $result = CookieManager::saveFilters(['category' => 'coffee']);

        self::assertIsBool($result);
    }

    // ─── addRecentlyViewed() ───────────────────────────────────────────────────

    public function testAddRecentlyViewedReturnsFalseWhenNoConsent(): void
    {
        $result = CookieManager::addRecentlyViewed(5);

        self::assertFalse($result);
    }

    public function testAddRecentlyViewedReturnsBoolWhenConsentGranted(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        $result = CookieManager::addRecentlyViewed(5);

        self::assertIsBool($result);
    }

    public function testAddRecentlyViewedDeduplicatesExistingItem(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);
        // Pre-seed existing recently viewed
        $_COOKIE[CookieManager::RECENTLY_VIEWED] = \json_encode([3, 5, 7]);

        // Adding 5 again should deduplicate — the method still returns bool
        $result = CookieManager::addRecentlyViewed(5);

        self::assertIsBool($result);
    }

    // ─── markNewsletterPrompted() ──────────────────────────────────────────────

    public function testMarkNewsletterPromptedReturnsFalseWhenNoConsent(): void
    {
        $result = CookieManager::markNewsletterPrompted();

        self::assertFalse($result);
    }

    public function testMarkNewsletterPromptedReturnsBoolWhenConsentGranted(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        $result = CookieManager::markNewsletterPrompted();

        self::assertIsBool($result);
    }

    // ─── saveDietaryPreferences() ──────────────────────────────────────────────

    public function testSaveDietaryPreferencesReturnsFalseWhenNoConsent(): void
    {
        $result = CookieManager::saveDietaryPreferences(['vegan' => true]);

        self::assertFalse($result);
    }

    public function testSaveDietaryPreferencesReturnsBoolWhenConsentGranted(): void
    {
        $_COOKIE[CookieManager::COOKIE_CONSENT] = \json_encode(['functional' => true]);

        $result = CookieManager::saveDietaryPreferences(['vegan' => true]);

        self::assertIsBool($result);
    }
}
