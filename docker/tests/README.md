# Prueba del seeder de renting

Esta prueba requiere Docker funcionando. El proyecto `rr-renting-seed-test` usa contenedores y volúmenes independientes, sin puertos publicados. Ejecutar desde la raíz del repositorio.

```powershell
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml up -d
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml logs --tail=100 dolibarr
```

Esperar al mensaje `[RR-RENT-SEED] Seeder completado correctamente` y a que Apache haya arrancado. Después:

```powershell
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml exec -T dolibarr php /rr-seed-tests/verify-renting-seed.php initial
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml exec -T -e RR_SEED_TEST_ALLOW_MUTATION=isolated-project-only dolibarr php /rr-seed-tests/verify-renting-seed.php move
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml up -d --no-deps --force-recreate dolibarr
```

Esperar al arranque; el log debe indicar que la versión del seeder ya se ejecutó. Comprobar que la unidad trasladada siga en renting y no se haya duplicado:

```powershell
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml exec -T dolibarr php /rr-seed-tests/verify-renting-seed.php moved
```

Los datos y contraseñas de esta configuración son exclusivamente de prueba. La ejecución de estas pruebas está pendiente mientras el motor de Docker no esté disponible; la configuración Compose se validó sin iniciar contenedores.

Al terminar, eliminar únicamente el proyecto de prueba mediante sus mismos argumentos explícitos:

```powershell
docker compose -p rr-renting-seed-test -f compose.yaml -f docker/tests/seed-test.override.yaml down -v
```

No omitir `-p rr-renting-seed-test` ni los archivos `-f`: el comando anterior solo corresponde a los volúmenes de prueba.
