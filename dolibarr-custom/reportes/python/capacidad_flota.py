import os
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from rr_chart_style import save_chart

import warnings
warnings.filterwarnings("ignore")

import matplotlib.pyplot as plt
import mysql.connector
import pandas as pd


conexion = mysql.connector.connect(
    host=os.getenv("DOLI_DB_HOST", "mariadb"),
    port=3306,
    user=os.getenv("DOLI_DB_USER", "dolibarr"),
    password=os.environ["DOLI_DB_PASSWORD"],
    database=os.getenv("DOLI_DB_NAME", "dolidb"),
)

consulta = """
SELECT
    p.label AS producto,
    a.status,
    COUNT(*) AS unidades
FROM llx_rr_renting_asset a
JOIN llx_product p
    ON p.rowid = a.fk_product
    AND p.entity = a.entity
WHERE a.entity = 1
GROUP BY p.label, a.status
"""

df = pd.read_sql(consulta, conexion)

resultado = df.pivot(
    index="producto", columns="status", values="unidades"
).fillna(0)

# 1. Renombrar las columnas DEL DATAFRAME PRIMERO
resultado = resultado.rename(
    columns={
        "available": "Disponible",
        "out": "Entregado",
        "repair": "Reparación",
        "review": "Revisión",
        "sale": "Destinado a venta",
    }
)

# 2. Definir mapa de colores
colores_status = {
    "Disponible": "#65dbc3",
    "Entregado": "#70b6ed",
    "Reparación": "#f18b99",
    "Revisión": "#efbb69",
    "Destinado a venta": "#91a6b9",
}

# 3. Crear figura y graficar UNA SOLA VEZ
fig, ax = plt.subplots(figsize=(12, 6))
resultado.plot(kind="barh", stacked=True, ax=ax, color=colores_status)

plt.title("Capacidad de la flota")
plt.xlabel("Cantidad de equipos")
plt.ylabel("")

ax.invert_yaxis()


plt.legend(title="Estado", bbox_to_anchor=(1.02, 1), loc="upper left")

save_chart("/var/www/html/custom/reportes/python/graficos/capacidad_flota.png", bbox_inches="tight")

conexion.close()