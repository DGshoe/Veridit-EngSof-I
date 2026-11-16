# Production web server: Nginx with the public/ assets baked in (no code volume).
# Build context: backend/identity.
FROM nginx:1.30-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
