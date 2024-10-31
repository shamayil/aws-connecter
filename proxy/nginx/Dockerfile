FROM nginx:1.19.7-alpine

WORKDIR /var/www/html

ADD ./proxy/nginx/default.conf.template /etc/nginx/templates/default.conf.template

COPY . .

EXPOSE 80
