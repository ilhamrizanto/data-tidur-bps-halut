#!/bin/bash
# web-entrypoint.sh — siapkan folder uploads (volume dapat menimpa layer image)
set -e
mkdir -p /var/www/html/uploads
[ -d /var/www/html/uploads ] && chown -R www-data:www-data /var/www/html/uploads || true
exec apache2-foreground
