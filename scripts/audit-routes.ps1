param([string]$Base = "http://localhost:8080")

$routes = @(
    # Publicas
    "/ | Home"
    "/cafes | Cafes index"
    "/menu | Menu"
    "/quiz | Quiz"
    "/historia | Historia"
    "/faq | FAQ"
    "/contacto | Contacto"
    "/legal/privacidad | Legal privacidad"
    "/legal/cookies | Legal cookies"
    "/legal/terminos | Legal terminos"
    "/newsletter/verify | Newsletter verify"
    "/newsletter/unsubscribe | Newsletter unsubscribe"
    "/reservar | Reservar"
    "/reservas | Reservas"

    # Auth guest
    "/login | Login"
    "/registro | Registro"
    "/forgot-password | Forgot password"
    "/reset-password | Reset password"
    "/verify-email | Verify email"

    # Auth requerida (302 esperado)
    "/profile | Perfil"
    "/perfil | Perfil alias"
    "/account/sessions | Sessions"
    "/account/security | Security"
    "/account/change-password | Change password"
    "/reservas/mis-reservas | Mis reservas"
    "/user/waitlists | User waitlists"
    "/mis-favoritos | Favoritos"
    "/carrito | Carrito"
    "/loyalty/card | Loyalty card"

    # Admin
    "/admin | Admin redirect"
    "/admin/dashboard | Admin dashboard"
    "/admin/users | Admin users"
    "/admin/users/create | Admin users create"
    "/admin/roles | Admin roles"
    "/admin/cafes | Admin cafes"
    "/admin/menu | Admin menu"
    "/admin/menu/create | Admin menu create"
    "/admin/reviews | Admin reviews"
    "/admin/reservations | Admin reservations"
    "/admin/waitlists | Admin waitlists"
    "/admin/animals | Admin animals"
    "/admin/animals/create | Admin animals create"
    "/admin/settings | Admin settings"
    "/admin/logs | Admin logs"
    "/admin/data-viewer | Admin data-viewer"
    "/admin/logs/audit | Admin audit logs"
    "/admin/logs/auth | Admin auth logs"
    "/admin/reports | Admin reports"

    # Manager
    "/manager/dashboard | Manager dashboard"
    "/manager/reservations | Manager reservations"
    "/manager/reviews | Manager reviews"
    "/manager/staff | Manager staff"
    "/manager/reports | Manager reports"
    "/manager/cafe | Manager cafe"
    "/manager/products | Manager products"

    # Supervisor
    "/supervisor/dashboard | Supervisor dashboard"
    "/supervisor/assignments | Supervisor assignments"

    # Ops
    "/ops/reception | Reception"
    "/ops/reception/reservations | Reception reservations"
    "/ops/kitchen | Kitchen"
    "/ops/kitchen/history | Kitchen history"
    "/ops/kitchen/orders | Kitchen orders"

    # Keeper
    "/keeper/dashboard | Keeper dashboard"
    "/keeper/animals | Keeper animals"
    "/keeper/health-checks | Keeper health-checks"
    "/keeper/incidents | Keeper incidents"
    "/keeper/schedule | Keeper schedule"

    # API publica
    "/api/v1/menu/alergenos | API alergenos"
    "/api/v1/menu/productos | API productos"
    "/api/v1/menu/products/1 | API product by id"
    "/api/v1/holidays | API holidays"
    "/api/v1/holidays/2026-05-01 | API holiday date"
    "/api/v1/time-slots/available | API time-slots available"
    "/api/v1/time-slots/stats | API time-slots stats"
    "/api/v1/cafes | API cafes"
    "/api/v1/cafes/komorebi-akihabara | API cafe by slug"
    "/api/v1/passes | API passes"
    "/api/v1/waitlists/token-fake | API waitlist position"

    # Error pages
    "/error/419 | Error 419"
    "/error/429 | Error 429"
    "/error/503 | Error 503"
    "/redirect | Redirect interstitial"

    # Health
    "/health | Health check"

    # Inexistentes
    "/pagina-que-no-existe | 404 esperado"
    "/api/v1/ruta-invalida | API 404 esperado"
)

$results = @()
foreach ($entry in $routes) {
    $parts = $entry -split " \| ", 2
    $path = $parts[0].Trim()
    $label = if ($parts.Count -gt 1) { $parts[1].Trim() } else { $path }
    $raw = curl.exe -s -o "NUL" -w "%{http_code}" --max-redirs 0 "$Base$path" 2>$null
    $code = if ($raw -match '^\d+$') { [int]$raw } else { 0 }
    $results += [PSCustomObject]@{ Code = $code; Path = $path; Label = $label }
}

$ok = $results | Where-Object { $_.Code -ge 200 -and $_.Code -lt 300 }
$redir = $results | Where-Object { $_.Code -ge 300 -and $_.Code -lt 400 }
$err4 = $results | Where-Object { $_.Code -ge 400 -and $_.Code -lt 500 }
$err5 = $results | Where-Object { $_.Code -ge 500 }
$conn = $results | Where-Object { $_.Code -eq 0 }

Write-Host ""
Write-Host ""
Write-Host "========================================================"
Write-Host "  AUDITORIA DE RUTAS -- $($results.Count) rutas probadas"
Write-Host "  Base: $Base"
Write-Host "========================================================"
Write-Host "  2xx OK:           $($ok.Count)"
Write-Host "  3xx Redireccion:  $($redir.Count)"
Write-Host "  4xx Error:        $($err4.Count)"
Write-Host "  5xx Error:        $($err5.Count)"
Write-Host "  0 Sin conexion:   $($conn.Count)"
Write-Host ""

if ($err4.Count -gt 0) {
    Write-Host "--- 4xx ERRORES ---"
    $err4 | Format-Table -AutoSize Code, Path, Label
}
if ($err5.Count -gt 0) {
    Write-Host "--- 5xx ERRORES ---"
    $err5 | Format-Table -AutoSize Code, Path, Label
}
if ($conn.Count -gt 0) {
    Write-Host "--- SIN RESPUESTA (code=0) ---"
    $conn | Format-Table -AutoSize Code, Path, Label
}

Write-Host "--- TABLA COMPLETA (ordenada por codigo) ---"
$results | Sort-Object Code, Path | Format-Table -AutoSize Code, Path, Label
