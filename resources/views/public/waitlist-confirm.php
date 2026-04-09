<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Reserva - Komorebi Café</title>
    <link rel="stylesheet" href="/css/main.css">
    <style>
        .confirm-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            text-align: center;
            font-size: 4rem;
            margin: 2rem 0;
        }

        .btn-confirm {
            width: 100%;
            padding: 1rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-confirm:hover {
            background: #059669;
        }

        .btn-confirm:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #ef4444;
            margin: 1rem 0;
        }

        .countdown {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            color: #dc2626;
            margin: 1.5rem 0;
        }

        .info-card {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 6px;
            margin: 1.5rem 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>

<body>
    <div class="confirm-container">
        <div class="success-icon">🎉</div>

        <h1 style="text-align: center; color: #111827; margin-bottom: 1rem;">
            ¡Tu Plaza Está Disponible!
        </h1>

        <p style="text-align: center; color: #6b7280; margin-bottom: 2rem;">
            Confirma tu reserva antes de que expire el tiempo
        </p>

        <div class="countdown" id="countdown">
            Cargando...
        </div>

        <div class="info-card">
            <h3 style="margin-top: 0; color: #374151;">Detalles de tu Reserva</h3>

            <div class="info-row">
                <span style="color: #6b7280;">Fecha:</span>
                <strong><?= htmlspecialchars(date('d/m/Y', strtotime($waitlist['time_slot']['date'])), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="info-row">
                <span style="color: #6b7280;">Hora:</span>
                <strong><?= htmlspecialchars(date('H:i', strtotime($waitlist['time_slot']['time'])), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>

            <div class="info-row">
                <span style="color: #6b7280;">Personas:</span>
                <strong><?= (int) $waitlist['guest_count'] ?></strong>
            </div>

            <?php if (!empty($waitlist['special_requests'])): ?>
                <div class="info-row">
                    <span style="color: #6b7280;">Notas:</span>
                    <strong><?= htmlspecialchars($waitlist['special_requests'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" id="confirmForm" style="margin-top: 2rem;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn-confirm" id="confirmBtn">
                ✅ Confirmar Reserva
            </button>
        </form>

        <div id="errorMessage" class="alert-danger" style="display: none;"></div>

        <p style="text-align: center; color: #9ca3af; font-size: 0.9rem; margin-top: 2rem;">
            Al confirmar, aceptas los términos y condiciones de reserva de Komorebi Café
        </p>
    </div>
    <div id="waitlist-meta" data-expires-at="<?= htmlspecialchars($waitlist['expires_at'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <script src="/js/pages/waitlistConfirm.js" nonce="<?= $cspNonce ?? '' ?>"></script>
</body>

</html>
