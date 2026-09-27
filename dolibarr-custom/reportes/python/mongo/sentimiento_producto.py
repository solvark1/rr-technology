import pandas as pd
import matplotlib.pyplot as plt
from matplotlib.ticker import MultipleLocator
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

resultado = pd.crosstab(
    df["producto"],
    df["sentimiento"]
)

print(resultado)

ax = resultado.plot(kind="bar", stacked=True, figsize=(14, 7))
ax.yaxis.set_major_locator(MultipleLocator(50))

plt.title("Sentimiento por producto", pad=18)
plt.xlabel("Producto", labelpad=12)
plt.ylabel("Cantidad de publicaciones", labelpad=12)
plt.xticks(rotation=45, ha="right")
plt.tight_layout()
plt.show()