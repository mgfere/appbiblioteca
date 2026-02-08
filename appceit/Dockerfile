FROM composer:2.7 as vendor
WORKDIR /app
COPY composer.json composer.json
COPY composer.lock composer.lock
RUN composer install \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --no-dev \
    --prefer-dist \
    --ignore-platform-req=ext-gd

FROM php:8.2-apache-bullseye

# Instalar Java 17 LTS
RUN apt-get update && \
    apt-get install -y \
    openjdk-17-jdk \
    && rm -rf /var/lib/apt/lists/*

# Configurar JAVA_HOME para Java 17
ENV JAVA_HOME=/usr/lib/jvm/java-17-openjdk-amd64
ENV PATH=$PATH:$JAVA_HOME/bin

# Verificar instalación de Java 17
RUN java -version

# CONFIGURACIÓN PARA SQL SERVER - DEBIAN 11 (Bullseye)

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    gnupg2 curl apt-transport-https ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# INSTALAR ODBC DRIVER 17 para SQL Server
# Nota: Usamos repositorios de Debian 11 para coincidir con la imagen base
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql17 unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# CONFIGURAR OPENSSL PARA SQL SERVER 2012
RUN printf '[openssl_init]\nssl_conf = ssl_sect\n\n[ssl_sect]\nsystem_default = system_default_sect\n\n[system_default_sect]\nMinProtocol = TLSv1.0\nCipherString = DEFAULT@SECLEVEL=1\n' > /etc/ssl/openssl_custom.cnf \
    && echo 'export OPENSSL_CONF=/etc/ssl/openssl_custom.cnf' >> /etc/apache2/envvars

# --- AGREGADO RECOMENDADO ---
# Esto asegura que los comandos de consola (CLI) también usen la configuración SSL antigua
ENV OPENSSL_CONF=/etc/ssl/openssl_custom.cnf
# ----------------------------

# INSTALAR SQLSRV Y PDO_SQLSRV
RUN pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv


# Instalar otras extensiones PHP (gd, mysql, zip, etc.) con el instalador mlocati (muy eficiente)
RUN curl -sSLf \
    -o /usr/local/bin/install-php-extensions \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd pdo_mysql mysqli zip fileinfo curl gettext mbstring exif

COPY php.ini /usr/local/etc/php/php.ini
WORKDIR /var/www/html
COPY --from=vendor app/vendor/ ./vendor/
COPY . .

EXPOSE 80