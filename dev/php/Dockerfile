ARG PHP_VERSION=""
FROM php:${PHP_VERSION:+${PHP_VERSION}-}apache

# Use php development settings
#RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"


# php-zip support and database drivers (MySQL, PostgreSQL, SQL Server)
RUN set -eux; \
	apt-get update; \
	apt-get install -y \
		zlib1g-dev \
		libzip-dev \
		unzip \
		libpq-dev \
		gnupg2 \
		wget \
		curl \
		apt-transport-https; \
	docker-php-ext-install zip; \
	docker-php-ext-install pdo_mysql; \
	docker-php-ext-install pdo_pgsql

# Install Microsoft SQL Server drivers
# Note: SQL Server support nécessite architecture x86_64, pas disponible sur ARM64
# Pour support ARM64, utiliser FreeTDS comme alternative
RUN if [ "$(uname -m)" = "x86_64" ]; then \
		set -eux; \
		# Add Microsoft repository
		curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -; \
		curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list; \
		apt-get update; \
		# Install SQL Server ODBC driver and tools
		ACCEPT_EULA=Y apt-get install -y \
			msodbcsql18 \
			mssql-tools18 \
			unixodbc-dev; \
		# Install PHP SQL Server extensions
		pecl install sqlsrv pdo_sqlsrv; \
		docker-php-ext-enable sqlsrv pdo_sqlsrv; \
		# Clean up
		apt-get clean; \
		rm -rf /var/lib/apt/lists/*; \
	else \
		echo "SQL Server drivers not available for $(uname -m) architecture"; \
		echo "For ARM64, SQL Server connections will use PDO_ODBC with FreeTDS"; \
		apt-get update; \
		apt-get install -y freetds-dev freetds-bin unixodbc unixodbc-dev; \
		docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr; \
		docker-php-ext-install pdo_odbc; \
		apt-get clean; \
		rm -rf /var/lib/apt/lists/*; \
	fi

# enable mod_rewrite and allow override from .htacess files
RUN set -eux; \
	a2enmod rewrite; \
	sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# uncomment below to add modsecurity
#RUN set -eux; \
#	apt-get install -y modsecurity-crs libapache2-mod-security2; \ 
#	mv /etc/modsecurity/modsecurity.conf-recommended /etc/modsecurity/modsecurity.conf; \
#        sed -i -e 's/SecRuleEngine DetectionOnly/SecRuleEngine On/g' /etc/modsecurity/modsecurity.conf; \
#	echo "SecAction \"id:900200, phase:1, nolog, pass, t:none, setvar:'tx.allowed_methods=GET HEAD POST OPTIONS PUT DELETE'\"" > /etc/modsecurity/allow_put_delete.conf

