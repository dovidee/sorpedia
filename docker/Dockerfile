FROM php:8.4-apache
RUN docker-php-ext-install pdo_mysql \
 && sed -ri 's#/var/www/html#/var/www/app/public#g' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/app/public>\n  AllowOverride None\n  Require all granted\n  FallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-enabled/sorpedia.conf \
 && printf 'output_buffering=Off\nzlib.output_compression=Off\nmax_execution_time=300\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/sorpedia.ini \
 && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-enabled/zz-hardening.conf
WORKDIR /var/www/app
COPY docker/entrypoint.sh /usr/local/bin/sorpedia-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/sorpedia-entrypoint && chmod +x /usr/local/bin/sorpedia-entrypoint
ENTRYPOINT ["sorpedia-entrypoint"]
CMD ["apache2-foreground"]
