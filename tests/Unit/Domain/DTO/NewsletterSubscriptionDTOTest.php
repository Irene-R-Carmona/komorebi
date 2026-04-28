<?php

declare(strict_types=1);

/**
 * ¿Qué pruebas aquí?
 * NewsletterSubscriptionDTO: construcción desde fila BD (fromArray) y serialización (toViewArray).
 *
 * ¿Qué me quieres demostrar?
 * Que fromArray convierte correctamente todos los campos nullable y que toViewArray
 * devuelve exactamente las claves esperadas por las vistas.
 *
 * ¿Qué va a fallar en este test si se cambia el código?
 * Si se eliminan campos nullable, si cambian las claves en toViewArray, o si se pierde
 * el manejo de valores por defecto en fromArray.
 */

namespace Tests\Unit\Domain\DTO;

use App\Domain\DTO\NewsletterSubscriptionDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NewsletterSubscriptionDTO::class)]
final class NewsletterSubscriptionDTOTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fullRow(): array
    {
        return [
            'id'              => 7,
            'email'           => 'suscriptor@ejemplo.com',
            'token'           => 'abc123token',
            'confirmed_at'    => '2025-05-10 12:00:00',
            'unsubscribed_at' => null,
            'created_at'      => '2025-05-01 09:00:00',
            'expires_at'      => '2025-11-01 09:00:00',
        ];
    }

    public function testFromArrayPopulatesAllFields(): void
    {
        $dto = NewsletterSubscriptionDTO::fromArray($this->fullRow());

        $this->assertSame(7, $dto->id);
        $this->assertSame('suscriptor@ejemplo.com', $dto->email);
        $this->assertSame('abc123token', $dto->token);
        $this->assertSame('2025-05-10 12:00:00', $dto->confirmed_at);
        $this->assertNull($dto->unsubscribed_at);
        $this->assertSame('2025-05-01 09:00:00', $dto->created_at);
        $this->assertSame('2025-11-01 09:00:00', $dto->expires_at);
    }

    public function testFromArrayUsesDefaultsForMissingFields(): void
    {
        $dto = NewsletterSubscriptionDTO::fromArray([]);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->email);
        $this->assertNull($dto->token);
        $this->assertNull($dto->confirmed_at);
        $this->assertNull($dto->unsubscribed_at);
        $this->assertNull($dto->created_at);
        $this->assertNull($dto->expires_at);
    }

    public function testFromArrayHandlesNullableFieldsExplicitNull(): void
    {
        $row = [
            'id'              => 3,
            'email'           => 'test@test.com',
            'token'           => null,
            'confirmed_at'    => null,
            'unsubscribed_at' => null,
            'created_at'      => null,
            'expires_at'      => null,
        ];

        $dto = NewsletterSubscriptionDTO::fromArray($row);

        $this->assertNull($dto->token);
        $this->assertNull($dto->confirmed_at);
        $this->assertNull($dto->unsubscribed_at);
    }

    public function testToViewArrayContainsAllRequiredKeys(): void
    {
        $dto  = NewsletterSubscriptionDTO::fromArray($this->fullRow());
        $view = $dto->toViewArray();

        foreach (['id', 'email', 'token', 'confirmed_at', 'unsubscribed_at', 'created_at', 'expires_at'] as $key) {
            $this->assertArrayHasKey($key, $view, "toViewArray debe contener la clave '{$key}'");
        }
    }

    public function testToViewArrayValuesMatchProperties(): void
    {
        $dto  = NewsletterSubscriptionDTO::fromArray($this->fullRow());
        $view = $dto->toViewArray();

        $this->assertSame($dto->id, $view['id']);
        $this->assertSame($dto->email, $view['email']);
        $this->assertSame($dto->token, $view['token']);
        $this->assertSame($dto->confirmed_at, $view['confirmed_at']);
        $this->assertSame($dto->unsubscribed_at, $view['unsubscribed_at']);
        $this->assertSame($dto->created_at, $view['created_at']);
        $this->assertSame($dto->expires_at, $view['expires_at']);
    }
}
