FROM hyperf/hyperf:8.2-alpine-v3.19-swoole

RUN set -ex && \
    apk add --no-cache git bash zip unzip curl && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer --version

WORKDIR /app
COPY . .
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9501
ENTRYPOINT ["/entrypoint.sh"]
