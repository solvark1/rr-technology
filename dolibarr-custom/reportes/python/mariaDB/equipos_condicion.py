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
    item_condition AS condicion,
    COUNT(*) AS cantidad
FROM llx_rr_renting_asset
WHERE entity = 1
GROUP BY item_condition
ORDER BY cantidad DESC
"""

df = pd.read_sql(consulta, conexion)

print(df)

# Gráfico
plt.figure(figsize=(8, 5))

plt.bar(
    df["condicion"],
    df["cantidad"]
)

plt.title("Equipos por condición")
plt.xlabel("Condición del equipo")
plt.ylabel("Cantidad de equipos")

plt.xticks(rotation=45)
plt.tight_layout()
plt.show()

conexion.close()