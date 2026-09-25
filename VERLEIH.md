# Renting (adaptación de Verleih)

El módulo instalado se ha adaptado a **Renting de R&R Technology**, versión 2.0.1. Conserva el identificador técnico `verleih` y el número 501000 para mantener las relaciones de instalación y permisos.

Consulta [RENTING-GUIA.md](RENTING-GUIA.md) para configurar productos, series, clientes y contratos y probar la separación entre venta y renting.

## Procedencia

- Fuente original: https://github.com/KimWittkowski1975/dolibarr-verleih
- Commit importado: `5e3df2bd1180eba913ab07f5e84a7808d86063a4`.
- Fecha de importación: 2026-09-23.
- Autor original: Kim Wittkowski. Adaptación: R&R Technology.
- Licencia: GPL-3.0-or-later. Se conservan atribuciones y licencia.
- Los 57 archivos originales se verificaron al importar. La versión actual incorpora cambios locales.
- No se incluyó ningún repositorio Git dentro del módulo.

## Diseño

Las nuevas tablas `rr_renting_*` vinculan equipos serializados, clientes, contratos y servicios nativos. Los movimientos usan la API de stock de Dolibarr. Los datos escolares se conservan sin conversión automática; sus páginas redirigen al nuevo módulo.

No se necesita borrar volúmenes. La migración de arranque es aditiva y repetible. Las funciones y límites de esta primera versión están descritos en la guía.

