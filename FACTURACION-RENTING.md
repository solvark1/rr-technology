# Facturación vinculada al renting

## Flujo actual

1. Preparar el contrato con una línea de servicio: cantidad de unidades, tarifa **por unidad y por mes**, IVA, descuento y fechas previstas.
2. Crear el renting vinculado a esa línea y seleccionar los equipos requeridos.
3. En la ficha, abrir **Facturación del renting**. Revisar los importes y el número de mensualidades, elegir condiciones de pago y confirmar que no hay otra plantilla o factura para esos periodos.
4. Pulsar **Crear programación mensual desde el contrato**. No hace falta crear una factura inicial ni convertirla manualmente en plantilla.
5. Si todavía no se entregaron los equipos, la programación espera. La entrega activa la generación. Si ya se entregaron, queda activa al crearla.
6. El job genera borradores cuando llegan sus fechas. Las facturas aparecen en la misma ficha del renting y en el listado nativo de facturas. Revisar y validar; registrar el pago por separado al recibirlo.

Se copian cliente, servicio, cantidad, precio unitario, IVA y descuento del contrato. La modalidad de pago se toma del cliente y las condiciones de pago se eligen al preparar la programación. No se modifican facturas o plantillas existentes.

## Calendario y límites

- Modalidad inicial: mensual, anticipada, por unidad. Confirmarla antes de activar; el nombre del servicio no garantiza que su tarifa sea mensual.
- La primera fecha es el inicio del contrato; cada siguiente fecha usa el calendario mensual nativo de Dolibarr, incluido su tratamiento de fines de mes.
- La fecha final es el límite exclusivo para iniciar otra mensualidad. Por ejemplo, del 25 de septiembre al 25 de diciembre son tres mensualidades.
- Un periodo incompleto factura una mensualidad completa. No hay prorrateo automático. El fin debe ser posterior al inicio.
- Una entrega o configuración tardía recupera mensualidades vencidas desde el inicio contractual, una por ejecución. Revisar esta consecuencia antes de activar.
- Las plantillas gestionadas tienen un límite finito y generan solamente borradores; no validan, envían correos ni cobran.
- Se impide crear dos programaciones para el mismo renting. Las plantillas manuales anteriores no pueden identificarse con certeza como equivalentes: revisarlas antes de confirmar para evitar duplicación externa.

## Devoluciones, pausa y ajuste

Al devolver cualquier unidad se pausa la programación vinculada dentro de la misma operación. Si todas regresaron, no se permite reanudarla. Cancelar una reserva también pausa su programación.

En una devolución parcial, el asesor puede **Revisar y reanudar facturación**:

- Mantener la cantidad actualmente programada, si así corresponde al acuerdo.
- Ajustar la cantidad a los equipos que siguen entregados, conservando tarifa por unidad, IVA y descuento del contrato.
- Confirmar la próxima fecha y escribir el motivo/acuerdo con el cliente.

La fecha no puede retroceder a un periodo ya facturado, ser anterior a hoy ni alcanzar el fin contractual. Elegir una fecha posterior omite periodos pendientes anteriores: el asesor debe resolverlos expresamente antes de confirmar. El máximo restante se reduce si hace falta para respetar el fin contractual; no aumenta para recuperar periodos omitidos.

Los ajustes solo afectan mensualidades futuras. Las facturas ya generadas, incluso borradores, se conservan para revisión. No se calculan abonos, devoluciones de dinero, penalizaciones ni cargos por reparación. La cantidad original del contrato se conserva como historial del compromiso; el ajuste facturable queda documentado en el renting.

## Protección e historial

La generación y las devoluciones comparten el bloqueo del renting para impedir que una nueva mensualidad se genere simultáneamente con una devolución ya registrada. La generación manual y las modificaciones estándar protegidas de estas plantillas se rechazan: deben utilizarse las acciones de Renting. El worker comprueba además las condiciones económicas antes de generar.

El historial de eventos del módulo registra creación, activación, pausa y ajuste con usuario, fecha y motivo. Las facturas se relacionan mediante su plantilla nativa y se muestran en la ficha.

Los rentings antiguos sin vínculo de línea contractual no se convierten automáticamente. Las plantillas creadas anteriormente fuera de este flujo siguen siendo independientes y deben suspenderse o ajustarse manualmente.

## Alcance técnico

La migración del arranque agrega una tabla de vínculos, sin borrar los datos existentes. La implementación reside en el módulo personalizado y utiliza las clases nativas de facturación. El job de Compose sigue ejecutándose cada minuto como `www-data`.

Para la creación nativa de la plantilla se utiliza un borrador técnico que se elimina dentro de la misma transacción. No queda una primera factura adicional para pagar; la primera factura real la genera el calendario.

Pruebas: `tests/integration.php` cubre el flujo integrado y `tests/recurring_billing.php` la recurrencia independiente. Las pruebas revierten sus datos temporales.
