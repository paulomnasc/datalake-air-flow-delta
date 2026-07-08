FROM apache/airflow:2.9.1-python3.10

USER airflow

COPY entrypoint.sh /entrypoint.sh
#RUN chmod +x /entrypoint.sh

# ... (outras linhas)
# Esta linha copia o arquivo do seu host para o contêiner
COPY requirements.txt /requirements.txt 

# Linha Nova: COPIA O ARQUIVO DE CONSTRAINTS DO SEU HOST PARA O CONTÊINER
COPY constraints.txt /constraints.txt


# ... (outras linhas)
# Esta linha instala as dependências
RUN pip install --no-cache-dir -r /requirements.txt --constraint /constraints.txt 
# ...

# Define a URL de constraints dentro do próprio RUN
RUN export CONSTRAINT_URL="https://raw.githubusercontent.com/apache/airflow/constraints-2.9.1/constraints-3.10.txt" && \
    pip install --no-cache-dir \
        apache-airflow-providers-apache-spark \
        apache-airflow-providers-amazon \
        apache-airflow-providers-mysql \
        pyspark \
        minio \
        --constraint "${CONSTRAINT_URL}"

RUN pip install --no-cache-dir playwright==1.61.0 pyee==13.0.1

# Altera o usuário para root
USER root        
RUN apt-get update && apt-get install -y dos2unix && dos2unix /entrypoint.sh
RUN python -m playwright install --with-deps chromium

# Altera o usuário de volta para 'airflow' (usuário não-root recomendado)
USER airflow
# Define o ponto de entrada do contêiner
ENTRYPOINT ["/entrypoint.sh"]
CMD ["airflow", "webserver"]