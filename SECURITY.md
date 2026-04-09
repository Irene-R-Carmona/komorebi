# Security Policy — Komorebi Café

---

## Versiones soportadas

| Versión | Soporte de seguridad         |
|---------|------------------------------|
| v1.0.x  | :white_check_mark: Soportada |
| < v1.0  | :x: No aplicable             |

---

## Reporte de vulnerabilidades

**No abras un issue público de GitHub para reportar vulnerabilidades de seguridad.**

Reporta vulnerabilidades de forma privada usando la funcionalidad **"Report a vulnerability"** disponible en la pestaña
**Security** del repositorio en GitHub. Esta vía cifra el reporte y lo mantiene privado hasta que el fix esté publicado.

### Qué incluir en el reporte

- **Descripción clara** de la vulnerabilidad y el componente afectado.
- **Pasos para reproducirla** (versión, entorno, configuración mínima necesaria).
- **Impacto potencial** estimado (qué datos o funcionalidad quedan expuestos).
- **Sugerencia de fix** (opcional, pero bienvenida).

### Tiempos de respuesta

| Evento                                 | Plazo objetivo |
|----------------------------------------|----------------|
| Acuse de recibo del reporte            | 72 horas       |
| Evaluación inicial y clasificación     | 7 días         |
| Fix para vulnerabilidades críticas     | 30 días        |
| Fix para vulnerabilidades medias/bajas | 90 días        |

Tras aplicar el fix se publicará una entrada en `CHANGELOG.md` bajo la sección `Security` y, si procede, un advisory en
GitHub.

---

## Alcance

### En alcance

- Bypass de autenticación o autorización
- Inyección SQL
- Cross-Site Scripting (XSS)
- Bypass de protección CSRF
- Escalada de privilegios entre roles
- Ejecución remota de código (RCE)
- Exposición de secretos o credenciales

### Fuera de alcance

- Vulnerabilidades en paquetes de terceros (repórtalas directamente al mantenedor del paquete).
- Ataques teóricos sin prueba de concepto funcional.
- Ingeniería social o phishing dirigidos a usuarios o desarrolladores.
- Resultados de escáneres automáticos sin verificación manual del impacto real.

---

## Agradecimientos

Reconocemos públicamente los reportes responsables en este fichero tras aplicar el fix correspondiente. Si deseas que tu
nombre o alias figure aquí, indícalo en tu reporte.
