import pandas as pd
import matplotlib.pyplot as plt
from pymongo import MongoClient

USUARIO = "luisballar"
PASSWORD = "123"
CLUSTER = "cluster0.6vh1buh.mongodb.net"

uri = f"mongodb+srv://{USUARIO}:{PASSWORD}@{CLUSTER}/?appName=Cluster0"

cliente = MongoClient(uri)
db = cliente["R&R_Social"]
coleccion = db["posts"]

datos = list(coleccion.find())
df = pd.json_normalize(datos)

resultado = (
    df.groupby("producto")["metricas.interacciones_totales"]
    .sum()
    .sort_values(ascending=False)
)

print(resultado)

resultado.plot(kind="bar")

plt.title("Interacciones por producto")
plt.xlabel("Producto")
plt.ylabel("Interacciones")
plt.xticks(rotation=45)
plt.tight_layout()
plt.show()