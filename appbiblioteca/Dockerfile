FROM php:8.2-apache-bullseye

COPY . /var/www/html
COPY php.ini /usr/local/etc/php/php.ini
WORKDIR /var/www/html
EXPOSE 80

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    apt-transport-https \
    ca-certificates \
    libzip-dev unzip \
    && rm -rf /var/lib/apt/lists/*

# INSTALAR ODBC DRIVER 17 (Configurado para Debian 11 Bullseye)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/11/prod bullseye main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql17 unixodbc-dev \
    && rm -rf /var/lib/apt/lists/*

# CONFIGURAR OPENSSL (Crucial para SQL Server 2012)
# 1. Creamos el archivo cnf personalizado
RUN printf '[openssl_init]\nssl_conf = ssl_sect\n\n[ssl_sect]\nsystem_default = system_default_sect\n\n[system_default_sect]\nMinProtocol = TLSv1.0\nCipherString = DEFAULT@SECLEVEL=1\n' > /etc/ssl/openssl_custom.cnf

# 2. Hacemos que Apache lo vea
RUN echo 'export OPENSSL_CONF=/etc/ssl/openssl_custom.cnf' >> /etc/apache2/envvars

# 3. IMPORTANTE: Hacemos que PHP CLI también lo vea (para comandos artisan o scripts)
ENV OPENSSL_CONF=/etc/ssl/openssl_custom.cnf

# INSTALAR EXTENSIONES PHP
RUN pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# INSTALAR PDO_MYSQL
RUN docker-php-ext-install pdo_mysql