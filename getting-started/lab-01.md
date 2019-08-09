# Getting Started

## Prerequisites
- Have Docker installed and running on your machine.

## Lab

One of the most challenging things about building images is keeping the image size down. Each instruction in the 
Dockerfile adds a layer to the image, and you need to remember to clean up any artifacts you don’t need before moving on
to the next layer. To write a really efficient Dockerfile, you have traditionally needed to employ shell tricks and 
other logic to keep the layers as small as possible and to ensure that each layer has the artifacts it needs from the
previous layer and nothing else. With multi-stage builds, you use multiple FROM statements in your Dockerfile. Each FROM
instruction can use a different base, and each of them begins a new stage of the build. You can selectively copy 
artifacts from one stage to another, leaving behind everything you don’t want in the final image

For this lab, we are going to run a multistage build for a sample PHP application. We will have a container running PHP,
a container running NGINX and as part of the build process, we will install our dependencies separate to our PHP image
and copy the dependencies in.

First create an empty directory for this lab session and download the sample PHP app from Github into this directory.
Now add a single file in there called "Dockerfile", this is going to be the file you responsible for building your 
Docker images.

Next, open your favourite IDE/text editor and open the Dockerfile file.

As we will be doing a multistage build in this dockerfile, lets start with the stage that will build our Nginx server
image.

Add the following to your Dockerfile:

`FROM nginx:1.17-alpine as nginx`\
`ARG uid=82`\
`COPY ./public/img /var/app/public/img`\
`COPY ./public/favicon.ico /var/app/public/favicon.ico`\
`COPY ./nginx/nginx.conf /etc/nginx/nginx.conf`\
`COPY ./nginx/site.template /etc/nginx/conf.d/site.template`\
`RUN set -eux; \`\
`  adduser -u ${uid} -D -S -G www-data www-data; \`\
`  touch /var/run/nginx.pid; \`\
`  chown -R www-data:www-data /var/run/nginx.pid; \`\
`  chown -R www-data:www-data /var/cache/nginx; \`\
`  chown -R www-data:www-data /etc/nginx; \`\
`  chown -R www-data:www-data /var/log/nginx; \`\
`  chown -R www-data:www-data /var/app;`\
`USER www-data`\
`WORKDIR /var/app/public`\
`ENV PHP_HOST=host.docker.internal:9000`\
`ENV NGINX_TIMEOUT=60s`\
`ENV NGINX_FASTCGI_READ_TIMEOUT=60`\
`ENTRYPOINT ["/bin/sh", "-c"]`\
`CMD ["envsubst '${PHP_HOST},${NGINX_TIMEOUT},${NGINX_FASTCGI_READ_TIMEOUT}' < /etc/nginx/conf.d/site.template > /etc/nginx/conf.d/default.conf; exec nginx -g 'daemon off;'"]`\
`EXPOSE 8080`

This is everything you'll need for your Nginx image. What this does is pulls the Nginx alpine image from Dockerhub
and names this stage "nginx". It will set the user ID to www-data and copies over all the static files for the sample
project. Note, it doesn't copy any of the PHP files, these will be handled later. It also copies over the Nginx config
files and sets the ownership of the files to the www-data user. We then set the active user of the container to www-data
as we don't want to run as root for security reasons. We set the working directory to /var/app/public so when the
container runs, this is the directory it will default to. We then set our environment variables for Nginx including our
PHP host. As we will be running these containers locally for the lab, we have set the URL to `host.docker.internal`
which resolves to the internal IP address used by the host so our containers can talk to each other. We then set an
entry point which is what runs when the container runs, and then we pass a command that will be executed by what
we specified for the entrypoint. This command sets out config variables and starts nginx. Lastly we expose port 8080
as this is the port we will be using to access nginx.

The next stage will be to install all of the dependencies for our sample PHP app. For this we will build an image that
has composer installed, as our sample app uses composer dependencies, we will then copy over the composer.json file and
the composer.lock file, install the dependencies in the image which we can then use later.

Add the following to your Dockerfile:

`FROM composer:1.8 as installer`\
`WORKDIR /var/app`\
`COPY ./composer.json composer.json`\
`COPY ./composer.lock composer.lock`\
`RUN composer install`\

We are using the composer image from Dockerhub version locked to 1.8 and we're naming the stage "installer". We then set
the working directory to /var/app and then we copy in our compose.json file and composer.lock file. Then we run the
command "composer install" to install all of our dependencies.

For the last stage, we are going to build the PHP image which will contain our PHP files for our sample app. Nginx will 
connect to the PHP container to serve the PHP files whereas any static files will be served by the Nginx container. This
image will also copy in the files from our installer stage to get the dependencies for the app.

Add the following to your Dockerfile:

`FROM php:7.3-fpm-alpine as php`\
`ARG uid=82`\
`ARG gid=82`\
`RUN set -eux; \`\
`  mkdir -p /var/app; \`\
`  chown -R www-data:www-data /var/app`\
`COPY ./php/php-fpm.conf /usr/local/etc/php-fpm.conf`\
`COPY ./php/www.conf /usr/local/etc/php-fpm.d/www.conf`\
`COPY ./php/php.ini $PHP_INI_DIR/php.ini`\
`WORKDIR /var/app/`\
`COPY --chown=www-data:www-data public/index.php public/index.php`\
`COPY --chown=www-data:www-data app app/`\
`COPY --from=installer --chown=www-data:www-data /var/app/vendor vendor/`\
`USER www-data`\
`EXPOSE 9000`

We're pulling the PHP image from Dockerhub and we're naming this stage "php". We set the user ID and the group ID to
that of the user www-data. We then create our /var/app directory and change the owner of this directory to www-data. We
then copy in our PHP configuration files and our PHP ini file. We set the working directory to /var/app and we then copy
our index.php file, app directory and all of our vendor files from the previous stage. All while changing the owner of
these files to www-data. We then set the user of the container to www-data and expose port 9000 which is the port Nginx
will connect to for fetching the PHP files.

Our Dockerfile is now complete, we have everything in place to build our Nginx web server and our PHP web application
and for them to effectively work together to serve content. To build our images is very straight forward, as we have 
named the stages, we can reference them when we are building the images. To build our nginx image, in the command line
(ensuring you're in the same directory as your Dockerfile), simply run:
`docker build -t nginx --target nginx .`
This will tag the image (-t) with the name nginx and the `--target` parameter tells docker to use the nginx stage from
our Dockerfile. The full stop at the end of the command tells Docker to look in the current directory for our Dockerfile.
Our Nginx image is now built and ready to be run. Before we do that, build the PHP image using the following command:
`docker build -t php --target php .` this will tag the image as php and tells docker to use the php stage of our
Dockerfile.

Now we have both of our images, run `docker images` to view our images. You should see both our Nginx image and our PHP
image. Now we want to run them. To run the PHP image, simply run the following command:
`docker run --name php -p 9000:9000 php`
This will name the container (`--name`) php and maps the ports (`-p`) 9000 on our host to port 9000 of the container, 
meaning any traffic that hits 9000 on our host will be routed to the container. Port mappings work as host:container. 
You should see:
`NOTICE: fpm is running, pid 1`\
`NOTICE: ready to handle connections`

This means our PHP container is running and is ready to handle connections! So now it's time to run our Nginx webserver.
Open a new command window and run:
`docker run --name nginx -p 8080:8080 nginx`
Again this is going to name the container "nginx" and will map ports 8080 of our host to the container (as Nginx is
running on port 8080 in the container). You'll notice that nothing comes up when we run that command, however if you now
open your web browser and navigate to `localhost:8080`, you will see our sample PHP app! And if you go back to the Nginx
command prompt, you'll see our network request! And that's it, we have a Nginx web server running in its own container
which connects to PHP running in another container to serve our PHP web application.













