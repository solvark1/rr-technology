# Procesos de negocio: antes y después de la personalización

**Proyecto:** R&R Technology — Informática Aplicada a los Negocios  
**Fecha de revisión:** 25 de septiembre de 2026  
**Base técnica:** Dolibarr 24.0.0 y módulo personalizado Renting, adaptado de Verleih.

## Propósito y criterio de comparación

La empresa combina venta de hardware y alquiler de equipos para empresas de eSports, otras organizaciones y personas individuales. Este documento propone tres procesos diferenciados para explicar la adaptación del ERP y preparar diagramas de antes/después en Visio o Bizagi.

**Antes** significa utilizar las funciones nativas de la instalación de Dolibarr sin nuestras reglas de Renting. No significa que Dolibarr carezca de contratos, números de serie, almacenes o facturas recurrentes, ni que no existan otros módulos externos de alquiler.

**Después actual** describe lo implementado. **Después propuesto** describe trabajo pendiente; no debe presentarse como funcionalidad disponible. La separación de estos tres procesos debe contrastarse con el criterio del profesor: no se ha verificado aquí el texto exacto de la consigna.

## 1. Comparación general: limitación, consecuencia y mejora

**Actualización de implementación posterior a la comparación original:** la integración de facturación descrita abajo como propuesta ya dispone de creación de plantilla desde el renting, protección contra duplicados del vínculo, visualización de facturas, activación con entrega y pausa por devolución. Permite ajustar a las unidades restantes y reanudar futuras mensualidades con confirmación. La explicación detallada y vigente está en [FACTURACION-RENTING.md](FACTURACION-RENTING.md). Los párrafos de propuesta de este documento conservan el planteamiento original; ya no representan por sí solos la lista de pendientes. Siguen fuera los prorrateos, reembolsos y migración de plantillas antiguas. La sustitución de equipos del mismo producto y la pantalla técnica ya están implementadas; consulta SUSTITUCIONES-Y-REPARACIONES.md.

| Proceso | Antes: limitación y consecuencia para el negocio | Después actual: qué resolvemos | Después propuesto / pendiente | Estado |
|---|---|---|---|---|
| Gestión operativa del renting | El contrato y el stock no reunían en un mismo flujo la unidad alquilada, sus fechas de ocupación y su devolución. El operador debía cruzar esos datos, con riesgo de comprometer la misma máquina en periodos coincidentes o perder el seguimiento de devoluciones parciales. | Un renting reúne cliente, contrato, servicio y series. El sistema rechaza reservas solapadas y registra la entrega y devolución de cada unidad, permitiendo conocer cuáles siguen con el cliente. | Sustitución dentro del renting, renovación, penalizaciones y cierre coordinado con contrato y facturación. | Implementado para el circuito básico; excepciones parcialmente cubiertas. |
| Asignación de inventario entre venta y renting | Separar almacenes no bastaba para hacer cumplir las reglas de ambas líneas de negocio. Comprobar qué unidades podían venderse o reasignarse dependía del operador, con riesgo de comprometer para venta stock destinado al alquiler. | Los movimientos controlados separan el stock por destino y los controles añadidos impiden devolver a venta activos con reservas pendientes y validar pedidos sin stock autorizado suficiente en RR-VENTA. | Adaptar y probar la disponibilidad mostrada en catálogo/carrito del e-commerce. Formalizar la política operativa de reasignación. | Parcialmente implementado; controles internos disponibles, integración visual del e-commerce pendiente. |
| Facturación según el ciclo del renting | La programación de facturas se gestionaba aparte del alquiler. Había que configurar los datos de cobro y recordar actualizar la plantilla ante cambios, con riesgo de omitir mensualidades o seguir facturando tras una devolución anticipada. | Quedó operativa la ejecución periódica de plantillas configuradas: se generan borradores al llegar su fecha sin que el asesor lance cada generación. La configuración y los ajustes por cambios del alquiler siguen siendo manuales. | Vincular la programación al renting para evitar introducir sus condiciones por separado, prevenir plantillas duplicadas y gestionar la suspensión o ajuste de mensualidades futuras. | Automatización de ejecución disponible; la desconexión entre alquiler y facturación todavía no está resuelta. |

## 2. Proceso 1: gestión operativa del renting

**Objetivo:** entregar unidades identificables durante un periodo y recuperar su disponibilidad después de la devolución y revisión.

- **Inicio:** un cliente solicita alquilar uno o más equipos.
- **Actores:** asesor comercial, encargado de equipos y cliente.
- **Entradas:** cliente, contrato validado, servicio del contrato, fechas y equipos identificados por serie.
- **Resultado:** equipos entregados y posteriormente devueltos, con estado e historial registrados.

### Antes

El asesor podía registrar el contrato y consultar las existencias, pero no disponía de nuestro flujo que reuniera **qué máquina se había comprometido, con quién, durante qué fechas y cuáles unidades habían regresado**. Para atender una nueva solicitud debía cruzar esa información y comprobar la disponibilidad por su cuenta.

Esa dependencia de la coordinación manual dejaba margen para prometer una unidad en periodos coincidentes o tratar una devolución parcial como si todo el alquiler hubiera terminado. Son riesgos del flujo anterior, no incidentes históricos acreditados.

**Flujo base para el diagrama:**

Solicitud → registrar cliente y contrato → comprobar y coordinar disponibilidad → identificar unidades → registrar entrega/movimientos → controlar devolución → registrar movimientos de retorno.

### Después actual

**Qué resolvemos:** el renting centraliza la asignación de cada serie al cliente y al periodo. Las comprobaciones de disponibilidad bloquean reservas incompatibles y las devoluciones se registran por equipo, de modo que recibir una máquina no oculta las que siguen entregadas.

Solicitud → seleccionar cliente, contrato y servicio → seleccionar fechas y una o varias unidades → comprobar reservas y estado → crear reserva → entregar equipos → registrar devoluciones individuales → enviar a revisión → decidir disponibilidad, reparación o venta.

**Decisiones para el diagrama:**

- ¿El contrato corresponde al cliente y contiene el servicio? Si no, rechazar la reserva.
- ¿Hay una reserva solapada o una devolución vencida del equipo? Si sí, rechazar esa selección.
- ¿Llegó el periodo y los equipos están disponibles para entregar? Si no, impedir la entrega.
- ¿Volvieron todos los equipos? Si no, mantener devolución parcial; si sí, finalizar el renting.
- ¿El equipo está dañado? Enviarlo a reparación antes de volver a ofrecerlo.

**Ejemplo:** un cliente alquila dos PCs bajo el mismo renting. Se registran ambas series. Si devuelve una, la otra permanece entregada y el renting queda en devolución parcial.

**Límite:** finalizar el renting no cierra automáticamente el contrato ni suspende su plantilla de facturación. La reparación representa un estado y movimiento; no hay gestión completa de órdenes de taller.

## 3. Proceso 2: asignación de inventario entre venta y renting

**Objetivo:** decidir qué unidades pueden ofrecerse en cada línea de negocio y evitar comprometer el mismo equipo para venta y alquiler.

- **Inicio:** recepción de equipos o decisión interna de reasignar unidades.
- **Actores:** responsable de inventario, encargado de equipos y ventas.
- **Entradas:** producto, unidades/series, estado físico y compromisos existentes.
- **Resultado:** unidades destinadas a venta, renting, revisión o reparación con existencias coherentes.

### Antes

La empresa podía organizar productos por almacén, pero esa separación no hacía cumplir por sí sola **qué stock debía atender ventas y qué unidades debían permanecer en la flota de alquiler**. Antes de vender o reasignar equipos, el responsable tenía que comprobar sus compromisos y aplicar la política comercial.

Si esa revisión se omitía, existía el riesgo de ofrecer stock destinado a renting o intentar pasar a venta una unidad con una reserva pendiente. El límite no era la ausencia de almacenes en Dolibarr, sino la falta de nuestras reglas para coordinar ambos usos del inventario.

**Flujo base para el diagrama:**

Recibir stock → almacenar → decidir destino comercial → comprobar compromisos manualmente → transferir o disponer unidades → ofrecer para venta/alquiler.

### Después actual

**Qué resolvemos:** la separación deja de depender únicamente de una decisión manual. Las transferencias del módulo conservan las existencias y los controles añadidos verifican el stock de venta y las reservas antes de permitir las operaciones cubiertas. Así se protege la asignación del inventario entre ambos canales dentro del flujo integrado.

Stock en RR-VENTA → seleccionar producto y serie existente → comprobar disponibilidad y compromisos de venta → incorporar a Renting → transferir a RR-RENTING → reservar/entregar mediante el proceso 1.

Para volver a venta:

Equipo disponible o devuelto → revisar estado y reservas → autorizar destino venta → transferir a RR-VENTA.

| Almacén | Uso en el proceso |
|---|---|
| RR-VENTA | Existencias destinadas a ventas. |
| RR-RENTING | Unidades de la flota disponibles físicamente para alquiler; las reservas se consultan por fechas. |
| RR-CLIENTES | Equipos entregados en alquiler. |
| RR-REVISION | Equipos pendientes de revisión después de regresar. |
| RR-REPARACION | Equipos apartados para reparación. |

**Reglas añadidas que constituyen la personalización:**

- La incorporación usa una serie existente y movimientos nativos; no crea stock físico adicional.
- No se permite destinar a venta un activo con reservas pendientes.
- Los movimientos ordinarios hacia/desde los almacenes protegidos se bloquean fuera del flujo autorizado de Renting.
- La validación estándar de pedidos y determinados cambios en pedidos validados comprueban RR-VENTA, descontando compromisos de otros pedidos abiertos.

**Ejemplo:** de 100 laptops, la empresa destina 30 al alquiler. Al incorporarlas, pasan de RR-VENTA a RR-RENTING. Las ventas deben apoyarse en las unidades disponibles para ese canal, considerando pedidos pendientes.

**Pendiente:** el catálogo y carrito del e-commerce todavía requieren adaptación y pruebas para mostrar disponibilidad conforme a RR-VENTA. No afirmar que toda integración externa está protegida: una que escriba directamente en base de datos u omita los eventos estándar no está cubierta.

**Por qué es distinto del proceso 1:** puede ejecutarse sin una solicitud de cliente; decide el destino comercial del inventario, mientras que el renting asigna temporalmente unidades a un cliente concreto.

## 4. Proceso 3: facturación según el ciclo del renting

**Objetivo:** generar las mensualidades pactadas y mantener la programación alineada con la situación del alquiler.

- **Inicio:** acuerdo de condiciones económicas del renting o modificación posterior.
- **Actores:** asesor comercial, responsable de facturación, sistema y cliente.
- **Entradas:** cliente, servicio, cantidad, precio, impuestos, fechas y número de periodos.
- **Resultado:** facturas revisables y programación de futuras mensualidades coherente con el acuerdo.

### Antes

El acuerdo de alquiler y su programación de cobro se gestionaban por separado. El asesor debía trasladar cliente, servicio, cantidades, importes y calendario a una plantilla, además de recordar modificarla cuando cambiaba el alquiler.

Esa separación permitía que la programación quedara desalineada con la operación: por ejemplo, seguir generando mensualidades después de recuperar todos los equipos. Dolibarr ya disponía de recurrencia y tareas programadas; la limitación para nuestro negocio era **la falta de conexión entre el ciclo del renting y esas funciones de facturación**.

**Flujo base para el diagrama:**

Contrato acordado → preparar factura y plantilla → configurar frecuencia, fecha y límite → ejecutar generación manual o programada → revisar y validar factura → registrar pago al recibirlo.

### Después actual

**Qué resolvemos hoy:** dejamos configurada la ejecución periódica, por lo que una plantilla elegible genera su borrador al llegar la fecha sin intervención del asesor en cada periodo. **Qué aún no resolvemos:** crear esa plantilla desde el renting y mantenerla alineada con devoluciones o cambios del servicio. El job no corrige por sí solo esa separación.

Renting registrado → abrir facturación del contrato → preparar borrador → crear plantilla manualmente → job consulta plantillas cada minuto → comprobar fecha, suspensión y límite → generar borrador → revisión humana → validar → registrar pago cuando se reciba.

**Condiciones del job:** plantilla de la entidad actual, frecuencia positiva, fecha definida y alcanzada, no suspendida, con generaciones pendientes y configurada para borradores. Revisa plantillas recurrentes elegibles, no únicamente las relacionadas con renting. Las configuradas para validar/enviar automáticamente se omiten.

El calendario y contador de Dolibarr evitan repetir el mismo periodo en las ejecuciones sucesivas normales del job. No se debe prometer protección absoluta ante creación de plantillas duplicadas o generación manual simultánea. Validar una factura no significa que esté pagada.

### Después propuesto — no implementado

**Qué se busca resolver:** que las condiciones del alquiler alimenten su programación de cobro y que una finalización anticipada incluya la decisión sobre las mensualidades futuras. Esto reduciría la introducción repetida de datos y la dependencia de recordar un ajuste en otra pantalla, manteniendo la intervención del asesor cuando corresponda.

Renting y condiciones económicas confirmados → comprobar si ya existe una programación asociada → crear y vincular plantilla si corresponde → ejecutar generación periódica → revisar y validar facturas → registrar pagos.

Rama de modificación:

Cancelación anticipada o cambio de servicio → revisar fecha efectiva y mensualidades ya emitidas → asesor confirma tratamiento → suspender o ajustar periodos futuros → registrar motivo e historial.

**Alcance inicial propuesto:**

1. Crear y vincular la plantilla desde el renting usando las funciones nativas de Dolibarr.
2. Evitar una segunda programación para el mismo renting.
3. Mostrar la plantilla y las facturas relacionadas en su ficha.
4. Incorporar una acción explícita y confirmada para suspender futuros periodos al terminar antes.
5. Definir cómo se ajustan cantidades y tarifas; inicialmente puede requerir aprobación del asesor, sin prorrateo automático.

**Ejemplo:** un alquiler de seis mensualidades finaliza tras tres. Hoy se registra la devolución y se suspende la plantilla aparte. En el flujo propuesto, la finalización ofrecería gestionar esa suspensión y dejar constancia de la decisión. No eliminaría facturas emitidas ni calcularía reembolsos automáticamente.

**Por qué es distinto:** su resultado es documental y económico; no mueve equipos. El job actual es infraestructura de ejecución, no constituye por sí solo toda la modificación propuesta del proceso.

## 5. Resumen de casos límite y fronteras entre procesos

| Situación | Respuesta actual | Intervención pendiente |
|---|---|---|
| Varias máquinas en el mismo renting | Selección de varias series, entrega conjunta y devoluciones individuales. | La cantidad del servicio facturado no se sincroniza con el número de equipos seleccionados. |
| Cancelación antes de entregar | Se puede cancelar la reserva. | Revisar por separado contrato y facturación preparada. |
| Devolución anticipada completa | El renting finaliza al recibir todos los equipos. | Cerrar contrato y suspender plantilla según el acuerdo. |
| Devolución parcial | El renting mantiene los demás equipos entregados. | Ajustar facturación si el cambio lo requiere. |
| Equipo dañado | Condición registrada, revisión y destino reparación. | Diagnóstico, costos, responsabilidad y posibles cargos. |
| Sustitución de equipo | Incidencia e intercambio de series del mismo producto dentro del renting original; original a revisión y sustituto al cliente, sin cambiar facturación. | Modelos diferentes, costos de reparación e intercambio en momentos separados. |
| Equipo con devolución vencida | Sigue entregado y bloquea nuevas reservas sobre él. | Recargos, extensión y gestión de recuperación. |
| Impago | La factura mantiene su saldo pendiente; Dolibarr permite registrar pagos. | Políticas de suspensión del servicio o recuperación; no se ejecutan desde Renting. |

## 6. Cómo preparar los diagramas

Preparar dos diagramas por proceso: **antes** y **después**. Para facturación, distinguir visualmente el después actual del después propuesto, o elaborar un tercer diagrama de la propuesta.

Usar carriles para los actores indicados, tareas para las actividades, rombos para las decisiones y eventos de inicio/fin. Separar las actividades humanas de las automáticas. Etiquetar las tareas pendientes con “Propuesto”; no dibujarlas como ya operativas.

Conexiones entre procesos:

- Asignación de inventario proporciona unidades autorizadas al proceso de renting.
- Renting identifica cliente, contrato, servicio y equipos; la conexión automática con la programación de facturación todavía es una propuesta.
- Una devolución conduce a revisión y eventual reasignación; el ajuste económico requiere una acción independiente en la versión actual.

## 7. Implementación sin modificar el núcleo

El enfoque es ampliar el módulo personalizado y utilizar clases, movimientos, contratos, facturas y puntos de extensión nativos. Compose monta los archivos personalizados y ejecuta la configuración inicial y los seeders.

La propuesta no requiere de entrada modificar el código original de Dolibarr ni construir una imagen propia. Cada integración futura deberá verificarse contra la versión instalada. Configurar CRC, el modo oscuro o datos de demostración facilita el entorno, pero no debe contarse como un proceso de negocio modificado adicional.

## 8. Mensaje sugerido para la presentación

“Adaptamos el ERP a tres ámbitos de nuestro negocio: gestión operativa del alquiler, asignación del inventario entre venta y renting, y facturación vinculada al ciclo del alquiler. El circuito básico de equipos y los controles internos de inventario están disponibles. La ejecución de facturas recurrentes funciona, mientras que su vinculación automática con los cambios del renting es una mejora pendiente. Reutilizamos capacidades nativas mediante un módulo personalizado, sin plantear modificaciones del núcleo del ERP.”

## Referencias locales de alcance

- `RENTING-GUIA.md`: operación y limitaciones del módulo.
- `dolibarr-custom/verleih/class/rrrenting.class.php`: reservas, movimientos y devoluciones.
- `dolibarr-custom/verleih/class/actions_verleih.class.php`: protección de movimientos de stock.
- `dolibarr-custom/verleih/core/triggers/interface_90_modVerleih_RrRentingSales.class.php`: controles en pedidos de venta.
- `dolibarr-custom/verleih/lib/recurring_billing.php`: selección y ejecución de plantillas.
- `compose.yaml` y `docker/start/`: arranque, configuración y seeders.

Estas referencias permiten contrastar lo documentado con el código; este documento no acredita una nueva ronda de pruebas funcionales.
