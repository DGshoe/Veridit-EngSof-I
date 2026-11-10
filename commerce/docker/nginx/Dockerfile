# Production web server for Commerce: Nginx with public/ baked in (no code volume).
# Build context: commerce/
FROM nginx:1.30-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
