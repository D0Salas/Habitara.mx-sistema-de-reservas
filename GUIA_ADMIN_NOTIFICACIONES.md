# Probar el Panel de Administración y las Notificaciones (XAMPP)

Esta guía asume que ya tienes el proyecto corriendo localmente (si no, revisa primero `GUIA_LOCAL_WINDOWS.md`). Aquí solo cubrimos lo nuevo: el panel `/admin/` y los correos automáticos.

---

## 1. Reimporta la base de datos y revisa `db.php`

Como cada zip nuevo trae `sql/schema.sql` actualizado y `api/db.php` con las credenciales de ejemplo (no las tuyas), antes de nada:

1. Ve a `http://localhost/phpmyadmin` → tu base de datos → pestaña **Importar** → sube el `sql/schema.sql` más reciente (conjunto de caracteres: **utf8mb4**).
2. Abre `C:\xampp\htdocs\habitara\api\db.php` y vuelve a poner:
   ```php
   $DB_HOST = 'localhost';
   $DB_NAME = 'habitara_local';   // o el nombre que le pusiste tú
   $DB_USER = 'root';
   $DB_PASS = '';
   ```
3. Confirma que en `quintana-roo.html` y `tabasco.html` la línea diga `apiBase: 'api',` (sin diagonal) — con la versión más reciente que te di, esto ya no debería cambiar nunca más, pero revísalo una vez.

---

## 2. Probar el panel de administración

1. Abre: `http://localhost/habitara/admin/login.php`
2. Contraseña de ejemplo: **`habitaraAdmin2026`**
3. Deberías ver dos tablas: **"Próximas reservas"** y **"Historial"**.

### Cómo probarlo de verdad

1. Ve a `quintana-roo.html` (o `tabasco.html`) y haz una reserva de prueba con fechas futuras.
2. Regresa a `http://localhost/habitara/admin/index.php` (o recarga) — la reserva debe aparecer en "Próximas reservas".
3. Da clic en **"Cancelar"** junto a esa reserva → confirma el cuadro de diálogo.
4. Verifica dos cosas:
   - La reserva ahora aparece en **"Historial"** marcada como **"Cancelada"**.
   - Si regresas al calendario en `quintana-roo.html`, esas mismas fechas ya deben verse **disponibles de nuevo** (puedes confirmarlo también directo en phpMyAdmin: la fila desapareció de `occupied_nights`).

### Cambiar la contraseña del panel

Antes de subir a producción, genera una contraseña nueva. En la terminal de VSCode:
```powershell
C:\xampp\php\php.exe -r "echo password_hash('TU_CONTRASENA_NUEVA', PASSWORD_DEFAULT);"
```
Copia el resultado (empieza con `$2y$...`) y pégalo en `admin/config_admin.php`, reemplazando el valor de `ADMIN_PASSWORD_HASH`.

---

## 3. Probar las notificaciones por correo

Aquí hay un detalle importante que debes saber: **XAMPP no envía correos reales de fábrica**. La función `mail()` de PHP necesita un servidor SMTP configurado para de verdad entregar el correo a una bandeja de entrada — en tu hosting de Hostinger esto ya funciona solo (por eso ahí no necesitas hacer nada extra), pero en tu computadora no hay ningún servidor de correo real detrás.

Esto significa que **al hacer una reserva de prueba en XAMPP, el correo no va a llegar a ningún lado** — pero eso no es un error del código, es normal en un entorno local. Tienes dos formas de confirmar que la parte de código sí funciona:

### Opción A (rápida): confirmar que el intento de envío ocurre, sin ver el correo real

1. Haz una reserva de prueba.
2. Abre el log de errores de Apache: `C:\xampp\apache\logs\error.log` (los más recientes están al final del archivo).
3. Si `mail()` falla (como es de esperarse en XAMPP sin configurar), verás una línea como:
   ```
   [Habitara] No se pudo enviar el correo de notificación a duenos@habitara.mx
   ```
   Esto confirma que el sistema **sí intentó enviarlo** con los datos correctos — solo que no hay servidor de correo real detrás en tu compu. La reserva se guarda bien de todas formas; el aviso nunca detiene el proceso de reservar.

### Opción B (para ver el correo de verdad): usar un "buzón de pruebas" gratuito

Si quieres ver el contenido real del correo como si fuera una bandeja de entrada, la forma más simple es usar un servicio gratuito como **Mailtrap** (https://mailtrap.io), diseñado justo para esto:

1. Crea una cuenta gratuita en Mailtrap.
2. En tu "Inbox" de prueba, copia los datos de SMTP que te dan (host, puerto, usuario, contraseña).
3. Abre `C:\xampp\php\php.ini`, busca la sección `[mail function]` y configúrala así (ajusta con tus datos reales de Mailtrap):
   ```ini
   SMTP = sandbox.smtp.mailtrap.io
   smtp_port = 2525
   sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
   ```
4. Abre `C:\xampp\sendmail\sendmail.ini` y configura ahí el usuario/contraseña de Mailtrap:
   ```ini
   smtp_server=sandbox.smtp.mailtrap.io
   smtp_port=2525
   auth_username=tu_usuario_de_mailtrap
   auth_password=tu_contraseña_de_mailtrap
   ```
5. Reinicia Apache desde el Panel de XAMPP.
6. Haz una reserva de prueba → el correo debe aparecer en tu Inbox de Mailtrap, con el texto completo (propiedad, huésped, fechas, etc.).

Esta opción es completamente opcional — solo para cuando quieras *ver* el correo bonito antes de subir a producción. En Hostinger no vas a necesitar nada de esto.

### Configurar el destinatario real

Antes de subir a producción, edita `notify/config_notify.php`:
```php
const NOTIFY_TO = [
    'correo_real_de_los_duenos@ejemplo.com',
];
```

---

## Solución de problemas

| Problema | Causa probable | Solución |
|---|---|---|
| `/admin/` da error 500 o pantalla en blanco | `db.php` con credenciales viejas | Revisa el paso 1 |
| El panel carga pero no aparecen reservas | Aún no has hecho ninguna reserva de prueba, o la fecha de la reserva ya pasó (se va al Historial, no a Próximas) | Haz una reserva con fecha futura |
| Cancelar no libera las fechas en el calendario | El navegador tiene el calendario en caché | Recarga `quintana-roo.html`/`tabasco.html` con Ctrl+F5 |
| No llega ningún correo | Es normal en XAMPP sin configurar (ver sección 3) | Usa la Opción A o B de arriba |

---

¿Todo probado? Seguimos con lo que decidas: separar `db.php` para que no se sobrescriba, el despliegue final a Hostinger, o agregar los cuartos reales de Tabasco.
