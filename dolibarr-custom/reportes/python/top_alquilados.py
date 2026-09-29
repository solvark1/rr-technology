import os
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from rr_chart_style import save_chart

import warnings
warnings.filterwarnings("ignore")

import mysql.connector
import pandas as pd
import matplotlib.pyplot as plt

conexion = mysql.connector.connect(
    host=os.getenv("DOLI_DB_HOST", "mariadb"),
    port=3306,
    user=os.getenv("DOLI_DB_USER", "dolibarr"),
    password=os.environ["DOLI_DB_PASSWORD"],
    database=os.getenv("DOLI_DB_NAME", "dolidb")
)

consulta = """
SELECT
    p.ref AS producto,
    p.label AS nombre_producto,
    COUNT(*) AS veces_alquilado
FROM llx_rr_renting_booking b
JOIN llx_rr_renting_line l
    ON l.fk_booking = b.rowid
JOIN llx_rr_renting_asset a
    ON a.rowid = l.fk_asset
    AND a.entity = b.entity
JOIN llx_product p
    ON p.rowid = a.fk_product
    AND p.entity = b.entity
WHERE b.entity = 1
GROUP BY p.rowid, p.ref, p.label
ORDER BY veces_alquilado DESC
"""

df = pd.read_sql(consulta, conexion)


plt.figure(figsize=(10, 6))

plt.barh(
    df["nombre_producto"],
    df["veces_alquilado"]
)

plt.title("Productos/equipos más alquilados")
plt.xlabel("Cantidad de alquileres")
plt.ylabel("Equipo")

plt.gca().invert_yaxis()

plt.tight_layout()
save_chart("/var/www/html/custom/reportes/python/graficos/top_alquilados.png", bbox_inches="tight")

conexion.close()