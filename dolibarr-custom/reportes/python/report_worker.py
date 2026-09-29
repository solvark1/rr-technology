"""Fixed report jobs, run by Docker as www-data; no shell commands from PHP."""
import json
import os
import subprocess
import sys
import time
from pathlib import Path

ROOT=Path(__file__).resolve().parent
OUT=ROOT / "graficos"
JOBS=["capacidad_flota.py", "incidencias_pendientes.py", "top_alquilados.py", "mariaDB/equipos_condicion.py", "mariaDB/incidencias_tipo.py", "mongo/reportes_mongo.py"]

def state(payload):
    target=OUT / "report-status.json"
    temp=OUT / "report-status.tmp"
    temp.write_text(json.dumps(payload),encoding="utf-8")
    os.replace(temp,target)

def generate():
    request=OUT / ".refresh-request"
    request.unlink(missing_ok=True)
    state({"running":True, "updated":0, "errors":[]})
    errors=[]
    for job in JOBS:
        try:
            result=subprocess.run([sys.executable,str(ROOT/job)],stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=45)
            if result.returncode: errors.append(job)
        except (OSError,subprocess.TimeoutExpired): errors.append(job)
    state({"running":False,"updated":int(time.time()),"errors":errors})
    print("[RR-REPORTES] Generation finished; failed jobs: " + (", ".join(errors) or "none"),flush=True)

if __name__=="__main__":
    OUT.mkdir(parents=True,exist_ok=True)
    while True:
        generate()
        if "--once" in sys.argv: break
        deadline=time.monotonic()+300
        while time.monotonic()<deadline and not (OUT/".refresh-request").exists(): time.sleep(2)
