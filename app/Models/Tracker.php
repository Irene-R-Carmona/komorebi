<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Domain object para Tracker.
 *
 * Solo constantes de dominio — sin PDO, sin queries.
 * Todo el acceso a datos vive en App\Repositories\TrackerRepository.
 */
final class Tracker
{
    public const string STATUS_AVAILABLE = 'available';
    public const string STATUS_IN_USE    = 'in_use';
    public const string STATUS_LOST      = 'lost';

    public const string TYPE_TOKEN  = 'token';
    public const string TYPE_BEEPER = 'beeper';
    public const string TYPE_NFC    = 'nfc';
    public const string TYPE_QR     = 'qr';

    public const array VALID_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_IN_USE,
        self::STATUS_LOST,
    ];

    public const array VALID_TYPES = [
        self::TYPE_TOKEN,
        self::TYPE_BEEPER,
        self::TYPE_NFC,
        self::TYPE_QR,
    ];
}
