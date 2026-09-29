
import pandas as pd
import matplotlib.pyplot as plt
from matplotlib.ticker import MultipleLocator
from pymongo import MongoClient

# ============================================================
# CONEXIÓN A MONGODB
# ============================================================

USUARIO = "luisballar"
PASSWORD = "123"
CLUSTER = "cluster0.6vh1buh.mongodb.net"

uri = f"mongodb+srv://{USUARIO}:{PASSWORD}@{CLUSTER}/?appName=Cluster0"

cliente = MongoClient(uri)

db = cliente["R&R_Social"]
coleccion = db["posts"]

# Obtener los datos UNA sola vez
datos = list(coleccion.find())

# Convertir a DataFrame UNA sola vez
df = pd.json_normalize(datos)


# ============================================================
# RUTAS DE LOS GRÁFICOS
# ============================================================

ruta = "/var/www/html/custom/reportes/python/graficos/"


# ============================================================
# 1. INTERACCIONES POR PLATAFORMA
# ============================================================

resultado = (
    df.groupby("plataforma")["metricas.interacciones_totales"]
    .sum()
    .sort_values(ascending=False)
)

plt.figure(figsize=(8, 5))

resultado.plot(kind="bar")

plt.title("Interacciones por plataforma")
plt.xlabel("Plataforma")
plt.ylabel("Interacciones")
plt.xticks(rotation=0)
plt.tight_layout()

plt.savefig(
    ruta + "interacciones_plataforma.png",
    bbox_inches="tight"
)

plt.close()


# ============================================================
# 2. INTERACCIONES POR PRODUCTO
# ============================================================

resultado = (
    df.groupby("producto")["metricas.interacciones_totales"]
    .sum()
    .sort_values(ascending=False)
)

plt.figure(figsize=(8, 5))

resultado.plot(kind="bar")

plt.title("Interacciones por producto")
plt.xlabel("Producto")
plt.ylabel("Interacciones")
plt.xticks(rotation=45)

plt.tight_layout()

plt.savefig(
    ruta + "interacciones_producto.png",
    bbox_inches="tight"
)

plt.close()


# ============================================================
# 3. INTERACCIONES POR UBICACIÓN
# ============================================================

resultado = (
    df.groupby("ubicacion")["metricas.interacciones_totales"]
    .sum()
    .sort_values(ascending=False)
)

plt.figure(figsize=(8, 5))

resultado.plot(kind="bar")

plt.title("Interacciones por ubicación")
plt.xlabel("Ubicación")
plt.ylabel("Interacciones")
plt.xticks(rotation=45)

plt.tight_layout()

plt.savefig(
    ruta + "interacciones_ubicacion.png",
    bbox_inches="tight"
)

plt.close()


# ============================================================
# 4. SENTIMIENTO POR PRODUCTO
# ============================================================

resultado = pd.crosstab(
    df["producto"],
    df["sentimiento"]
)

plt.figure(figsize=(14, 7))

ax = resultado.plot(
    kind="bar",
    stacked=True,
    figsize=(14, 7)
)

ax.yaxis.set_major_locator(MultipleLocator(50))

plt.title("Sentimiento por producto", pad=18)
plt.xlabel("Producto", labelpad=12)
plt.ylabel("Cantidad de publicaciones", labelpad=12)

plt.xticks(
    rotation=45,
    ha="right"
)

plt.tight_layout()

plt.savefig(
    ruta + "sentimiento_producto.png",
    bbox_inches="tight"
)

plt.close()


# ============================================================
# 5. INTERACCIONES POR TIPO DE PUBLICACIÓN
# ============================================================

resultado = (
    df.groupby("tipo_publicacion")["metricas.interacciones_totales"]
    .sum()
    .sort_values(ascending=False)
)

plt.figure(figsize=(8, 5))

resultado.plot(kind="bar")

plt.title("Interacciones por tipo de publicación")
plt.xlabel("Tipo de publicación")
plt.ylabel("Interacciones")

plt.xticks(
    rotation=45,
    ha="right"
)

plt.tight_layout()

plt.savefig(
    ruta + "tipo_publicacion_interacciones.png",
    bbox_inches="tight"
)

plt.close()


cliente.close()
