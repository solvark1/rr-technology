# Datos simulados para las visualizaciones

## Qué se carga

El seeder `docker/start/47-seed-analytics.php` crea una historia ficticia reconocible por el prefijo **RRA1** y notas **SIMULACION ANALITICA**. Se ejecuta después de los seeders básicos y antes del proceso de facturación automática.

- 12 clientes adicionales con nombres ficticios y correos `example.invalid`.
- 35 contratos con sus rentings: 20 finalizados, 5 activos, 5 reservados y 5 cancelados.
- Los cinco productos físicos existentes: PC, monitor, teclado, mouse y audio. Se incorporan series nuevas RRA1; no se reutilizan series de operaciones manuales.
- Cinco servicios mensuales identificados como simulados, uno por producto físico. No se añaden nuevos modelos de hardware.
- 50 facturas de renting y 20 facturas de venta en una carga realizada después del día 5 del mes; al comienzo del mes puede haber menos mensualidades alcanzadas. No se generan facturas de fechas futuras.
- Pagos completos, parciales y facturas pendientes. En la carga verificada: 51 pagos. La cantidad puede variar si la fecha prevista del pago todavía no llegó.
- 10 incidencias: cinco históricas resueltas y cinco del periodo activo, con reportes abiertos, entregas pendientes y sustituciones.
- Stock y eventos históricos con unidades disponibles, entregadas, en revisión, en reparación y retiradas a venta.
- Una cuenta bancaria separada **RRA1-BANK** para los cobros simulados. No existe conexión a un banco real.

La historia comprende aproximadamente los seis meses anteriores a la primera ejecución. Las fechas quedan fijas al guardar el marcador de carga. No se regeneran ni se desplazan al reiniciar el contenedor.

Las ventas son **facturas estándar de productos con salida física de RR-VENTA y pagos**. No se fabrican pedidos ni expediciones comerciales adicionales. No usar estos datos para demostrar un flujo de carrito, envíos o conversión de pedidos.

## Escenarios para la demo

| Visualización | Datos que encontrará |
|---|---|
| Capacidad de flota | Unidades de los cinco productos en distintos estados. |
| Facturado, cobrado y pendiente | Facturas de varios meses con pagos completos, parciales y sin pago. |
| Incidencias pendientes | Reportes abiertos y equipos recibidos que esperan sustitución. |
| Utilización histórica | Fechas de entrega y retorno de unidades serializadas. |
| Reportes por producto | Incidencias distribuidas entre los cinco modelos. |
| Tiempo de atención | Reportes y entregas de resolución en fechas diferentes. |
| Concentración por cliente | Clientes con cantidades y tarifas distintas. |
| Próximas devoluciones | Cinco rentings activos y fechas de finalización futuras. |
| Inventario por canal | Existencias en venta, flota, clientes, revisión y reparación. |
| Cobros por fecha | Pagos distribuidos por varios meses en la cuenta simulada. |

Consultar `DATOS-ANALITICA-RENTING.md` para las relaciones y reglas de agregación. Filtrar `booking.ref LIKE 'RRA1-%'` para rentings simulados y `facture.ref_ext LIKE 'RRA1-%'` para sus facturas y ventas. Los borradores usan referencia provisional: por eso se filtra por `ref_ext`, no por `ref`. Los clientes llevan `societe.ref_ext = RRA1-CLIENT-...`; las series empiezan por RRA1.

## Arranque y repetición

Compose habilita la carga por defecto para este proyecto académico mediante `RR_ANALYTICS_SEED`. Para desactivarla antes de levantar una instalación, establecer en `.env`:

```dotenv
RR_ANALYTICS_SEED=0
```

Desactivar no borra registros existentes. Con valor 1, la carga ocurre una vez por entidad y versión. Repetir el arranque no duplica clientes, stock, contratos, facturas ni pagos. No quitar el marcador `RR_ANALYTICS_SEED_VERSION` para intentar volver a cargar: las referencias de la simulación ya existen.

Para aplicar cambios de Compose conservando datos:

```powershell
docker compose up -d --no-deps --force-recreate dolibarr
```

No se necesita borrar volúmenes. Si se crea una instalación completamente nueva con los seeders activos, recibe tanto los datos básicos como esta historia.

## Precauciones y diseño

- Datos exclusivamente ficticios: no sirven para afirmar tendencias reales, costos, rentabilidad ni calidad real del servicio.
- Las facturas y los pagos sí son documentos operativos visibles dentro de esta instalación demo. No habilitar este seeder en un ERP de producción.
- Se usa una transacción y un bloqueo para impedir cargas simultáneas. Un fallo revierte la carga. Se comprueba stock por serie, fechas de devolución, vínculos de facturación, importes de pagos y cantidad de facturas de cada plantilla antes de confirmar.
- Clientes, contratos, facturas, pagos y movimientos se crean con las APIs nativas. El historial del módulo se importa desde este script CLI con fechas pasadas; no se debilita la validación del formulario que impide crear reservas operativas retroactivas.
- Los productos físicos se habilitan para serie única. Si alguno ya tiene existencias sin serie única, el seeder se detiene para evitar reinterpretarlas automáticamente.
- Los calendarios de los rentings activos siguen funcionando: podrán generar futuras mensualidades en borrador. Los finalizados y cancelados quedan suspendidos; las reservas futuras esperan su entrega.
- Las cantidades de generaciones históricas incluyen borradores. No sumar borradores como facturación definitiva en las visualizaciones.
- Si se abandona el proyecto por meses, la historia permanecerá intacta y algunos rentings activos podrán quedar vencidos; no se devuelven físicamente por el paso del tiempo.
- No se crean comentarios de redes sociales ni datos MongoDB en esta carga.

## Comprobación técnica

Antes de guardar se ensayó la carga con `--dry-run`, que ejecuta y revierte la transacción. El ensayo no usa ni modifica las reservas operativas actuales. Las secuencias autoincrementales pueden dejar huecos, algo normal en MariaDB.

```powershell
docker compose exec -T -e RR_ANALYTICS_SEED=1 dolibarr php /var/www/scripts/rr-start/47-seed-analytics.php --dry-run
```

Una vez cargada la versión, también el ensayo la reconoce y sale sin generar otra copia. La comprobación de idempotencia consiste en ejecutar nuevamente el seeder y verificar que anuncia «already loaded; unchanged».