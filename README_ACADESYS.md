# fact_api para AcadeSys

Copia de [angelitosystems/fact_api](https://github.com/angelitosystems/fact_api) (licencia MIT) preparada para
emitir las boletas electrónicas de AcadeSys en el **ambiente beta de SUNAT**.

## Cambios respecto al original

- `Dockerfile`, `docker/` y `render.yaml` para desplegar en Render.
- `php artisan acadesys:preparar`: al arrancar crea el usuario de la API, la empresa de prueba
  (RUC 20161515648, usuario SOL `MODDATOS`), la sucursal y copia el certificado de prueba. Es idempotente.
- La migración `2025_12_15_104136` fallaba en una instalación nueva (`mto_igv_gratuitas` duplicada); ahora
  solo agrega las columnas que faltan.
- `GET /api/v1/boletas/{id}/download-xml` llamaba a un método inexistente; ahora usa `FileService::downloadXml`.
- En producción no se exponen `POST /api/setup/migrate` ni `/api/setup/seed` (no tienen autenticación).

## Variables de entorno

| Variable | Valor |
|---|---|
| `APP_KEY` | cualquier secreto largo (Render lo genera) |
| `APP_URL` | URL pública del servicio |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` | los del servicio MySQL de Aiven |
| `DB_DATABASE` | `facturacion` (base separada de la de AcadeSys) |
| `DB_CA_CERT` | contenido del *CA certificate* de Aiven (texto que empieza con `-----BEGIN CERTIFICATE-----`) |
| `SESSION_DRIVER` / `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `FACT_ADMIN_EMAIL` / `FACT_ADMIN_PASSWORD` | usuario con el que AcadeSys se conecta |

El log de arranque muestra `AcadeSys listo: FACT_API_COMPANY_ID=1 FACT_API_BRANCH_ID=1`.
