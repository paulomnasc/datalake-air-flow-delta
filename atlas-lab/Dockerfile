FROM eclipse-temurin:8-jre-jammy

# Install dependencies
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    bash curl wget python3 python3-pip && \
    rm -rf /var/lib/apt/lists/*

# Download and install Atlas 2.3.0
WORKDIR /opt
RUN wget https://archive.apache.org/dist/atlas/2.3.0/apache-atlas-2.3.0-bin.tar.gz && \
    tar -xzf apache-atlas-2.3.0-bin.tar.gz && \
    mv apache-atlas-2.3.0 atlas && \
    rm apache-atlas-2.3.0-bin.tar.gz

WORKDIR /opt/atlas

# Configure Atlas for standalone mode
RUN echo "atlas.graph.storage.backend=berkeleyje" > conf/atlas-application.properties && \
    echo "atlas.graph.storage.directory=\${sys:atlas.home}/data/berkley" >> conf/atlas-application.properties && \
    echo "atlas.notification.embedded=true" >> conf/atlas-application.properties && \
    echo "atlas.kafka.data=\${sys:atlas.home}/data/kafka" >> conf/atlas-application.properties && \
    echo "atlas.graph.index.search.backend=solr" >> conf/atlas-application.properties && \
    echo "atlas.graph.index.search.solr.mode=embedded" >> conf/atlas-application.properties && \
    echo "atlas.graph.index.search.directory=\${sys:atlas.home}/data/solr" >> conf/atlas-application.properties && \
    echo "atlas.authentication.method.file=true" >> conf/atlas-application.properties && \
    echo "atlas.server.http.port=21000" >> conf/atlas-application.properties

# Create admin user
RUN echo "admin=ADMIN::admin" > conf/users-credentials.properties

# Create python symlink
RUN ln -s /usr/bin/python3 /usr/bin/python

# Expose port
EXPOSE 21000

# Start Atlas
CMD ["bin/atlas_start.py"]