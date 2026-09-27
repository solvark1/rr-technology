# Procesos de negocio actuales de R&R Technology

**Guía para diagramar en Bizagi Modeler — estado actual de la personalización**  
**Revisión:** 26 de septiembre de 2026  
**Sistema:** Dolibarr con el módulo personalizado Renting.

## 1. Alcance

Este documento describe únicamente el funcionamiento actual. Sustituye la comparación anterior y sus propuestas desactualizadas. Se conserva el nombre del archivo para no romper las referencias del proyecto.

Los tres procesos de negocio son:

| Proceso | Resultado de negocio | Personalización que lo respalda |
|---|---|---|
| 1. Gestión del renting | Equipos asignados a un cliente, entregados, atendidos y recuperados con trazabilidad. | Reserva según contrato, control de disponibilidad por serie, sustituciones y devolución definitiva conjunta. |
| 2. Asignación y recuperación del inventario | Unidades aptas disponibles para venta o alquiler, separadas de revisión y reparación. | Movimientos controlados, diagnóstico, reincorporación y restricciones de venta y reasignación. |
| 3. Facturación del renting | Mensualidades generadas y revisadas, con cobros registrados y programación vinculada al servicio. | Plantilla vinculada al renting, activación con entrega, borradores periódicos y suspensión por devolución definitiva. |

**Relación con el enunciado:** la página 3 exige al menos tres procesos personalizados **aparte del correspondiente al portal electrónico**, y su presentación al docente mediante una herramienta BPM. Estos son los tres procesos internos; este documento no sustituye el diagrama del e-commerce ni acredita aprobación del docente. No se cuentan configuración de moneda, imágenes o seeders como procesos adicionales.

## 2. Convenciones para dibujar en Bizagi

Utilizar BPMN 2.0 con etiquetas breves. Las tareas se nombran con verbo y objeto, por ejemplo **Registrar contrato**. Los identificadores de las tablas sirven para conectar figuras; no necesitan aparecer en el dibujo.

| Elemento | Uso en estos diagramas |
|---|---|
| Pool «R&R Technology» | Contiene el proceso interno. |
| Carril | Responsable: Comercial, Logística, Servicio técnico, Facturación o Sistema ERP, según el diagrama. |
| Pool «Cliente», cerrado | Participante externo; no es un carril de R&R. Se usa cuando hay solicitudes o comunicaciones con el cliente. |
| Evento de inicio / fin | Círculo de inicio y círculo de borde grueso para un resultado final. |
| Tarea de usuario | Una persona registra, revisa o confirma una operación en el ERP. |
| Tarea manual | Trabajo físico, como revisar o reparar un equipo. |
| Tarea de servicio | Operación automática del ERP, como generar una factura. |
| Compuerta exclusiva (X) | Una decisión con alternativas excluyentes. Etiquetar todas sus salidas. |
| Compuerta basada en eventos | Espera una de varias comunicaciones; cada salida conduce a un evento de recepción de mensaje. |
| Evento temporizador | Espera hasta la próxima fecha; evita dibujar la revisión técnica de cada minuto. |
| Subproceso colapsado (+) | Agrupa una actividad extensa; su detalle se dibuja aparte. |
| Objeto o almacén de datos | Contrato, expediente del renting, inventario, plantilla y factura. Conectarlos mediante asociaciones de datos. |

Las flechas continuas representan secuencia dentro del mismo pool, incluso entre carriles. Los mensajes entre R&R y Cliente se dibujan con flecha discontinua; no usar mensajes entre carriles de R&R. Las relaciones entre los tres diagramas se explican mediante datos compartidos y anotaciones, sin inventar mensajes de software que no existen.

Fuentes de notación: [compuertas de Bizagi](https://help.bizagi.com/platform/en/gateways.htm) y [conectores de Bizagi](https://help.bizagi.com/platform/en/connectors.htm).

**Nivel de detalle:** una figura representa una actividad de negocio, no un clic, consulta o movimiento individual de base de datos. Las validaciones técnicas se resumen como una tarea del sistema seguida de una decisión.

## 3. Proceso 1 — Gestión del renting

**Objetivo:** atender una solicitud de alquiler, mantener el servicio y recuperar las unidades al finalizarlo.  
**Carriles:** Comercial, Logística y Sistema ERP.  
**Participante externo:** Cliente.  
**Datos:** contrato validado, expediente del renting y equipos identificados por serie.

### 3.1 Flujo principal

| ID | Figura BPMN | Carril | Nombre de la figura | Siguiente paso / salida |
|---|---|---|---|---|
| R01 | Inicio de mensaje | Comercial | Solicitud de alquiler recibida | R02. Mensaje desde Cliente. |
| R02 | Tarea de usuario | Comercial | Acordar y registrar contrato | R03. Cliente, servicio, cantidad, tarifa y periodo quedan en el contrato. |
| R03 | Tarea de usuario | Logística | Seleccionar unidades para el contrato | R04. Las fechas y cantidades se toman de la línea contractual. |
| R04 | Tarea de servicio | Sistema ERP | Comprobar disponibilidad y condiciones | R05. |
| R05 | Compuerta exclusiva | Sistema ERP | ¿Selección válida y completa? | Sí → R06. No → R03 para corregir; la reserva no se crea. |
| R06 | Tarea de usuario | Logística | Confirmar reserva | R07. |
| R07 | Tarea de usuario | Comercial | Preparar programación de cobro | R08. Corresponde a la preparación del proceso 3. |
| R08 | Tarea de usuario | Logística | Coordinar entrega con el cliente | R09. |
| R09 | Compuerta exclusiva | Logística | ¿El cliente mantiene la reserva? | Sí → R10. No → R16. |
| R10 | Tarea de usuario | Logística | Entregar y registrar todos los equipos | R11. Solo cuando el periodo y la disponibilidad permiten la entrega completa. |
| R11 | Compuerta basada en eventos | Logística | Esperar novedad del cliente | Reporte de falla → R12. Devolución definitiva acordada → R14. |
| R12 | Evento intermedio de mensaje | Logística | Reporte de falla recibido | R13. Mensaje desde Cliente. |
| R13 | Subproceso colapsado | Logística | Atender incidencia y restablecer servicio | Servicio restablecido → R11. Cliente decide finalizar → R15. Detalle en 3.2. |
| R14 | Evento intermedio de mensaje | Logística | Devolución definitiva acordada | R15. Mensaje desde Cliente, por fin del periodo o devolución anticipada. |
| R15 | Tarea de usuario | Logística | Recibir todos y finalizar renting | R17. Confirma recepción física de todas las series pendientes. |
| R16 | Tarea de usuario | Logística | Cancelar reserva sin entrega | R18. Libera las unidades y mantiene detenida la programación preparada. |
| R17 | Fin | Logística | Renting finalizado y equipos recibidos | Fin de esta operación comercial. |
| R18 | Fin | Logística | Reserva cancelada | Fin sin entrega de equipos. |

**Anotaciones para el dibujo:**

- R10 traslada las unidades a poder del cliente y habilita la programación vinculada. La generación de facturas se desarrolla en el proceso 3; no hay que esperar al pago para representar la entrega.
- Si no puede completarse R10, la reserva continúa pendiente y Logística debe resolver la disponibilidad. No se representa una entrega exitosa con unidades faltantes.
- R15 envía todas las unidades pendientes a revisión, cancela entregas de sustitutos pendientes y detiene futuras mensualidades. La operación se registra completa o se revierte si falla.
- La revisión física continúa en el proceso 2, sin impedir el cierre del renting. El cierre no cancela automáticamente todos los servicios del contrato nativo ni elimina facturas anteriores.
- El vencimiento por sí solo no registra una devolución: Logística debe recibir realmente los equipos.

### 3.2 Subproceso — Atender incidencia y restablecer servicio

Dibujar como detalle de R13, no como un cuarto proceso de negocio. Carriles: Logística y Sistema ERP. Mantener Cliente como participante externo.

| ID | Figura BPMN | Carril | Nombre de la figura | Siguiente paso / salida |
|---|---|---|---|---|
| I01 | Inicio simple | Logística | Incidencia por atender | I02. |
| I02 | Tarea de usuario | Logística | Registrar reporte e identificar equipo | I03. Se identifica cliente, renting activo y serie. |
| I03 | Tarea de usuario | Logística | Recibir equipo por incidencia | I04. El equipo pasa a revisión y queda una entrega pendiente. |
| I04 | Tarea de usuario | Logística | Consultar unidad apta para entregar | I05. Puede ser otro equipo o el original ya reparado. |
| I05 | Compuerta exclusiva | Logística | ¿Hay unidad disponible del mismo producto? | Sí → I06. No → I08. |
| I06 | Tarea de usuario | Logística | Entregar y registrar unidad de reemplazo | I07. El sistema vuelve a comprobar la disponibilidad del periodo restante. |
| I07 | Fin | Logística | Servicio restablecido | Salida de R13 hacia R11. |
| I08 | Tarea de usuario | Logística | Coordinar espera con el cliente | I09. La reparación o liberación de stock ocurre en el proceso 2. |
| I09 | Compuerta exclusiva | Logística | ¿El cliente mantiene el servicio? | Sí → I10. No → I11. |
| I10 | Tarea de usuario | Logística | Dar seguimiento a la unidad pendiente | Volver a I04 cuando exista nueva disponibilidad. No es una revisión automática continua. |
| I11 | Fin | Logística | Cliente solicita finalización | Salida de R13 hacia R15; recibir las demás unidades si las hay. |

**Reglas:** la incidencia no pausa la facturación, incluso durante la espera. Una pausa previa no se levanta automáticamente. La condición física se determina en revisión; el reporte del cliente no equivale a un diagnóstico. Se admite recibir y sustituir en el mismo momento, pero también en momentos diferentes. Cada entrega conserva su historial, incluso si vuelve la misma serie reparada.

Para no extender el diagrama, representar «Resultado de atención» con una compuerta exclusiva después de R13: **Restablecido → R11** / **Finalizar → R15**. Los finales I07 e I11 describen esos resultados; no conectar flechas desde dentro del subproceso a figuras externas.

## 4. Proceso 2 — Asignación y recuperación del inventario

**Objetivo:** mantener equipos aptos en el canal correcto y evitar comprometerlos simultáneamente para venta y alquiler.  
**Carriles:** Inventario y Logística, Servicio técnico y Sistema ERP.  
**Datos:** inventario por almacén, series, diagnóstico y compromisos comerciales.

Este proceso se inicia por recepción de existencias, por devolución desde renting o por decisión de reasignación. Puede ejecutarse sin un nuevo contrato de cliente. Para mantenerlo legible, dibujar un flujo principal y el subproceso de revisión por separado.

### 4.1 Flujo principal

| ID | Figura BPMN | Carril | Nombre de la figura | Siguiente paso / salida |
|---|---|---|---|---|
| A01 | Inicio simple | Inventario y Logística | Necesidad de asignar o recuperar una unidad | A02. |
| A02 | Compuerta exclusiva | Inventario y Logística | ¿La unidad requiere revisión? | Sí, devuelta o en reparación → A03. No, unidad apta disponible → A04. |
| A03 | Subproceso colapsado | Servicio técnico | Revisar y recuperar equipo | A04 cuando esté apto. Detalle en 4.2. |
| A04 | Tarea de usuario | Inventario y Logística | Definir destino comercial | A05. Venta o flota de renting. |
| A05 | Tarea de servicio | Sistema ERP | Comprobar condición y compromisos | A06. |
| A06 | Compuerta exclusiva | Sistema ERP | ¿Se permite la asignación? | Sí → A07. No → A08. |
| A07 | Tarea de usuario | Inventario y Logística | Confirmar destino de la unidad | A09. El ERP registra el movimiento correspondiente si cambia el almacén. |
| A08 | Tarea de usuario | Inventario y Logística | Revisar compromisos o elegir otra unidad | Volver a A04. La unidad bloqueada conserva su ubicación. |
| A09 | Fin | Inventario y Logística | Unidad asignada al canal autorizado | Disponible para atender ventas o reservas según sus compromisos. |

**Reglas que anotar junto a A05:**

- La incorporación inicial a la flota utiliza stock existente de RR-VENTA y una serie válida; no crea una unidad física nueva.
- Una unidad entregada permanece con el cliente hasta registrar su recepción. No se reasigna directamente.
- Un equipo dañado no puede quedar disponible ni pasar a venta.
- La salida de la flota hacia venta requiere ausencia de compromisos de renting pendientes.
- Una reserva futura no desaparece por enviar a reparación: el personal debe coordinar su atención.
- La revisión y elección de destino se confirman juntas en la interfaz. Se separan en el diagrama para explicar responsabilidades, no porque requieran dos formularios.

### 4.2 Subproceso — Revisar y recuperar equipo

| ID | Figura BPMN | Carril | Nombre de la figura | Siguiente paso / salida |
|---|---|---|---|---|
| T01 | Inicio simple | Servicio técnico | Equipo pendiente de diagnóstico | T02. |
| T02 | Tarea manual | Servicio técnico | Inspeccionar funcionamiento y condición | T03. |
| T03 | Compuerta exclusiva | Servicio técnico | ¿El equipo está apto para uso? | Sí → T06. No → T04. |
| T04 | Tarea de usuario | Servicio técnico | Registrar diagnóstico y enviar a reparación | T05. |
| T05 | Tarea manual | Servicio técnico | Reparar y probar equipo | Volver a T02. Si no puede recuperarse, permanece en reparación pendiente de resolución; no inventar una baja automática. |
| T06 | Tarea de usuario | Servicio técnico | Registrar resultado y justificación | T07. Condición buena o con desgaste apto para uso. |
| T07 | Fin | Servicio técnico | Equipo apto para asignación | Retorno a A04. |

Las reparaciones físicas y pruebas son trabajo de los técnicos. El módulo registra condición, observaciones y movimientos; no administra órdenes completas de taller, repuestos o costos de reparación.

### 4.3 Almacenes y control de venta

| Almacén | Significado |
|---|---|
| RR-VENTA | Stock del canal de venta. |
| RR-RENTING | Flota disponible físicamente; la elegibilidad depende también de las reservas por fechas. |
| RR-CLIENTES | Unidades entregadas a clientes. |
| RR-REVISION | Unidades recibidas pendientes de diagnóstico. |
| RR-REPARACION | Unidades en reparación. |

Como regla del canal de venta, al validar un pedido estándar de Dolibarr se comprueba **stock en RR-VENTA menos compromisos de otros pedidos abiertos**. Si no alcanza, se rechaza la validación. Puede añadirse como anotación de A09; no es necesario dibujar todo el proceso de venta dentro de este diagrama.

**Límite:** catálogo y carrito del e-commerce no tienen todavía integrada esta comprobación de disponibilidad. No presentar esa parte como implementada. Los equipos trasladados a venta conservan su historial de renting.

## 5. Proceso 3 — Facturación del renting

**Objetivo:** preparar las mensualidades y gestionar las facturas hasta registrar sus cobros.  
**Carriles:** Comercial, Facturación y Sistema ERP.  
**Participante externo:** Cliente.  
**Datos:** contrato, renting, programación mensual, facturas y pagos.

Para no hacer depender la factura del mes siguiente del pago de la anterior, utilizar **dos diagramas relacionados**: programación mensual y tratamiento de cada factura. Ambos son partes del mismo proceso de negocio.

### 5.1 Preparar y ejecutar la programación

| ID | Figura BPMN | Carril | Nombre de la figura | Siguiente paso / salida |
|---|---|---|---|---|
| F01 | Inicio simple | Comercial | Renting listo para programar cobro | F02. |
| F02 | Tarea de usuario | Comercial | Confirmar condiciones mensuales | F03. Verificar tarifa mensual y ausencia de otra programación manual duplicada. |
| F03 | Tarea de servicio | Sistema ERP | Crear plantilla vinculada al renting | F04. Usa cliente, servicio, cantidad, tarifa, impuestos y periodo contractual. |
| F04 | Tarea de servicio | Sistema ERP | Habilitar programación con la entrega | F05. Si aún no se entregó, la plantilla permanece en espera; si se cancela la reserva, no se activa. |
| F05 | Evento intermedio temporizador | Sistema ERP | Próxima revisión de vencimiento | F06. Primera ejecución elegible o próxima revisión programada. |
| F06 | Tarea de servicio | Sistema ERP | Comprobar programación y situación del renting | F07. |
| F07 | Compuerta exclusiva | Sistema ERP | ¿Qué corresponde hacer? | Renting finalizado o periodos agotados → F10. Pausado o fecha aún no alcanzada → F05. Activo y mensualidad vencida → F08. |
| F08 | Tarea de servicio | Sistema ERP | Generar factura en borrador | F09. Se inicia el tratamiento de esa factura descrito en 5.2. |
| F09 | Tarea de servicio | Sistema ERP | Actualizar próxima generación | Volver a F05. |
| F10 | Fin | Sistema ERP | Programación concluida | No generar nuevas mensualidades. Las facturas existentes siguen su trámite. |

F04 puede dibujarse como un subproceso pequeño de espera: **¿Equipos entregados?** → activar; si no, esperar cambio de estado; si la reserva fue cancelada, terminar sin generación. No modelar la cancelación como una entrega.

El temporizador representa la comprobación periódica; la condición comercial sigue siendo la fecha pactada. No hace falta escribir «cada minuto» en el diagrama. La espera por pago de una factura no detiene automáticamente la programación de las siguientes.

### 5.2 Revisar y cobrar cada factura

Este flujo se ejecuta una vez por cada borrador generado, de forma independiente al calendario de mensualidades. Relacionarlo con F08 mediante el objeto de datos **Factura en borrador** y una anotación; no conectarlo a F09 como una espera obligatoria.

| ID | Figura BPMN | Carril | Nombre de la figura | Siguiente paso / salida |
|---|---|---|---|---|
| C01 | Inicio simple | Facturación | Factura en borrador disponible | C02. |
| C02 | Tarea de usuario | Facturación | Revisar datos e importe | C03. |
| C03 | Compuerta exclusiva | Facturación | ¿Factura correcta? | Sí → C05. No → C04. |
| C04 | Tarea de usuario | Facturación | Corregir borrador | Volver a C02. No implica alterar automáticamente todas las mensualidades futuras. |
| C05 | Tarea de usuario | Facturación | Validar factura | C06. Validación y pago son operaciones diferentes. |
| C06 | Evento intermedio de mensaje | Facturación | Pago del cliente confirmado | C07. La confirmación la gestiona el personal; no existe conciliación bancaria automática en este flujo. |
| C07 | Tarea de usuario | Facturación | Registrar pago recibido | C08. Seleccionar la cuenta receptora. |
| C08 | Compuerta exclusiva | Facturación | ¿Saldo liquidado? | Sí → C09. No → C06 para esperar otro pago. |
| C09 | Fin | Facturación | Factura pagada | Fin de esta factura. |

No dibujar «Validar factura» después del pago por obligación: primero se formaliza la factura; después se registra el dinero recibido cuando corresponda. El envío de facturas o la gestión de mora pueden ser actividades del personal, pero no se presentan aquí como automatizaciones añadidas.

### 5.3 Reglas de coordinación con renting

| Suceso operativo | Efecto en las mensualidades |
|---|---|
| Reserva sin entrega | Programación en espera. |
| Entrega completa | Habilita la programación vinculada. |
| Reporte o recepción por incidencia | Mantiene la programación; no pausa automáticamente. |
| Entrega de sustituto o del original reparado | Mantiene cantidad y condiciones; respeta cualquier pausa previa. |
| Devolución definitiva conjunta | Detiene futuras mensualidades. |
| Fin del calendario | No genera mensualidades fuera del periodo. |
| Pausa decidida por el responsable | Suspende futuras generaciones; la reanudación requiere fecha y justificación. |

Los borradores ya generados, las facturas validadas y los pagos se conservan. No hay prorrateo, reembolso ni cobro bancario automático. Los casos heredados de devolución parcial permiten revisar cantidad y reanudar; no son el camino principal de cancelación que se muestra en el proceso 1.

## 6. Cómo montar los diagramas sin sobrecargarlos

1. Crear tres diagramas principales, uno por proceso. El tercero se puede presentar en dos páginas: programación y cobro.
2. Crear páginas de detalle para **Atender incidencia** y **Revisar y recuperar equipo**, utilizando subprocesos colapsados en los principales.
3. Dibujar de izquierda a derecha y colocar cada actividad en el carril que la realiza. Nombrar las compuertas como preguntas y sus salidas como respuestas.
4. Usar finales simples para resultados como «Renting finalizado» o «Factura pagada»; no usar fin de terminación, porque no se pretende cancelar otros procesos ni borrar facturas.
5. Colocar como anotaciones las reglas de almacén, cantidad completa, continuidad del cobro por incidencia y límites de automatización.
6. Verificar que cada decisión tenga todas sus salidas y que cada espera tenga una forma de continuar. No dibujar una flecha directa entre pools.

**Conexión conceptual:** inventario proporciona unidades aptas al renting; renting aporta el acuerdo y su situación a facturación; las recepciones devuelven equipos a revisión. Son procesos relacionados, con resultados distintos, no tres nombres para la misma operación.

## 7. Ejemplo breve para explicar en la defensa

Arena Tica contrata cinco PCs. Logística selecciona cinco series elegibles y registra su entrega. La programación factura cinco unidades del servicio mensual, con revisión y registro del pago por el personal.

Si una PC falla, se recibe por incidencia y pasa a revisión. El renting continúa y se entrega otra serie disponible, o la misma después de repararla. La facturación se mantiene. Cuando el cliente termina el alquiler, Logística confirma todas las unidades que todavía tiene el cliente, finaliza el renting y detiene futuras mensualidades. Servicio técnico revisa cada unidad para devolverla a la flota o decidir su destino autorizado.

## 8. Referencias y alcance documental

- Enunciado **Proyecto 1 - IF6201 2026 RP.pdf**, páginas 1 y 3: personalización de al menos tres procesos, modelado BPM y proceso del portal adicional.
- [Guía operativa de Renting](RENTING-GUIA.md).
- [Facturación del renting](FACTURACION-RENTING.md).
- [Recepciones y sustituciones](SUSTITUCIONES-Y-REPARACIONES.md).
- Implementación contrastada: `rrrenting.class.php`, `rrbilling.class.php`, generación recurrente y control de pedidos de venta del módulo.

Este documento sirve como especificación de los diagramas actuales. No es un archivo ejecutable de Bizagi ni una nueva prueba funcional del ERP. Las tareas humanas describen la operación que debe realizar R&R; las tareas automáticas corresponden a controles del sistema. El caso de equipos no devueltos, las penalizaciones y la baja definitiva de equipos no se amplían en este alcance.