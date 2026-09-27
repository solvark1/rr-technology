import mysql.connector
import pandas as pd
import matplotlib.pyplot as plt

# Conexión a MariaDB
conexion = mysql.connector.connect(
    host="localhost",
    port=3306,
    user="dolibarr",
    password="123",
    database="dolidb"
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

print(df)

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
plt.show()

conexion.close()