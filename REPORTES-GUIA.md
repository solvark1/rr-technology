# Módulo de análisis de R&R

El panel conserva los diez reportes y sus cálculos: tres principales, dos operativos y cinco de redes sociales. Comparte colores, tarjetas y tipografía con Renting. Incluye fecha de actualización en Costa Rica, ampliación de gráficos y distribución adaptable a móvil.

## Arranque automático

Ejecutar `docker compose up -d --build`. Compose construye `rr-technology/dolibarr:24.0.0`, que añade las dependencias Python existentes al Dolibarr base. El script `49-enable-reportes.php` activa Reportes de forma idempotente y prepara el directorio de imágenes. No se requiere instalar el módulo manualmente.

No se necesita borrar volúmenes. La activación no modifica los datos comerciales.

## Actualización

Un proceso Python iniciado por Compose como `www-data` genera los diez gráficos al arrancar y luego cada cinco minutos. El botón **Actualizar reportes** solicita una ejecución anticipada mediante un marcador fijo, protegido por el token de formulario de Dolibarr. La página consulta el progreso y se recarga al finalizar.

PHP no ejecuta comandos: `shell_exec` permanece deshabilitado. El proceso ejecuta únicamente los seis scripts predefinidos; el visitante no puede elegir programas ni rutas. Cada imagen se sustituye al terminar su generación. Si falla una fuente, se conserva su último gráfico con su fecha y aparece un aviso. Las tareas con errores se identifican en `docker compose logs dolibarr` sin mostrar credenciales en la página.

## Fuentes y alcance

Las cinco consultas SQL no cambian. La conexión MariaDB utiliza las variables DOLI_DB_HOST, DOLI_DB_USER, DOLI_DB_PASSWORD y DOLI_DB_NAME del contenedor. Los reportes sociales mantienen la fuente MongoDB del módulo; el script permite reemplazar su URI mediante RR_MONGO_URI. Las agregaciones sociales tampoco cambian.

Los reportes originales consultan la entidad 1. El panel se limita a usuarios internos de esa entidad para no mostrar esos datos en otra empresa. No se introduce soporte multientidad que las consultas originales no ofrecen.

Las mejoras de gráficos son de presentación: colores, etiquetas, orientación, resolución y espaciado. Por ejemplo, el ranking mantiene el conteo de asignaciones original, incluidas las que su consulta ya contabilizaba; no se redefine como ingresos ni entregas efectivas.

## Verificación realizada

- Diez imágenes generadas correctamente, incluidas las cinco de MongoDB.
- Comparación de las cinco consultas SQL con la versión original: sin cambios.
- Panel autenticado sin errores JavaScript, imágenes cargadas y actualización manual completada.
- Vista móvil de 390 px sin desbordamiento del panel.
- PHP y Python comprobados sintácticamente.
