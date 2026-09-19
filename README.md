# R&R Technology - Entorno Docker

Este repositorio contiene la configuración necesaria para levantar el entorno local de R&R Technology utilizando Docker.

Actualmente el entorno incluye:

- Dolibarr ERP
- MariaDB

## Requisitos

Antes de iniciar, asegúrate de tener instalado:

- Docker Desktop
- Git

## Clonar el repositorio

```bash
git clone <URL_DEL_REPOSITORIO>
cd rr-technology
```

## Configurar variables de entorno

El archivo `.env` no se incluye en el repositorio por seguridad.

Crea una copia del archivo `.env.example`.

### Windows PowerShell

```powershell
Copy-Item .env.example .env
```

Luego edita el archivo `.env` y coloca las credenciales correspondientes.

## Levantar los contenedores

Desde la raíz del proyecto, ejecuta:

```bash
docker compose up -d
```

Docker descargará automáticamente las imágenes necesarias si no están disponibles localmente y levantará los contenedores definidos en `compose.yaml`.

Una vez iniciado el entorno, Dolibarr estará disponible en:

```text
http://localhost:8080
```

## Verificar los contenedores

```bash
docker compose ps
```

## Ver logs

```bash
docker compose logs
```

Para seguir los logs en tiempo real:

```bash
docker compose logs -f
```

## Detener los contenedores

```bash
docker compose down
```

Este comando detiene y elimina los contenedores, pero mantiene los volúmenes y los datos persistentes.

## Volver a levantar el entorno

```bash
docker compose up -d
```

## Importante

Evitar utilizar:

```bash
docker compose down -v
```

salvo que se desee eliminar también los volúmenes del proyecto, ya que esto puede borrar los datos almacenados en MariaDB.
