import warnings
warnings.filterwarnings("ignore")

import matplotlib.pyplot as plt
import mysql.connector
import pandas as pd


conexion = mysql.connector.connect(
    host="mariadb",
    port=3306,
    user="dolibarr",
    password="123",
    database="dolidb",
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
    "Disponible": "#2ecc71",
    "Entregado": "#3498db",
    "Reparación": "#e74c3c",
    "Revisión": "#f39c12",
    "Destinado a venta": "#7f7e80",
}

# 3. Crear figura y graficar UNA SOLA VEZ
fig, ax = plt.subplots(figsize=(12, 6))
resultado.plot(kind="bar", stacked=True, ax=ax, color=colores_status)

plt.title("Capacidad de la flota")
plt.xlabel("Producto")
plt.ylabel("Cantidad de equipos")

plt.xticks(rotation=45, ha="right")
plt.yticks(range(0, int(resultado.sum(axis=1).max()) + 1, 1))

plt.legend(title="Estado", bbox_to_anchor=(1.02, 1), loc="upper left")

plt.savefig("/var/www/html/custom/reportes/python/graficos/capacidad_flota.png", bbox_inches="tight")

conexion.close()