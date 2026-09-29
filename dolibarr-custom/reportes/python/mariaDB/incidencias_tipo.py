import os
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from rr_chart_style import save_chart

import warnings
warnings.filterwarnings("ignore")
import mysql.connector
import pandas as pd
import matplotlib.pyplot as plt

# Conexión a MariaDB
conexion = mysql.connector.connect(
    host=os.getenv("DOLI_DB_HOST", "mariadb"),
    user=os.getenv("DOLI_DB_USER", "dolibarr"),
    password=os.environ["DOLI_DB_PASSWORD"],
    database=os.getenv("DOLI_DB_NAME", "dolidb")
)

consulta = """
SELECT
    status AS estado,
    COUNT(*) AS cantidad
FROM llx_rr_renting_incident
WHERE entity = 1
GROUP BY status
ORDER BY cantidad DESC
"""

df = pd.read_sql(consulta, conexion)

# Gráfico
plt.figure(figsize=(8, 5))

plt.bar(
    df["estado"],
    df["cantidad"]
)

plt.title("Incidencias por estado")
plt.xlabel("Estado")
plt.ylabel("Cantidad de incidencias")

plt.tight_layout()
save_chart("/var/www/html/custom/reportes/python/graficos/incidencias_tipo.png", bbox_inches="tight")

conexion.close()