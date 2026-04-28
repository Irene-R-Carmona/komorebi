<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Override;

/**
 * DTO de suscripción al newsletter.
 *
 * Encapsula una fila de la tabla `newsletter_subscriptions`
 * con los campos necesarios para la lógica de confirmación,
 * baja y validación de tokens.
 */
final readonly class NewsletterSubscriptionDTO implements DomainTransferObject
{
    public function __construct(
        public int     $id,
        public string  $email,
        public ?string $token,
        public ?string $confirmed_at,
        public ?string $unsubscribed_at,
        public ?string $created_at,
        public ?string $expires_at,
    ) {}

    /**
     * Construye el DTO desde una fila cruda de la base de datos.
     *
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id:               (int)    ($row['id']               ?? 0),
            email:            (string) ($row['email']            ?? ''),
            token:            isset($row['token'])            ? (string) $row['token']            : null,
            confirmed_at:     isset($row['confirmed_at'])     ? (string) $row['confirmed_at']     : null,
            unsubscribed_at:  isset($row['unsubscribed_at'])  ? (string) $row['unsubscribed_at']  : null,
            created_at:       isset($row['created_at'])       ? (string) $row['created_at']       : null,
            expires_at:       isset($row['expires_at'])       ? (string) $row['expires_at']       : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toViewArray(): array
    {
        return [
            'id'              => $this->id,
            'email'           => $this->email,
            'token'           => $this->token,
            'confirmed_at'    => $this->confirmed_at,
            'unsubscribed_at' => $this->unsubscribed_at,
            'created_at'      => $this->created_at,
            'expires_at'      => $this->expires_at,
        ];
    }
}
