
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from rr_chart_style import save_chart


import pandas as pd
import matplotlib.pyplot as plt
from pymongo import MongoClient

cliente = MongoClient(
    "mongodb://localhost:27017/",
    serverSelectionTimeoutMS=10000,
    connectTimeoutMS=10000
)

db = cliente["R&R_Social"]
coleccion = db["posts"]

# Obtener los datos UNA sola vez
datos = list(coleccion.find())

# Convertir a DataFrame UNA sola vez
df = pd.json_normalize(datos)


ruta = "/var/www/html/custom/reportes/python/graficos/"


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

save_chart(
    ruta + "interacciones_plataforma.png",
    bbox_inches="tight"
)

plt.close()

resultado = (
    df.groupby("producto")["metricas.interacciones_totales"]
    .sum()
    .sort_values(ascending=False)
)

plt.figure(figsize=(8, 5))

resultado.plot(kind="barh")

plt.title("Interacciones por producto")
plt.xlabel("Interacciones")
plt.ylabel("")
plt.gca().invert_yaxis()

plt.tight_layout()

save_chart(
    ruta + "interacciones_producto.png",
    bbox_inches="tight"
)

plt.close()

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

save_chart(
    ruta + "interacciones_ubicacion.png",
    bbox_inches="tight"
)

plt.close()

resultado = pd.crosstab(
    df["producto"],
    df["sentimiento"]
)

plt.figure(figsize=(14, 7))

ax = resultado.plot(
    kind="barh",
    stacked=True,
    color=[{"positivo":"#65dbc3", "neutral":"#91a6b9", "negativo":"#f18b99"}.get(str(c).lower(),"#70b6ed") for c in resultado.columns],
    figsize=(14, 7)
)

ax.invert_yaxis()

plt.title("Sentimiento por producto", pad=18)
plt.xlabel("Cantidad de publicaciones", labelpad=12)
plt.ylabel("")

plt.xticks(
    rotation=45,
    ha="right"
)

plt.tight_layout()

save_chart(
    ruta + "sentimiento_producto.png",
    bbox_inches="tight"
)

plt.close()

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

save_chart(
    ruta + "tipo_publicacion_interacciones.png",
    bbox_inches="tight"
)

plt.close()


cliente.close()
