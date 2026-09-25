# Reservas basadas en el contrato

Cada nueva reserva se vincula a una línea de servicio de un contrato validado del cliente. De ella toma la cantidad entera positiva y las fechas previstas de inicio y fin. Una unidad del servicio representa una unidad física; no se admite interpretar automáticamente paquetes ni cantidades fraccionarias.

## Uso

1. Preparar en el contrato el servicio, su cantidad y sus fechas previstas. El periodo de reserva debe comenzar hoy o después.
2. En Renting, elegir cliente, contrato y línea de servicio.
3. Elegir el producto físico que cumple esa línea: PC, monitor, tarjeta gráfica u otro producto gestionado por serie única. El servicio es lo que se factura; el producto identifica el tipo de unidad que se entrega. Esta asociación se elige explícitamente, no se deduce del nombre del servicio.
4. Seleccionar exactamente la cantidad contratada de series disponibles para el periodo. La pantalla indica el total requerido, seleccionado y disponible; el servidor vuelve a validar todas las unidades.
5. Crear la reserva y registrar la entrega dentro del periodo. Se entregan todas las unidades juntas o ninguna, si alguna ya no está disponible.

Para distintos productos o periodos, usar líneas contractuales y reservas separadas. Por ejemplo: una línea para dos PCs y otra para tres monitores. No existe todavía una confirmación conjunta de todas las líneas de un contrato.

## Protecciones

- No se crea una reserva incompleta ni con más unidades de las contratadas.
- Se mantienen los controles de reservas solapadas y devoluciones vencidas.
- Cada línea puede tener un renting no cancelado; una reserva cancelada permite reintentar. Un renting finalizado conserva el vínculo: para otro periodo se utiliza una nueva línea.
- La edición o eliminación de una línea comprometida se rechaza mientras exista reserva, entrega o devolución parcial. Por ahora el bloqueo abarca toda la edición de esa línea, incluidas fechas y tarifas.
- También se bloquean los eventos estándar de modificación, eliminación y reapertura del contrato con rentings abiertos. La validación de entrega detecta diferencias de cantidad, servicio o fechas aunque se hayan introducido fuera de los controles normales.
- Para corregir una reserva sin entrega, cancelarla, modificar el contrato y crear otra reserva. Con unidades entregadas se requiere completar su devolución antes de cambiar el compromiso. No hay aún un flujo de ampliaciones o adendas durante el alquiler.

Los contratos nativos siguen representando el acuerdo comercial: validarlos no entrega equipos ni garantiza inventario. El punto que exige cobertura completa es crear la reserva y, nuevamente, entregar ese renting. No se cambió el núcleo de Dolibarr ni se añadieron bloqueos globales a todos los contratos de otros servicios.

## Datos anteriores y facturación

Los rentings anteriores mantienen fechas, equipos e historial; no se les asigna una línea por suposición. Si hay uno abierto para el mismo contrato y servicio, se impide crear una reserva nueva para ese compromiso hasta resolverlo. Sus contratos también quedan protegidos conservadoramente mientras estén abiertos.

La facturación recurrente sigue usando sus plantillas nativas. Este cambio no crea ni actualiza plantillas, pagos o facturas existentes. Tampoco suspende la facturación al devolver equipos. La fuente contractual de cantidad y fechas queda conectada a la operación; su sincronización con las plantillas continúa pendiente.

La tabla de vínculos se crea en el arranque por la migración aditiva del módulo. Las validaciones del contrato usan triggers del módulo personalizado y requieren las rutas estándar de Dolibarr; escrituras directas en base de datos no están cubiertas por esos eventos.
