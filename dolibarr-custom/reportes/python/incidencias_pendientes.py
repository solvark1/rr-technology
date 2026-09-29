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
    s.nom AS cliente,
    i.reason AS motivo,
    i.status,
    DATEDIFF(CURDATE(), i.date_creation) AS dias
FROM llx_rr_renting_incident i
JOIN llx_rr_renting_booking b
    ON b.rowid = i.fk_booking
JOIN llx_societe s
    ON s.rowid = b.fk_soc
    AND s.entity = b.entity
WHERE i.entity = 1
AND i.status IN ('open', 'received')
ORDER BY dias DESC
"""

df = pd.read_sql(consulta, conexion)

plt.barh(df["cliente"], df["dias"])

plt.title("Antigüedad de incidencias pendientes")
plt.xlabel("Días esperando")
plt.ylabel("Cliente")
plt.gca().invert_yaxis()

plt.tight_layout()
save_chart("/var/www/html/custom/reportes/python/graficos/incidencias_pendientes.png", bbox_inches="tight")

conexion.close()