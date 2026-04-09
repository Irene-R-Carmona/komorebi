<?php

declare(strict_types=1);

/**
 * Vista: Política de Privacidad (RGPD Artículo 13)
 * Diseño: TOC lateral + contenido estrecho (estilo Stripe/Vercel)
 */
?>
<div class="static-page">
    <!-- Hero -->
    <header class="static-hero">
        <span class="static-hero__icon">🔒</span>
        <h1 class="static-hero__title">Política de Privacidad</h1>
        <p class="static-hero__subtitle">Transparencia total sobre cómo tratamos tus datos personales</p>
        <p class="static-hero__date">Última actualización: 1 de febrero de 2026</p>
    </header>

    <!-- Layout con sidebar -->
    <div class="static-layout">
        <!-- TOC Sidebar -->
        <aside class="static-sidebar">
            <h2 class="static-sidebar__title">En esta página</h2>
            <nav class="static-sidebar__nav">
                <a href="#responsable" class="static-sidebar__link">1. Responsable del tratamiento</a>
                <a href="#datos" class="static-sidebar__link">2. Datos que recogemos</a>
                <a href="#finalidades" class="static-sidebar__link">3. Finalidades del tratamiento</a>
                <a href="#legitimacion" class="static-sidebar__link">4. Legitimación</a>
                <a href="#destinatarios" class="static-sidebar__link">5. Destinatarios</a>
                <a href="#transferencias" class="static-sidebar__link">6. Transferencias internacionales</a>
                <a href="#conservacion" class="static-sidebar__link">7. Conservación de datos</a>
                <a href="#derechos" class="static-sidebar__link">8. Tus derechos (RGPD)</a>
                <a href="#seguridad" class="static-sidebar__link">9. Seguridad</a>
                <a href="#cookies" class="static-sidebar__link">10. Cookies</a>
                <a href="#cambios" class="static-sidebar__link">11. Cambios en la política</a>
                <a href="#contacto" class="static-sidebar__link">12. Contacto</a>
            </nav>
        </aside>

        <!-- Content -->
        <article class="static-content">
            <h2 id="responsable">1. Responsable del tratamiento</h2>
            <p><strong>Identidad:</strong> Komorebi Café (Proyecto Final de Carrera)<br>
                <strong>Dirección:</strong> Calle Ficticia 123, 28001 Madrid, España<br>
                <strong>Email:</strong> <a href="mailto:privacidad@komorebi.cafe">privacidad@komorebi.cafe</a><br>
                <strong>Teléfono:</strong> +34 910 123 456
            </p>

            <div class="static-content__callout static-content__callout--info">
                <strong>Nota académica:</strong> Este es un proyecto académico (PFC). Los datos se tratan exclusivamente con fines educativos bajo consentimiento explícito de los participantes.
            </div>

            <h2 id="datos">2. Datos que recogemos</h2>
            <p>Recogemos y tratamos las siguientes categorías de datos personales:</p>

            <h3>2.1. Datos de registro</h3>
            <ul>
                <li><strong>Obligatorios:</strong> Nombre completo, email, contraseña (hash Argon2id)</li>
                <li><strong>Opcionales:</strong> Teléfono (solo si vinculas Telegram), foto de perfil</li>
            </ul>

            <h3>2.2. Datos de reservas</h3>
            <ul>
                <li>Fecha y hora de reserva</li>
                <li>Número de personas</li>
                <li>Preferencias (mesa exterior, accesibilidad, mascotas)</li>
                <li>Notas especiales (alergias, celebraciones)</li>
            </ul>

            <h3>2.3. Datos de navegación (automáticos)</h3>
            <ul>
                <li>Dirección IP (anonimizada)</li>
                <li>Navegador y sistema operativo</li>
                <li>Páginas visitadas y tiempo de permanencia</li>
                <li>Cookies técnicas (sesión, CSRF, consentimiento)</li>
            </ul>

            <h3>2.4. Datos de comunicaciones</h3>
            <ul>
                <li>Emails enviados/recibidos (historial de notificaciones)</li>
                <li>Mensajes de contacto desde formulario web</li>
                <li>Telegram Chat ID (solo si vinculas voluntariamente)</li>
            </ul>

            <h2 id="finalidades">3. Finalidades del tratamiento</h2>
            <p>Tratamos tus datos para las siguientes finalidades:</p>

            <h3>3.1. Gestión de cuenta de usuario</h3>
            <p>Permitir registro, login, perfil, gestión de preferencias.</p>

            <h3>3.2. Gestión de reservas</h3>
            <p>Procesar reservas de mesa, enviar confirmaciones, recordatorios y gestionar cancelaciones.</p>

            <h3>3.3. Comunicaciones relacionadas con el servicio</h3>
            <p>Envío de confirmaciones de reserva, recordatorios 24h antes, notificaciones de cambios.</p>

            <h3>3.4. Marketing (opcional - requiere consentimiento)</h3>
            <p>Newsletter con novedades, eventos especiales, promociones. Puedes cancelar suscripción en cualquier momento.</p>

            <h3>3.5. Mejora del servicio</h3>
            <p>Análisis agregado (anónimo) de uso de la plataforma para mejorar experiencia de usuario.</p>

            <h2 id="legitimacion">4. Legitimación</h2>
            <p>La base legal para el tratamiento de tus datos es:</p>
            <ul>
                <li><strong>Ejecución de contrato:</strong> Gestión de reservas y cuenta de usuario (RGPD Art. 6.1.b)</li>
                <li><strong>Consentimiento:</strong> Newsletter, vinculación Telegram, cookies no esenciales (RGPD Art. 6.1.a)</li>
                <li><strong>Interés legítimo:</strong> Seguridad (prevención fraude), análisis agregado (RGPD Art. 6.1.f)</li>
                <li><strong>Obligación legal:</strong> Conservación de facturas (normativa fiscal) (RGPD Art. 6.1.c)</li>
            </ul>

            <h2 id="destinatarios">5. Destinatarios</h2>
            <p><strong>NO vendemos ni cedemos tus datos a terceros.</strong> Solo compartimos datos con:</p>

            <h3>5.1. Proveedores de servicios (encargados del tratamiento)</h3>
            <ul>
                <li><strong>Hosting:</strong> Hetzner Cloud (Alemania) - Infraestructura servidor</li>
                <li><strong>Email:</strong> Mailpit (dev) / SendGrid (producción) - Envío notificaciones</li>
                <li><strong>Telegram:</strong> API Telegram (opcional) - Solo si vinculas cuenta</li>
            </ul>

            <div class="static-content__callout static-content__callout--warning">
                <strong>⚠️ Importante:</strong> Todos los proveedores tienen DPA (Data Processing Agreement) y cumplen RGPD.
            </div>

            <h3>5.2. Autoridades competentes</h3>
            <p>Si existe obligación legal (ej: orden judicial).</p>

            <h2 id="transferencias">6. Transferencias internacionales</h2>
            <p>Tus datos se almacenan en servidores ubicados en la <strong>Unión Europea</strong> (Alemania).</p>
            <p>Si utilizas Telegram, el bot se comunica con API de Telegram (Países Bajos - UE). Telegram aplica cláusulas contractuales tipo de la Comisión Europea.</p>

            <h2 id="conservacion">7. Conservación de datos</h2>
            <table style="width: 100%; border-collapse: collapse; margin: 1rem 0;">
                <thead>
                    <tr style="background: var(--color-fondo-alt);">
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid var(--color-borde);">Tipo de dato</th>
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid var(--color-borde);">Conservación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Cuenta de usuario activa</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Mientras la cuenta esté activa</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Cuenta inactiva (sin login)</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">2 años → anonimización</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Reservas pasadas</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">3 años (obligación fiscal)</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Newsletter (suscripción)</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Hasta baja o 2 años inactivo</td>
                    </tr>
                    <tr>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">Logs de seguridad</td>
                        <td style="padding: 0.75rem; border: 1px solid var(--color-borde);">90 días</td>
                    </tr>
                </tbody>
            </table>

            <h2 id="derechos">8. Tus derechos (RGPD)</h2>
            <p>Tienes derecho a:</p>

            <h3>8.1. Acceso (Art. 15 RGPD)</h3>
            <p>Solicitar copia de tus datos personales que tratamos.</p>

            <h3>8.2. Rectificación (Art. 16 RGPD)</h3>
            <p>Corregir datos inexactos o incompletos (también desde tu perfil).</p>

            <h3>8.3. Supresión "derecho al olvido" (Art. 17 RGPD)</h3>
            <p>Solicitar eliminación de tus datos (salvo obligaciones legales). Desde tu perfil: "Eliminar cuenta".</p>

            <h3>8.4. Oposición (Art. 21 RGPD)</h3>
            <p>Oponerte a tratamientos basados en interés legítimo (marketing, análisis).</p>

            <h3>8.5. Limitación (Art. 18 RGPD)</h3>
            <p>Solicitar que congelemos temporalmente el tratamiento mientras verificamos inexactitud o legitimidad.</p>

            <h3>8.6. Portabilidad (Art. 20 RGPD)</h3>
            <p>Recibir tus datos en formato estructurado (JSON) y transferirlos a otro servicio.</p>

            <h3>8.7. Retirada de consentimiento</h3>
            <p>Para newsletter, Telegram, cookies opcionales: puedes retirar consentimiento en cualquier momento sin afectar tratamientos previos.</p>

            <h3>Cómo ejercer tus derechos</h3>
            <ul>
                <li>Email: <a href="mailto:privacidad@komorebi.cafe">privacidad@komorebi.cafe</a> con asunto "Ejercicio de derechos RGPD"</li>
                <li>Plazo de respuesta: <strong>1 mes</strong> (ampliable a 3 si complejidad)</li>
                <li>Identificación requerida: DNI/NIE o email registrado</li>
            </ul>

            <div class="static-content__callout">
                <strong>Reclamación ante autoridad:</strong> Si consideras que vulneramos tus derechos, puedes reclamar ante la <strong>Agencia Española de Protección de Datos (AEPD)</strong>: <a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>
            </div>

            <h2 id="seguridad">9. Seguridad</h2>
            <p>Implementamos medidas técnicas y organizativas apropiadas:</p>

            <h3>Técnicas</h3>
            <ul>
                <li><strong>Cifrado:</strong> HTTPS (TLS 1.3), contraseñas con Argon2id</li>
                <li><strong>Firewall:</strong> Acceso solo por puertos necesarios</li>
                <li><strong>Backups:</strong> Cifrados, automatizados diariamente, conservados 30 días</li>
                <li><strong>Autenticación:</strong> Sesiones con expiración, CSRF protection, rate limiting</li>
                <li><strong>Logs:</strong> Monitorización de accesos sospechosos</li>
            </ul>

            <h3>Organizativas</h3>
            <ul>
                <li>Acceso a datos limitado a personal autorizado</li>
                <li>Procedimientos de respuesta ante brechas de seguridad (notificación en 72h a AEPD si procede)</li>
                <li>Auditorías periódicas de seguridad</li>
            </ul>

            <h2 id="cookies">10. Cookies</h2>
            <p>Consulta nuestra <a href="/legal/cookies">Política de Cookies</a> detallada.</p>
            <p><strong>Resumen:</strong> Solo usamos cookies esenciales (sesión, CSRF). No usamos cookies de terceros (Google Analytics, Facebook Pixel).</p>

            <h2 id="cambios">11. Cambios en la política</h2>
            <p>Podemos actualizar esta política. Cambios sustanciales se notificarán mediante:</p>
            <ul>
                <li>Aviso en la página principal (30 días)</li>
                <li>Email a usuarios registrados</li>
            </ul>
            <p><strong>Versión actual:</strong> 1.0 (1 de febrero de 2026)</p>

            <h2 id="contacto">12. Contacto</h2>
            <p><strong>Delegado de Protección de Datos (DPO):</strong> privacidad@komorebi.cafe</p>
            <p><strong>Dirección postal:</strong> Calle Ficticia 123, 28001 Madrid</p>
            <p><strong>Horario atención:</strong> Lunes a Viernes, 10:00 - 18:00</p>
        </article>
    </div>

    <!-- CTA Final -->
    <div class="static-cta">
        <h3 class="static-cta__title">¿Dudas sobre privacidad?</h3>
        <p class="static-cta__text">Nuestro equipo está disponible para resolver cualquier cuestión sobre tus datos.</p>
        <a href="/contacto" class="btn">Contactar</a>
    </div>
</div>
