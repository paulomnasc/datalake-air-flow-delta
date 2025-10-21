FROM apache/airflow:2.9.1-python3.10

USER airflow

COPY entrypoint.sh /entrypoint.sh
#RUN chmod +x /entrypoint.sh

# Define a URL de constraints dentro do próprio RUN
RUN export CONSTRAINT_URL="https://raw.githubusercontent.com/apache/airflow/constraints-2.9.1/constraints-3.10.txt" && \
    pip install --no-cache-dir \
        apache-airflow-providers-apache-spark \
        pyspark \
        minio \
        --constraint "${CONSTRAINT_URL}"

ENTRYPOINT ["/entrypoint.sh"]
CMD ["airflow", "webserver"]