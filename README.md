# Composer Docker POC

This repository holds a proof of concept as to how you can use one single
repository as a full build, test and deploy system. It is focused on being
light, fast and able to be setup without any additional configuraiton. 

## Concept introduction

Phase one of the concept is to ensure the architecture of the controller
components (git, composer and docker-compose) always result in a functional
project.

- `git checkout` **=>** `composer install` **=>** `docker-compose up -d`

This action should always result in a functional project regardless of which
environment it was executed for. The master branch should always be fully built.
This branch/codebase/environment will act as the baseline on which people will
base their development, testing or deployment. When in development mode you can
always simulate testing or deployment in the exact way it will be deployed on
production.

### Development environment
Anyone that is given write access should automatically receive any and all tools
that they need to perform development with. In this example we will use a
Cloud9 local editor that will be automatically connected to the environment that
the git branch is running on To accomplish this we need to provide some data:
- sanitized database of production
- 



## Host binary requirements

### 1. Docker
> The endgoal is to have docker as the only requirement on the host system. To
accomplish this the requirements below have to be provided by docker itself. If
this goal is reached any and all incompatibilities of developers trying to use
their own development environment are abolished. The quote "It works on my
machine." will be a thing of the past.

### 2. Docker Compose
> This requirement can be eventually dropped if it is made compatible with Docker
Stack. Dropping this requirement is being postponed because I do not have
sufficient knowledge yet on the latest version of Docker that provides the Docker
Stack functionality which does the same and more natively as what Docker Compose
brings to the table.

### 3. Composer
> <details><summary>Composer is the main component for building the codebase(s).
> The host system should provide it. But the ideal situation would be for the
> project to define composer as a service in its docker-compose.yml file and the
> host system will executed composer with the version defined in the
> docker-compose.yml file if any is present in the working directory. This
> approach is only possible if the codebase is already on the host system. So
> probably we would have to implement a bash function that checks for the
> presence of a docker-compose.yml service and fallback to its own
> docker-compose service.</summary>
> 
> ```shell
> #!/bin/bash
> alias composer="docker-compose exec composer" 
> ```
> </details>

### 4. Git
> <details><summary>Same concept as Composer should be pursued. By letting
> docker handle the Git requirement any project will be able to select their own
> version of Git, which plugins they wish to use and what hooks they wish to
> execute. This approach is only possible if the codebase is already on the host
> system. So probably we would have to implement a bash function that checks for
> the presence of a docker-compose.yml service and fallback to its own
> docker-compose service.</summary>
> 
> ```shell
> #!/bin/bash
> alias git="docker-compose exec git" 
> ```
> </details>

## Project source code requirements

### 1. .gitignore
> <details><summary>The projects for the Docker only architecture are very
> sensitive to folder structure and file changes. To force developers to keep the
> repository clean and organised the .gitignore file applies a full blacklist and
> individual whitelisting.</summary>
> 
> ```shell
> # Blacklist everything.
> /**
> # Whitelist each individual file or folder.
> !/README.md
> !/composer.json
> !/composer.lock
> !/docker-compose.yml
> ```
> </details>


### 2. composer.json
> <details><summary>The composer.json file is responsable for generating all
> codebases. The component that will provide this task is
> verbruggenalex/composer-project-builder-plugin. To keep the project footprint
> as light as possible the following architecture is set up.</summary>
> 
> ```javascript
> {
>     "require": {
>         "drush/drush": "8.*",
>         "drupal/drupal": "~7.0",
>         "verbruggenalex/composer-project-builder-plugin": "dev-master"
>     }
> }
> ```
> </details>

### 3. docker-compose.yml
> <details><summary>The docker-compose.yml file has to contain all service
> required for the project to run. Extra services can be provided as helper
> services that developers can enable themselves.</summary>
> 
> ```yaml
> version: '3'
> services:
> 
>   # Images should not contain non dependant components inside like phpmyadmin.
>   # It's better to move it to its own dedicated service so developers can save
>   # diskspace if they are not planning to use the service.
>   web:
>     image: fpfis/php71-dev-7
>     environment:
>       - DOCUMENT_ROOT=${PWD}/build/master/web
>     working_dir: ${PWD}/build/master/web
>     volumes:
>       - ${PWD}:${PWD}
>     links:
>       - mysql:mysql
>     labels:
>       - 'traefik.backend=web'
>       - 'traefik.port=8080'
>       - 'traefik.frontend.rule=Host:${PROJECT_BASE_URL:-dev.local}'
> 
>   # All variables should be providing a default value. This allows for the
>   # same docker-compose.yml file to be used for development, testing and
>   # deployment.
>   mysql:
>     image: mysql:5.7
>     restart: always
>     environment:
>       - MYSQL_DATABASE=${MYSQL_DATABASE:-database}
>       - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-password}
>       - MYSQL_USER=${MYSQL_USER:-root}
>       - MYSQL_PASSWORD=${MYSQL_PASSWORD:-password}
>     volumes:
>       - "./build/master/data/db/mysql:/var/lib/mysql"
> 
>   # Cloud9 needs to be patched to allow it to connect the shell to the service
>   # that is running the project. In this case the web service. Currently we
>   # mount docker binaries and socket to allow the native Cloud9 shell to access
>   # services within the project.
>   cloud9:
>     image: eeacms/cloud9
>     environment:
>       - C9_WORKSPACE=${PWD}
>     volumes:
>       - ${PWD}:${PWD}
>       - /usr/bin/docker:/usr/bin/docker
>       - /usr/local/bin/docker-compose:/usr/local/bin/docker-compose
>       - /var/run/docker.sock:/var/run/docker.sock
>     links:
>       - web:web
>     labels:
>       - 'traefik.backend=cloud9'
>       - 'traefik.port=8080'
>       - 'traefik.frontend.rule=Host:cloud9.${PROJECT_BASE_URL:-dev.local}'
> 
>   # PHPMyAdmin is added as an example service that developers can use who do
>   # not have knowledge in using MySQL CLI commands to perform their tasks.
>   pma:
>     image: phpmyadmin/phpmyadmin
>     environment:
>       PMA_HOST: mysql
>       PMA_USER: ${MYSQL_USER:-root}
>       PMA_PASSWORD: ${MYSQL_ROOT_PASSWORD:-password}
>       PHP_UPLOAD_MAX_FILESIZE: 1G
>       PHP_MAX_INPUT_VARS: 1G
>     labels:
>       - 'traefik.backend=pma'
>       - 'traefik.port=80'
>       - 'traefik.frontend.rule=Host:pma.${PROJECT_BASE_URL:-dev.local}'
> 
>   # Services are exposed to a certain domain by traefik. This allows
>   # developers to spawn a project on a domain and easily know where to access
>   # their services.
>   traefik:
>     image: traefik
>     command: -c /dev/null --web --docker --logLevel=INFO
>     ports:
>       - '8000:80'
>       - '8080:8080' # Dashboard
>     volumes:
>       - /var/run/docker.sock:/var/run/docker.sock
> ```
> </details>

## Project workflow

### 1. Git
> <details><summary>Git is the initial starting point for any codebase. So we
> want Git to be the main controller to help the developers build their
> codebase. Composer will be responsible for building the codebase. So a simple
> Git hook that calls <code>composer install</code> whenever we change branch
> will immediately setup a new build for us. Click to see example of a <code>
> post-checkout</code> Git hook.</summary>
>
> ```shell
> #!/bin/bash
> [ -f composer.json ] && composer install
> ```
> </details>

### 2. Composer
> <details><summary>Composer is the secondary controller. Once we have a
> codebase available we require an environment to run the project on. The
> Composer hooks can run <code>docker-compose up -d</code> for us and we will
> have immediate access to our development environment.</summary>
>
> ```javascript
>     "scripts": {
>        "post-install-cmd": [
>            "docker-compose up -d"
>        ]
>    }
> ```
> </details>