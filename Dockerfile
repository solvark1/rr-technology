FROM dolibarr/dolibarr:24.0.0

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        python3 \
        python3-pip && \
    a2enmod alias && \
    (a2disconf javascript-common || true) && \
    apt-get purge -y --auto-remove && \
    rm -rf /var/lib/apt/lists/*

RUN pip3 install --break-system-packages --no-cache-dir \
    pandas \
    matplotlib \
    mysql-connector-python

# Habilitar shell_exec (y solo esa función) sin tocar el php.ini principal,
# para que sobreviva a la regeneración de config que hace docker-run.sh
RUN echo "disable_functions = exec,passthru,proc_open,proc_close,popen,system" \
    > /usr/local/etc/php/conf.d/zz-enable-shell-exec.ini