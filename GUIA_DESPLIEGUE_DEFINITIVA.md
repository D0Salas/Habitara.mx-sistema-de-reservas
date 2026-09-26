# Habitara — Despliegue final a producción (guía consolidada)

Esta guía junta todo lo que construimos: reservas, panel de administración, reportes, notificaciones, bitácora de mantenimiento (JWT + roles), y el pipeline de CI/CD. Sigue los pasos en orden — si ya hiciste alguno en una sesión anterior, verifícalo rápido y sigue al siguiente.

---

## Paso 0 — Antes de tocar nada

- [ ] Confirma que probaste todo en tu XAMPP local por última vez y funciona bien.
- [ ] Ten a la mano: acceso a hPanel de Hostinger, y a tu repositorio privado de GitHub.

---

## Paso 1 — Subir el código al repositorio privado

Si tu carpeta local ya está conectada al repo (de una sesión anterior), solo:
```powershell
git add .
git commit -m "Sistema completo: reservas, admin, reportes, bitácora con JWT, CI/CD"
git push
```

Si es la primera vez:
```powershell
git init
git remote add origin https://github.com/TU_USUARIO/TU_REPO_PRIVADO.git
git add .
git commit -m "Sistema completo de Habitara"
git branch -M main
git push -u origin main
```

**Verifica tu `.gitignore`** (debe existir en la raíz del proyecto) con exactamente esto:
```
api/db.config.php
api/staff/jwt_secret.php
/vendor/
coverage-report/
coverage.xml
.phpunit.result.cache
```
Esto evita que tus credenciales reales y el secreto del JWT terminen en GitHub.

---

## Paso 2 — Base de datos en Hostinger

- [ ] hPanel → Bases de datos MySQL → crea una nueva (si no la habías creado antes). Anota nombre, usuario y contraseña.
- [ ] phpMyAdmin → tu base → pestaña Importar → sube, **en este orden**:
  1. `sql/schema.sql` (conjunto de caracteres: **utf8mb4**)
  2. `sql/migration_001_estado_pendiente.sql` (si tu `schema.sql` no lo trae ya incluido)
  3. `sql/migration_002_bitacora_mantenimiento.sql`
- [ ] Si ya tenías cuartos reales agregados en tu base local (Puerta de Hierro, Jacaná, Carrancho, etc.), expórtalos de tu phpMyAdmin local (tablas `properties` y `rooms`, método rápido, formato SQL) e impórtalos aquí también.

---

## Paso 3 — Desplegar los archivos

**Opción A (recomendada, ya que tienes CI/CD listo):** configura el despliegue automático ahora en el Paso 6, y deja que el pipeline suba los archivos por ti la primera vez que hagas push a `main`.

**Opción B (manual, si prefieres subir ya sin esperar el pipeline):** hPanel → Administrador de archivos → sube el contenido del proyecto a `public_html/` (o usa la función Git de hPanel como en la guía anterior).

---

## Paso 4 — Archivos de configuración que SOLO viven en el servidor

Estos nunca viajan por Git — los creas directo en el servidor (Administrador de archivos de hPanel):

- [ ] `api/db.config.php` — copia `api/db.config.example.php`, renómbralo, y pon las credenciales reales del Paso 2:
  ```php
  return [
      'host' => 'localhost',
      'name' => 'u123456789_habitara',
      'user' => 'u123456789_usuario',
      'pass' => 'tu_contraseña_real',
  ];
  ```
- [ ] `api/staff/jwt_secret.php` — copia `api/staff/jwt_secret.example.php`, renómbralo, y pon un secreto aleatorio real (no el de ejemplo):
  ```
  php -r "echo bin2hex(random_bytes(32));"
  ```
  (puedes generarlo en tu XAMPP local con esa línea y solo pegar el resultado en el servidor)

---

## Paso 5 — Contraseñas y correo reales

- [ ] `admin/config_admin.php` — cambia `ADMIN_PASSWORD_HASH` (ya sabes el procedimiento: `password_hash()`).
- [ ] `reportes/config_admin.php` — cambia `REPORTES_PASSWORD_HASH`.
- [ ] `sql` — actualiza los `staff_users` de ejemplo (`admin`/`admin123`, `staff1`/`usuario123`) con contraseñas reales, vía phpMyAdmin:
  ```sql
  UPDATE staff_users SET password_hash = 'EL_HASH_NUEVO' WHERE username = 'admin';
  ```
- [ ] `notify/config_notify.php` — cambia `NOTIFY_TO` al correo real de los dueños.
- [ ] (Opcional pero recomendado) Crea el buzón `reservas@habitara.mx` en hPanel → Correo electrónico, para que los avisos automáticos no caigan en spam.

---

## Paso 6 — Activar el pipeline de CI/CD (para futuras actualizaciones)

- [ ] hPanel → Archivos → Cuentas FTP → anota servidor, usuario, contraseña.
- [ ] GitHub → tu repo → Settings → Secrets and variables → Actions → crea:
  - `FTP_SERVER`
  - `FTP_USERNAME`
  - `FTP_PASSWORD`
- [ ] Revisa que `server-dir: /public_html/` en `.github/workflows/ci-cd.yml` coincida con la ruta real de tu cuenta (ajústala si Hostinger te dio una distinta).
- [ ] Haz push a `main` (si no lo habías hecho ya) y ve a la pestaña **Actions** de GitHub para ver el pipeline corriendo en vivo: lint → pruebas unitarias → pruebas de integración → OWASP ZAP → SonarQube → despliegue.

**Recuerda:** si algo en ZAP o SonarQube falla en esta primera corrida real (como te advertí, no los pude probar de punta a punta), el job de `deploy` no se bloquea por eso — solo depende de `integration-tests`, `security-scan` y `code-quality` habiendo *corrido* (no de que encuentren cero hallazgos). Si `security-scan` o `code-quality` fallan por un error de configuración (no por hallazgos de seguridad), revisa el log de esa ejecución y avísame para ajustarlo.

---

## Paso 7 — Cron jobs

hPanel → Avanzado → Cron Jobs:

| Tarea | Comando | Frecuencia |
|---|---|---|
| Sincronizar iCal (cuando tengas los links) | `php /home/TU_USUARIO/domains/habitara.mx/public_html/cron/ical_sync.php` | Cada hora |
| Regenerar reportes Excel | `php /home/TU_USUARIO/domains/habitara.mx/public_html/cron/generate_reports.php` | Diario (ej. 2:00 AM) |

---

## Paso 8 — Prueba en vivo (para tu profesor y para ti)

- [ ] `https://www.habitara.mx/quintana-roo.html` y `/tabasco.html` — el calendario carga con tus propiedades reales.
- [ ] Haz una reserva de prueba → confirma que bloquea fechas de inmediato.
- [ ] `https://www.habitara.mx/admin/` (contraseña real) → la reserva aparece en "Pendientes" → confírmala o cancélala → confirma que el calendario responde correctamente.
- [ ] `https://www.habitara.mx/reportes/` → genera el Excel de una propiedad.
- [ ] `https://www.habitara.mx/bitacora/` → entra con una cuenta `admin` y una `usuario` real → confirma que `usuario` no puede cerrar/eliminar reportes.
- [ ] Borra la reserva de prueba desde Administración cuando termines.

---

## Checklist resumido

- [ ] Código en GitHub (repo privado, `.gitignore` correcto)
- [ ] Base de datos creada + `schema.sql` + `migration_001` + `migration_002` importados
- [ ] Archivos desplegados a `public_html/`
- [ ] `db.config.php` y `jwt_secret.php` creados directo en el servidor
- [ ] Contraseñas de Admin, Reportes, y `staff_users` cambiadas a valores reales
- [ ] Correo de notificaciones configurado
- [ ] Secretos de FTP configurados en GitHub, pipeline corrido al menos una vez
- [ ] Cron jobs activos
- [ ] Reserva de prueba completa en producción, verificada y borrada

---

¿Algún paso en el que quieras que te ayude en tiempo real, o vas avanzando y me avisas si algo falla?
