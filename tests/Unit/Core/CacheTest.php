<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * ¿Qué me quieres demostrar?
 * ¿Qué va a fallar en este test si se cambia el código?
 */

use App\Core\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests para el sistema de Cache Redis
 */
#[CoversClass(Cache::class)]
final class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar cache antes de cada test (Cache tiene fallback en memoria si Redis no está disponible)
        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Limpiar cache después de cada test
        Cache::flush();

        parent::tearDown();
    }

    /**
     * Test: Almacenar y recuperar un valor
     */
    public function testSetAndGet(): void
    {
        $key = 'test:simple';
        $value = 'Hello, Cache!';

        Cache::set($key, $value);

        $this->assertEquals($value, Cache::get($key));
    }

    /**
     * Test: Almacenar y recuperar un array
     */
    public function testSetAndGetArray(): void
    {
        $key = 'test:array';
        $value = ['name' => 'Komorebi', 'type' => 'Café'];

        Cache::set($key, $value);

        $this->assertEquals($value, Cache::get($key));
    }

    /**
     * Test: Almacenar y recuperar un objeto
     */
    public function testSetAndGetObject(): void
    {
        $key = 'test:object';
        $value = (object) ['id' => 1, 'name' => 'Product'];

        Cache::set($key, $value);

        $cached = Cache::get($key);
        $this->assertEquals($value, $cached);
    }

    /**
     * Test: TTL expira correctamente
     */
    public function testTTLExpiration(): void
    {
        $key = 'test:ttl';
        $value = 'Temporary value';

        Cache::set($key, $value, 1); // 1 segundo

        $this->assertEquals($value, Cache::get($key));

        sleep(2); // Esperar a que expire

        $this->assertNull(Cache::get($key));
    }

    /**
     * Test: has() detecta existencia
     */
    public function testHas(): void
    {
        $key = 'test:exists';

        $this->assertFalse(Cache::has($key));

        Cache::set($key, 'value');

        $this->assertTrue(Cache::has($key));
    }

    /**
     * Test: delete() elimina valores
     */
    public function testDelete(): void
    {
        $key = 'test:delete';

        Cache::set($key, 'value');
        $this->assertTrue(Cache::has($key));

        Cache::delete($key);
        $this->assertFalse(Cache::has($key));
    }

    /**
     * Test: deletePattern() elimina múltiples keys
     */
    public function testDeletePattern(): void
    {
        Cache::set('products:1', 'Product 1');
        Cache::set('products:2', 'Product 2');
        Cache::set('products:all', 'All products');
        Cache::set('cafes:1', 'Cafe 1');

        Cache::deletePattern('products:*');

        $this->assertNull(Cache::get('products:1'));
        $this->assertNull(Cache::get('products:2'));
        $this->assertNull(Cache::get('products:all'));
        $this->assertEquals('Cafe 1', Cache::get('cafes:1')); // No eliminado
    }

    /**
     * Test: remember() cachea resultado de callback
     */
    public function testRemember(): void
    {
        $key = 'test:remember';
        $callCount = 0;

        $callback = static function () use (&$callCount) {
            $callCount++;

            return 'Computed value';
        };

        // Primera llamada: ejecuta callback
        $value1 = Cache::remember($key, $callback, 3600);
        $this->assertEquals('Computed value', $value1);
        $this->assertEquals(1, $callCount);

        // Segunda llamada: retorna desde cache
        $value2 = Cache::remember($key, $callback, 3600);
        $this->assertEquals('Computed value', $value2);
        $this->assertEquals(1, $callCount); // No se ejecutó de nuevo
    }

    /**
     * Test: increment() incrementa contador atómicamente vía Redis INCRBY.
     * El valor de retorno es el nuevo entero; no se usa Cache::get() porque las
     * claves raw de incrBy son distintas al namespace PSR-16 del pool.
     */
    public function testIncrement(): void
    {
        $key = 'test:counter:atomic';

        $result1 = Cache::increment($key);
        // Con Redis disponible debe retornar int; sin Redis retorna false
        if ($result1 === false) {
            $this->markTestSkipped('Redis no disponible — increment() retorna false (correcto según spec)');
        }
        $this->assertSame(1, $result1);

        $result2 = Cache::increment($key, 5);
        $this->assertSame(6, $result2);
    }

    /**
     * Test: decrement() decrementa contador atómicamente vía Redis DECRBY.
     * El valor de retorno es el nuevo entero; no se usa Cache::get() porque las
     * claves raw de decrBy son distintas al namespace PSR-16 del pool.
     */
    public function testDecrement(): void
    {
        $key = 'test:counter:atomic';

        // Inicializar el contador a 10 usando increment atómico
        $init = Cache::increment($key, 10);
        if ($init === false) {
            $this->markTestSkipped('Redis no disponible — decrement() retorna false (correcto según spec)');
        }

        $result1 = Cache::decrement($key);
        $this->assertSame($init - 1, $result1);

        $result2 = Cache::decrement($key, 3);
        $this->assertSame($init - 4, $result2);
    }

    /**
     * Test: flush() limpia todo el cache
     */
    public function testFlush(): void
    {
        Cache::set('key1', 'value1');
        Cache::set('key2', 'value2');
        Cache::set('key3', 'value3');

        Cache::flush();

        $this->assertNull(Cache::get('key1'));
        $this->assertNull(Cache::get('key2'));
        $this->assertNull(Cache::get('key3'));
    }

    /**
     * Test: Manejar valores null
     */
    public function testNullValues(): void
    {
        $key = 'test:null';

        Cache::set($key, null);

        // null se almacena correctamente
        $this->assertTrue(Cache::has($key));
        $this->assertNull(Cache::get($key));
    }

    /**
     * Test: Claves largas
     */
    public function testLongKeys(): void
    {
        $key = str_repeat('long-key-name-', 20);
        $value = 'Value for long key';

        Cache::set($key, $value);

        $this->assertEquals($value, Cache::get($key));
    }

    /**
     * Test: Valores grandes
     */
    public function testLargeValues(): void
    {
        $key = 'test:large';
        $value = str_repeat('Large data ', 1000);

        Cache::set($key, $value);

        $this->assertEquals($value, Cache::get($key));
    }

    // ── TagAware operations ───────────────────────────────────────────────────

    /**
     * Test: setWithTags almacena valor recuperable vía Cache::get()
     */
    public function testSetWithTagsStoresValueRetrievableViaGet(): void
    {
        $result = Cache::setWithTags('tag:product:1', ['id' => 1, 'name' => 'Café'], ['products']);

        $this->assertTrue($result);
        $this->assertEquals(['id' => 1, 'name' => 'Café'], Cache::get('tag:product:1'));
    }

    /**
     * Test: invalidateTags elimina las entradas que llevan ese tag
     */
    public function testInvalidateTagsRemovesTaggedEntries(): void
    {
        Cache::setWithTags('tag:item:a', 'value-a', ['group1']);
        Cache::setWithTags('tag:item:b', 'value-b', ['group1', 'group2']);
        Cache::set('tag:item:c', 'value-c'); // sin tag — no debe borrarse

        $invalidated = Cache::invalidateTags(['group1']);

        $this->assertTrue($invalidated);
        $this->assertNull(Cache::get('tag:item:a'), 'item:a debería haberse invalidado');
        $this->assertNull(Cache::get('tag:item:b'), 'item:b debería haberse invalidado');
        $this->assertEquals('value-c', Cache::get('tag:item:c'), 'item:c sin tag no debe borrarse');
    }

    /**
     * Test: invalidateTags con tag inexistente retorna true sin error
     */
    public function testInvalidateTagsWithNonExistentTagReturnTrue(): void
    {
        $result = Cache::invalidateTags(['tag-que-no-existe-jamas']);

        $this->assertTrue($result);
    }

    /**
     * Test: computeIfAbsent ejecuta fn en cache miss y almacena el resultado
     */
    public function testComputeIfAbsentExecutesFnOnCacheMiss(): void
    {
        $callCount = 0;
        $fn = static function () use (&$callCount): string {
            $callCount++;
            return 'computed-value';
        };

        $result = Cache::computeIfAbsent('cia:miss:key', $fn, 3600);

        $this->assertEquals('computed-value', $result);
        $this->assertEquals(1, $callCount, 'La función debe ejecutarse exactamente una vez en miss');
    }

    /**
     * Test: computeIfAbsent NO ejecuta fn en cache hit
     */
    public function testComputeIfAbsentReturnsCachedValueOnHit(): void
    {
        $callCount = 0;
        $fn = static function () use (&$callCount): string {
            $callCount++;
            return 'computed-value';
        };

        // Primera llamada: miss → fn ejecutada
        Cache::computeIfAbsent('cia:hit:key', $fn, 3600);
        // Segunda llamada: hit → fn NO ejecutada
        $result = Cache::computeIfAbsent('cia:hit:key', $fn, 3600);

        $this->assertEquals('computed-value', $result);
        $this->assertEquals(1, $callCount, 'La función no debe ejecutarse en cache hit');
    }

    /**
     * Test: computeIfAbsent con tags permite invalidación posterior
     */
    public function testComputeIfAbsentWithTagsAllowsTagInvalidation(): void
    {
        $callCount = 0;
        $fn = static function () use (&$callCount): string {
            $callCount++;
            return 'tagged-value';
        };

        // Almacena con tags
        Cache::computeIfAbsent('cia:tagged:key', $fn, 3600, ['menu', 'products']);
        $this->assertEquals(1, $callCount);

        // Invalida por tag
        Cache::invalidateTags(['menu']);

        // Tras invalidación, fn debe ejecutarse de nuevo
        Cache::computeIfAbsent('cia:tagged:key', $fn, 3600, ['menu', 'products']);
        $this->assertEquals(2, $callCount, 'La función debe ejecutarse de nuevo tras invalidar el tag');
    }
}
