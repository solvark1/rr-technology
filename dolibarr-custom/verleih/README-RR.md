# Renting · R&R Technology

Adaptación local de Verleih para Dolibarr 24.0.0, versión 2.0.1.

La documentación del proceso está en [RENTING-GUIA.md](../../RENTING-GUIA.md).

## Archivos actuales

- `renting.php`: interfaz autenticada en español, basada en estilos nativos (incluido modo oscuro).
- `class/rrrenting.class.php`: reglas de negocio y transferencias transaccionales.
- `class/actions_verleih.class.php`: protección de almacenes de renting.
- `core/triggers/interface_90_modVerleih_RrRentingSales.class.php`: disponibilidad de ventas al validar pedidos.
- `lib/renting_schema.php`: esquema aditivo.
- `tests/integration.php`: pruebas de integración con reversión de datos temporales.
- `core/modules/modVerleih.class.php`: registro del módulo, dependencias, permisos y menús.

El nombre técnico y la ruta siguen siendo `verleih`. No hay un segundo repositorio Git.

El README y documentos originales describen el módulo escolar anterior; se conservan como referencia histórica. Sus páginas públicas ahora redirigen al flujo Renting; las clases y tablas escolares no se usan en el nuevo flujo ni se convierten automáticamente.

Original: Copyright (C) 2026 Kim Wittkowski. Modificaciones: R&R Technology, 2026. GPL-3.0-or-later.

