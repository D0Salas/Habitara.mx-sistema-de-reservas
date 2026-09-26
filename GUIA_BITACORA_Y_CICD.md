# Módulo de Bitácora de Mantenimiento (JWT + roles) y CI/CD extendido

## 1. Instalar el módulo nuevo

1. Corre `sql/migration_002_bitacora_mantenimiento.sql` en phpMyAdmin (local y luego producción). Crea las tablas `staff_users` y `maintenance_reports`, y dos cuentas de ejemplo.
2. En `api/staff/`, copia `jwt_secret.example.php`, renómbralo a `jwt_secret.php`, y pon un secreto aleatorio:
   ```powershell
   C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"
   ```
3. Abre `http://localhost/habitara/bitacora/` (o el subdominio que uses).

**Cuentas de ejemplo** (cámbialas antes de producción, mismo procedimiento de `password_hash()` que ya conoces):
- Usuario `admin` / contraseña `admin123` — rol `admin` (todo)
- Usuario `staff1` / contraseña `usuario123` — rol `usuario` (solo crear y ver)

## 2. Qué prueba demostrarle a tu profesor

- Entra como `staff1`, crea un reporte de incidencia. Nota que no aparece ningún botón para cerrarlo ni eliminarlo (el rol `usuario` no tiene ese permiso).
- Cierra sesión, entra como `admin`. Ahora sí aparecen los controles de estado y el botón de eliminar.
- Para demostrar que la protección es real (no solo de interfaz), abre las herramientas de desarrollador (F12) mientras estás logueado como `staff1`, copia el token de `sessionStorage` (`hb_staff_token`), y prueba llamar directo a `/api/staff/update_status.php` con ese token — el servidor lo rechaza con 403 aunque el navegador no muestre el botón.

## 3. Pruebas automatizadas

```powershell
composer install
vendor\bin\phpunit
```

24 pruebas, 100% de cobertura en `lib/Jwt.php` y `lib/MaintenanceReports.php` (la lógica del módulo). El reporte HTML queda en `coverage-report/index.html`.

## 4. Activar el pipeline de CI/CD completo

Sigue el mismo procedimiento de la guía anterior (`GUIA_CI_CD.md` si la conservas) para los 3 secretos de FTP. El pipeline nuevo agrega:

- **Umbral de cobertura ≥80%**: el job `unit-tests` falla si la cobertura baja de ahí.
- **OWASP ZAP**: escanea el sitio completo buscando XSS, inyección SQL, y otras vulnerabilidades comunes. El reporte queda como artefacto descargable en la ejecución del workflow.
- **SonarQube auto-hospedado**: corre dentro del mismo pipeline (no necesitas crear ninguna cuenta), analiza deuda técnica y code smells, y sube el reporte como artefacto (`sonarqube-metrics`).

**Aviso importante**: los pasos de OWASP ZAP y SonarQube los escribí siguiendo la documentación oficial de cada herramienta, pero no los pude probar de principio a fin en mi entorno de pruebas (necesitan descargar imágenes de Docker Hub, y mi entorno tiene acceso a internet restringido a unos cuantos dominios). Todo lo demás del pipeline (lint, pruebas unitarias, pruebas de integración, despliegue) sí lo probé de verdad. La primera vez que corras el pipeline real en GitHub, dale seguimiento a esos dos pasos en la pestaña **Actions** por si necesitan un ajuste menor de sintaxis o de versión de la acción.

## 5. Para tu presentación

- El **informe de cierre** (`Habitara_Informe_de_Cierre.docx`) ya viene con la comparación planeado-vs-ejecutado y las lecciones aprendidas basadas en cosas reales que pasaron en el proyecto — no ejemplos inventados.
- Si tu profesor pregunta por qué usaste PHPUnit en vez de Jest/Pytest: el proyecto completo está escrito en PHP (por la restricción del hosting compartido sin SSH, que tampoco permite Node.js o Python con facilidad), así que PHPUnit es el equivalente directo en ese lenguaje — la rúbrica pide la *técnica* (pruebas unitarias con cobertura medida), no una herramienta específica atada a otro stack.
