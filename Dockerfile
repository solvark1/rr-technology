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
    pymongo \
    mysql-connector-python
