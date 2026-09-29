import warnings
warnings.filterwarnings("ignore")

import mysql.connector
import pandas as pd
import matplotlib.pyplot as plt

conexion = mysql.connector.connect(
    host="mariadb",
    port=3306,
    user="dolibarr",
    password="123",
    database="dolidb"
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
plt.savefig("/var/www/html/custom/reportes/python/graficos/incidencias_pendientes.png", bbox_inches="tight")

conexion.close()