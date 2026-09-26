# Recepciones, sustituciones y revisión técnica

## Dos motivos de recepción

La recepción no determina si el equipo está bueno, desgastado o dañado. Ambos caminos lo envían a **RR-REVISION**, con condición **Pendiente de diagnóstico**. El técnico registra el resultado desde **Renting → Revisión y reparación**.

| Acción | Servicio y facturación | Equipo |
| --- | --- | --- |
| Devolución definitiva | Suspende las mensualidades vinculadas. Cierra el renting cuando no quedan unidades ni entregas pendientes. Si la devolución es parcial, se revisa y reanuda la facturación de las unidades que continúan. | Pasa a revisión. No se espera sustituto. |
| Recepción por incidencia | Mantiene el renting y la programación de facturación, incluso mientras se espera una nueva entrega. Una pausa previa no se elimina. | Pasa a revisión. Queda una entrega pendiente para el cliente. |

El cierre del renting no cancela automáticamente el contrato nativo completo ni sus otros servicios. No elimina facturas ya emitidas, pagos ni genera prorrateos o reembolsos.

## Recepción por incidencia y entrega posterior

1. Abre **Renting → Incidencias**. Selecciona el cliente con renting activo, busca sus rentings y abre el correspondiente. En **Registrar un reporte**, busca el equipo por serie o producto y describe la falla. También puedes entrar desde el enlace **Reportar incidencia** de la tabla de equipos del renting.
2. Confirma la recepción física: pasa a revisión y conserva la facturación.
3. En **Incidencias y sustituciones** aparece **Recibido · entrega pendiente**.
4. Elige **Entregar sustituto o equipo reparado**. La lista ofrece series aptas del mismo producto, disponibles durante el resto del periodo.
5. Confirma la entrega física. Se agrega una nueva entrega, conservando el historial de la anterior.

Puedes entregar otro equipo mientras se revisa el original o esperar a que el técnico repare el original y lo reintegre a la flota. **La misma serie puede volver al mismo renting**. No se puede entregar una unidad que sigue en revisión o reparación, ni entregar dos veces para la misma incidencia.

Sin stock disponible, la entrega queda pendiente; la facturación continúa conforme a lo acordado. También se permite recibir y sustituir en el mismo momento. Un fallo de stock revierte la operación, sin dejar movimientos parciales.

Si el cliente cambia de opinión después de la recepción por incidencia, usa **El cliente ya no desea continuar: devolución definitiva** dentro de esa incidencia para finalizar la entrega pendiente y detener la facturación vinculada.

## Trabajo técnico y salida de la flota

En **Revisión y reparación**, registra diagnóstico o trabajo, condición y destino:

- **Reparación:** RR-REPARACION.
- **Disponible para renting:** RR-RENTING, apto para otra entrega.
- **Trasladar a venta:** RR-VENTA, solo sin reservas pendientes.

Un equipo todavía dañado no puede volver a disponibilidad ni a venta. No es necesario que sea nuevo. La salida a venta conserva el historial y permite una incorporación posterior si conserva stock y serie válidos.

Las reservas futuras se muestran como advertencia; enviarlo a reparación no las reprograma automáticamente. El permiso de Renting **Entrega, recepción y revisión** habilita estas operaciones.

## Categorías de flota

En **Equipos**, selecciona una categoría nativa y pulsa **Filtrar flota**. Hay opciones para todas o sin categoría. El filtro utiliza asignaciones directas, sin incluir automáticamente subcategorías. Muestra hasta 500 registros e incluye equipos trasladados a venta como historial.

## Límites y actualización

Los sustitutos deben ser del mismo producto; no se incluyen cambios de modelo o tarifa, costos de taller ni cargos por daños. Las devoluciones registradas anteriormente como definitivas no se reclasifican ni reabren automáticamente: debe revisarse cada caso antes de restaurar su servicio y facturación.

Los cambios se aplican también al recrear Dolibarr mediante su script de arranque. La migración conserva los registros y permite varias entregas históricas de una misma serie.

Verificación: 99 comprobaciones de integración con datos temporales revertidos, incluyendo reparación y reentrega de la misma serie, conservación de facturación, prevención de entregas duplicadas y cierre de incidencias pendientes. No se ha completado una inspección visual automatizada.

## Demo académica de Arena Tica

El seeder versión 2 crea el contrato validado **RR-DEMO-ESP-005**, con cinco unidades del servicio mensual RR-RENT-PC-MES durante seis meses desde su creación. Conserva los contratos existentes. Incluye ocho series RR-FORGE-001 a RR-FORGE-008; las existentes conservan su ubicación y las nuevas se reciben en RR-VENTA.

Incorpora al menos cinco unidades disponibles a la flota antes de crear el renting del contrato. Incorpora otra para demostrar una sustitución. El seeder no entrega equipos ni crea un renting o facturas automáticamente. Una segunda ejecución no duplica datos. Al recrear Dolibarr se ejecuta desde Compose.
