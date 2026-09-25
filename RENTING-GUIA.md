# Renting de R&R Technology: configuración y primera prueba

## Qué quedó preparado

- El módulo aparece como **Renting**, con icono de equipo y pantallas en español.
- Usa los clientes, productos físicos, servicios y contratos de Dolibarr.
- Los cinco almacenes operativos se crean automáticamente al arrancar, incluso tras eliminar los volúmenes.
- Con `RR_RENTING_DEMO_SEED: "1"`, la primera carga añade seis PCs serializadas en RR-VENTA, tres clientes ficticios, un servicio mensual y tres contratos validados. No crea operaciones de renting ni ventas.
- Las unidades se trasladan mediante movimientos de stock nativos y conservan su número de serie.
- Se activaron Terceros, Productos, Servicios, Stocks, Lotes/Series y Contratos.
- Las ventas descuentan stock **al validar la expedición**, no al validar el pedido ni al facturar.
- Los pedidos de hardware solo se pueden validar si hay disponibilidad en RR-VENTA.
- El modo oscuro sigue activo.
- No necesitas borrar volúmenes ni reinstalar. Si no ves el menú nuevo, cierra la sesión y vuelve a entrar.

## 1. Revisar los almacenes

### Empezar con el ejemplo automático

Desde `C:\Projects\rr-technology`, con Docker iniciado:

```powershell
docker compose up -d mariadb
docker compose up -d --no-deps --force-recreate dolibarr
docker compose logs --tail=100 dolibarr
```

Busca `[RR-RENT-SEED] Seeder completado correctamente`. No necesitas borrar volúmenes. En una instalación nueva también basta `docker compose up -d`.

La carga se ejecuta después de habilitar Renting y preparar los almacenes. Compose monta `docker/start` y llama a `40-seed-renting-demo.php`. No se ejecuta desde `docker/init`, porque allí podría adelantarse a la preparación de Renting.

| Dato | Ejemplo creado |
|---|---|
| Producto físico | RR-PC-001 — R&R Forge RTX Gaming PC |
| Series en RR-VENTA | RR-FORGE-001 a RR-FORGE-006 |
| Cliente de E-sports | Arena Tica Esports S.A. [DEMO] |
| Cliente empresarial | PixelForge Studio S.R.L. [DEMO] |
| Cliente individual | Valeria Jiménez [CLIENTE DEMO] |
| Servicio | RR-RENT-PC-MES — Renting mensual de PC Gaming Pro |
| Contratos validados | RR-DEMO-ESP-001, RR-DEMO-CORP-001 y RR-DEMO-B2C-001 |

Los importes son de demostración y usan la moneda configurada en Dolibarr. Los contratos quedan validados con una línea de servicio; no se activan servicios ni se generan facturas, cobros, pedidos, reservas o entregas. No se añaden estos clientes ni el servicio a categorías del e-commerce.

Puedes empezar directamente en **Renting → Equipos**: incorpora una serie del producto RR-PC-001. Después, en **Renting → Rentings**, selecciona uno de los clientes, su contrato y el servicio RR-RENT-PC-MES. Los pasos siguientes sirven como referencia para crear tus propios datos.

Al recrear el contenedor se conserva una marca de carga en la base de datos: no duplica clientes, contratos ni unidades, y no repone equipos vendidos o trasladados. Si borras los volúmenes, la nueva base vuelve a recibir el ejemplo. Para instalaciones sin datos demo, cambia `RR_RENTING_DEMO_SEED` a `"0"`; los almacenes sí se preparan igualmente. Desactivar la opción no elimina datos ya creados.

Si RR-PC-001 ya tiene un historial de stock sin números de serie, el seeder se detiene con un mensaje para evitar convertirlo automáticamente. En ese caso, regulariza el producto o desactiva la carga demo para continuar.

En **Renting → Configuración** encontrarás:

| Almacén | Uso |
|---|---|
| RR-VENTA | Unidades destinadas a venta |
| RR-RENTING | Equipos de renting que están disponibles físicamente |
| RR-CLIENTES | Equipos propios entregados a clientes |
| RR-REVISION | Equipos devueltos pendientes de revisión |
| RR-REPARACION | Equipos en mantenimiento |

Son separaciones operativas: no requieren cinco edificios. Los equipos en RR-CLIENTES siguen siendo propiedad de R&R.

Los cuatro almacenes de renting están protegidos contra movimientos manuales y expediciones de venta. Sus movimientos se hacen desde Renting. Las recepciones de proveedores o ajustes iniciales entran en RR-VENTA.

No cierres ni renombres estos almacenes mientras haya operaciones pendientes. Las relaciones internas usan sus identificadores.

## 2. Crear un cliente

En **Terceros → Nuevo tercero**:

1. Registra una empresa o persona.
2. Márcala como cliente y mantenla activa.
3. Completa sus datos de contacto.

No necesitas crear estudiantes ni clases. Un mismo cliente puede comprar hardware y contratar renting.

## 3. Crear el producto físico

En **Productos/Servicios → Nuevo producto**:

1. Usa una referencia, por ejemplo `LAPTOP-RTX-01`.
2. Indica el modelo y las características.
3. Configura la gestión de stock por **número de serie único**, no por un lote compartido.
4. Mantén el producto disponible para venta si el mismo modelo se vende en tu catálogo.

El producto representa el modelo. No crees tres productos distintos si tienes tres laptops idénticas.

Para esta primera versión, cada unidad alquilable debe tener un número de serie. En periféricos sin serie del fabricante, puedes usar un código interno único y registrarlo como serie.

## 4. Registrar las unidades en venta

Desde la ficha del producto, usa la pestaña de stock o una recepción de proveedor:

1. Selecciona **RR-VENTA**.
2. Registra una unidad con serie `RR-LAP-001`.
3. Registra otra con `RR-LAP-002`.
4. Registra una tercera con `RR-LAP-003`.

Comprueba que el stock total sea 3 y que las tres unidades estén en RR-VENTA.

Si ya tienes existencias en otro almacén ordinario, trasládalas a RR-VENTA antes de incorporarlas al renting. No dupliques su recepción.

Si un producto ya tiene stock sin serializar, no inventes otra entrada encima: regulariza su trazabilidad antes de utilizarlo aquí.

## 5. Crear el servicio que se factura

En **Productos/Servicios → Nuevo servicio**:

1. Referencia de ejemplo: `RENT-LAPTOP-MES`.
2. Nombre: «Renting mensual de laptop de alto rendimiento».
3. Define el precio y los impuestos correspondientes al ejercicio.
4. Déjalo disponible para venta.

Este servicio representa lo que cobras. La laptop física se controla por separado.

## 6. Crear y validar el contrato

En **Contratos → Nuevo contrato**:

1. Selecciona el cliente.
2. Indica los responsables comerciales si Dolibarr los solicita.
3. Añade el servicio de renting creado en el paso anterior.
4. Define las condiciones y fechas del acuerdo.
5. Valida el contrato.

Renting exige que el contrato pertenezca al cliente seleccionado y contenga el servicio elegido. La reserva registra sus propias fechas operativas; en esta versión, el asesor debe comprobar que sean coherentes con las condiciones del contrato.

La validación del contrato y la reserva no generan un cobro automático. Gestiona la activación del servicio y su facturación desde las funciones nativas de Dolibarr, según tu operación.

## 7. Pasar equipos de venta a renting

En **Renting → Equipos → Incorporar una unidad a renting**:

1. Selecciona `LAPTOP-RTX-01`.
2. Introduce `RR-LAP-001`.
3. Pulsa **Trasladar de venta a renting**.
4. Repite con `RR-LAP-002`.

Resultado esperado:

- RR-VENTA: 1 unidad.
- RR-RENTING: 2 unidades.
- Total propiedad de la empresa: 3 unidades.

La ficha de Renting describe esas mismas unidades; no genera un inventario adicional.

Si la cantidad disponible para venta ya está comprometida en pedidos validados, el traslado se bloquea.

## 8. Crear una reserva

En **Renting → Rentings**:

1. Elige cliente, contrato validado y servicio del contrato.
2. Define inicio y fin. Para probar la entrega, usa hoy como inicio.
3. Selecciona los equipos.
4. Guarda la reserva.

El módulo comprueba solapamientos para cada equipo. Las fechas de inicio y fin son inclusivas: si un renting termina el día 15, el siguiente podrá empezar el 16.

Un equipo físicamente disponible puede estar reservado para fechas futuras. El estado «Disponible» del resumen no reemplaza la comprobación por fechas.

Se permite reservar un equipo actualmente entregado si su devolución está prevista antes del nuevo periodo. No se permite si tiene una devolución vencida, y nunca se permite entregarlo de nuevo hasta que haya vuelto y pasado la revisión.

Puedes cancelar una reserva antes de entregarla. El historial se conserva.

## 9. Registrar la entrega

Abre la reserva y pulsa **Registrar entrega de todos los equipos**.

El módulo:

- Comprueba que la reserva está dentro de su periodo.
- Verifica que todos los equipos estén disponibles.
- Traslada cada serie de RR-RENTING a RR-CLIENTES.
- Registra fecha y condición de salida.
- Cambia la operación a «En curso».

La entrega es conjunta en esta versión. Si falla una unidad, se revierten los movimientos de toda la operación.

No generes una expedición de venta de las laptops para documentar este renting: el movimiento ya lo realiza el módulo.

## 10. Facturar el servicio

Usa el circuito de facturación de Dolibarr para el cliente y servicio del contrato.

- Cobra el servicio, por ejemplo una mensualidad.
- No añadas la laptop como producto vendido.
- Una segunda mensualidad no debe mover otra laptop.
- Compose ejecuta cada minuto las plantillas recurrentes vencidas configuradas en estado **Borrador**. No realiza cobros ni envía correos. Las plantillas configuradas para validar o enviar automáticamente se omiten.

### Configuración inicial de cada renting

1. Abre el renting y pulsa **Preparar factura del contrato**. Comprueba el cliente y selecciona únicamente el servicio mensual; ajusta cantidad y tarifa a lo acordado. Incluye la referencia RT del renting en la descripción para reconocerla después.
2. Guarda la primera factura como borrador y usa la opción de convertirla/crear una factura predefinida o recurrente. La plantilla copia sus líneas; no sustituye la factura inicial.
3. Configura frecuencia **1 mes**, estado de facturas generadas **Borrador** y próxima ejecución en la fecha de la **segunda** mensualidad si conservas la primera factura. Para seis mensualidades totales, la plantilla debe generar las cinco restantes. No dejes el máximo en cero: significa sin límite.
4. Revisa y valida la primera factura cuando corresponda. Las siguientes aparecen automáticamente en **Facturación → Facturas a clientes**; las plantillas están en **Facturas predefinidas/plantillas**.
5. Si termina antes, suspende la plantilla. La devolución del equipo o el cierre del contrato no la detiene automáticamente. No crees una segunda plantilla para el mismo renting ni uses generación manual simultánea con el procesador.

El procesador utiliza el calendario y contador nativos de Dolibarr. Si el contenedor estuvo apagado, recupera periodos pendientes, uno por plantilla en cada revisión, hasta ponerse al día o alcanzar el máximo. No necesita que el navegador esté abierto, pero Docker debe estar funcionando.

La configuración del procesador sobrevive a recrear Dolibarr. Las plantillas y facturas se conservan en MariaDB; `docker compose down -v` las elimina, igual que los demás datos. El seeder no crea plantillas ni facturas de clientes. Para desactivar el procesador, configura `RR_RECURRING_BILLING: "0"` en Compose y recrea Dolibarr.

## 11. Recibir una devolución

Dentro del renting, registra la condición de cada equipo y pulsa **Recibir y enviar a revisión**.

- La unidad pasa de RR-CLIENTES a RR-REVISION.
- Si faltan equipos, la operación queda en «Devolución parcial».
- Cuando todos vuelven, queda «Finalizado».
- El equipo no vuelve automáticamente a estar disponible.

## 12. Revisar y liberar el equipo

En la ficha del equipo, selecciona el resultado y escribe una observación:

- **Disponible para renting**: vuelve a RR-RENTING.
- **Reparación**: pasa a RR-REPARACION.
- **Trasladar a venta**: pasa a RR-VENTA, únicamente si no tiene compromisos pendientes.

Un equipo dañado no puede marcarse disponible ni enviarse a venta. Después de la reparación, registra otra revisión con su condición actual.

Si lo pones en venta después de usarlo en renting, describe su condición de usado en la oferta correspondiente. Esta versión no crea automáticamente un anuncio o SKU de segunda mano.

## 13. Probar la separación con una venta

Con una unidad en RR-VENTA y dos en renting:

1. Crea un pedido de venta de dos unidades del producto.
2. Al validarlo debe aparecer «Stock de venta insuficiente».
3. Crea un pedido de una unidad: sí debe permitir validarlo.
4. La expedición de venta debe tomar la serie de RR-VENTA.
5. Los almacenes de renting no permiten salidas de venta.

Las cantidades de pedidos abiertos, incluidos los parcialmente enviados, se reservan de manera conservadora por su cantidad completa. Completa y cierra los pedidos de venta despachados para liberar esa reserva operativa.

## 14. Configurar permisos de otros usuarios

En **Usuarios y grupos → Usuario → Permisos → Renting**:

- Consulta: ver equipos y operaciones.
- Incorporación y reservas: registrar equipos y gestionar reservas.
- Entrega, recepción y revisión: ejecutar movimientos de renting.
- Configuración: preparar los almacenes.

Asigna también los permisos nativos necesarios para clientes, productos, contratos y ventas. Los usuarios externos no pueden acceder a este módulo.

## Alcance de esta versión

Incluye equipos y componentes alquilados **individualmente**, clientes, contrato y servicio asociados, reservas por fechas, entrega conjunta, devolución parcial, revisión, reparación básica, transferencia a venta, historial y separación de existencias.

Quedan fuera: composición interna de PCs o kits, mantenimiento con órdenes de trabajo, depósitos, penalizaciones, renovaciones automáticas, cobros automáticos, sincronización de plantillas con devoluciones y documentos PDF propios de renting.

**E-commerce:** la protección de pedidos actúa cuando la tienda utiliza la validación estándar de pedidos de Dolibarr. La presentación de disponibilidad del carrito y del catálogo de Marketplace todavía necesita adaptarse a RR-VENTA y probarse antes de aceptar ventas reales. Una integración que escriba directamente en la base o omita los eventos de Dolibarr no está cubierta.

## Operación y pruebas técnicas

Para aplicar los archivos en otro entorno conservando datos:

```powershell
docker compose up -d mariadb
docker compose up -d --no-deps --force-recreate dolibarr
docker compose logs --tail=100 dolibarr
```

El arranque crea las tablas nuevas de forma aditiva, habilita dependencias y actualiza el registro del módulo una vez por versión. Las tablas escolares originales no se borran ni se reinterpretan como clientes. Su interfaz se retira; los archivos originales se conservan detrás de redirecciones.

Prueba de integración (fixtures temporales, revertidos al finalizar):

```powershell
docker compose exec -T dolibarr php /var/www/html/custom/verleih/tests/integration.php
```

Se verificaron 37 comprobaciones con Dolibarr 24.0.0 y MariaDB 10.11, incluidas reservas solapadas, permisos, aislamiento entre entidades, reversión de entregas incompletas, conservación de stock y protección de ventas. Las cuatro pantallas se comprobaron con una sesión autenticada y se rechazó una solicitud con token inválido. La inspección visual mediante navegador automatizado no pudo ejecutarse por un fallo del entorno.

El respaldo previo a la migración está dentro del volumen de MariaDB, en `/var/lib/mysql/rr-backups/`. No sobrevive a borrar ese volumen.
