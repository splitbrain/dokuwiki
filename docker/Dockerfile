FROM php:7.4-apache

ARG BRANCH=stable

COPY --chmod=755 ./entrypoint.sh /usr/local/sbin/entrypoint

VOLUME /overlay/storage

# setup DokuWiki
RUN mkdir -p /overlay/original && \
    cd /overlay/original && \
    curl -L http://github.com/splitbrain/dokuwiki/tarball/$BRANCH | \
    tar --strip-components=1 -xzvf - && \
    chown -R www-data:www-data .

EXPOSE 80 443
ENTRYPOINT ["entrypoint"]
