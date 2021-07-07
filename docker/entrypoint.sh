#!/bin/bash
set -e

# prepare overlay directories
# https://stackoverflow.com/a/33275168/172068
mkdir -p /overlay/storage/{dokuwiki,work}
mount -t overlay \
      -o lowerdir=/overlay/original,upperdir=/overlay/storage/dokuwiki,workdir=/overlay/storage/work \
         overlay /var/www/html

exec docker-php-entrypoint apache2-foreground
