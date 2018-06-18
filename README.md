# Composer Docker POC

This repository holds a proof of concept as to how you can use one single
repository as a full build, test and deploy system. It is focused on being
light, fast and easy to setup without any additional configuration.

## Concept introduction

A project uses git hooks as the controller for docker. A git hook sets up the
environment and executes any commands to setup the project. There should be no
manual commands executed either on local or remote environments. The git hooks
should be on the environment and control what should be done with the project.

- `git checkout` **=>** `docker-compose up -d` **=>** `composer install`

## Host binary requirements

### 1. Git
> <details><summary>Git acts as the main controller for projects. A project can
> be started by cloning it with a template definition defined. In this example
> you should create a file under <code>~/git-hooks/clone/hooks/post-checkout
> </code>:</summary>
>
> ```bash
> #!/bin/sh
>
> # Post checkout hook. Setup environment, run composer install and clone site.
> mkdir web
> docker-compose up -d && \
> docker-compose exec -T web composer install --ansi --no-interaction --no-suggest && \
> docker-compose exec -T web ./vendor/bin/run drupal:site-clone
> ```
>
> After this is done you can run the following command to install the project.
> This will result in a clone of the project for development purposes.
>
> ```bash
> git clone git@github.com:verbruggenalex/composer-docker.git \
>   --template=~/git-hooks/clone/ \
>   -C project-directory \
> ```
>
> </details>

### 2. Docker & Docker Compose
> As shown in the example code above your git hook file is responsible for
> setting up the project. The entire workflow from development to production
> should be encapsulated in git template files.

## Source code requirements

### 1. docker-compose.yml
> <details><summary>The primary file for projects is the <code>
> docker-compose.yml</code> file. This allows us to use the correct environment
> with all required components available to set up the project.</summary>
> 
> ```yaml
> version: '3'
> services:
>   cloud9:
>     image: eeacms/cloud9
>     environment:
>       - C9_WORKSPACE=${PWD}
>     volumes:
>       - ${PWD}:${PWD}
>       - /usr/bin/docker:/usr/bin/docker
>       - /usr/local/bin/docker-compose:/usr/local/bin/docker-compose
>       - /var/run/docker.sock:/var/run/docker.sock
>     labels:
>       - 'traefik.backend=cloud9'
>       - 'traefik.port=8080'
>       - 'traefik.frontend.rule=Host:cloud9.${PROJECT_BASE_URL:-domain.local}'
>   web:
>     image: feature/php71-dev-7
>     environment:
>       - DOCUMENT_ROOT=${PWD}/web
>       - XDEBUG_CONFIG=idekey=cloud9ide remote_connect_back=0 remote_host=172.20.0.1
>       - COMPOSER_PROCESS_TIMEOUT=600
>     working_dir: ${PWD}
>     volumes:
>       - ${PWD}:${PWD}
>     labels:
>       - 'traefik.backend=web'
>       - 'traefik.port=8080'
>       - 'traefik.frontend.rule=Host:${PROJECT_BASE_URL:-domain.local}'
>   mysql:
>     image: mysql:5.7
>     environment:
>       - MYSQL_DATABASE=${MYSQL_DATABASE:-drupal}
>       - MYSQL_USER=${MYSQL_USER:-user}
>       - MYSQL_PASSWORD=${MYSQL_PASSWORD:-password}
>       - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-password}
>     labels:
>       - 'traefik.backend=mysql'
>       - 'traefik.port=3306'
>   phpmyadmin:
>     image: phpmyadmin/phpmyadmin
>     environment:
>       PMA_PORT: 3306
>       PMA_HOST: mysql
>       PMA_USER: ${MYSQL_USER:-root}
>       PMA_PASSWORD: ${MYSQL_ROOT_PASSWORD:-password}
>       PMA_ABSOLUTE_URI: 'http://phpmyadmin.${PROJECT_BASE_URL:-domain.local}'
>       PHP_UPLOAD_MAX_FILESIZE: 1G
>       PHP_MAX_INPUT_VARS: 1G
>     labels:
>       - 'traefik.backend=pma'
>       - 'traefik.frontend.rule=Host:phpmyadmin.${PROJECT_BASE_URL:-domain.local}'
>   solr:
>     image: fpfis/solr5
>     labels:
>       - 'traefik.backend=solr'
>       - 'traefik.port=8983'
>   traefik:
>     image: traefik
>     command: -c /dev/null --web --docker --logLevel=INFO
>     ports:
>       - '80:80'
>       - '8080:8080' # Dashboard
>     volumes:
>       - /var/run/docker.sock:/var/run/docker.sock
> ```
> </details>

### 2. composer.json
> <details><summary>The <code>composer.json</code> file is used as the build
> system for the project. Running composer install will generate a functional
> codebase. The command should always be executed from within the environment
> provided by the <code>docker-compose.yml</code> file. With the following
> command you can set up the projects' codebase: <code>docker-compose exec -T
> web composer install --ansi --no-interaction --no-suggest`</code></summary>
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

### 3. runner.yml
> <details><summary>All actions performed on the codebase should be provided by
> the taskrunner. In this proof of concept we are using the <code>
> openeuropa/task-runner</code> package. Any commands provided by the taskrunner
> should be executed within the environment provided by the <code>
> docker-compose.yml</code> file. With the following command definition declared
> in your <code>runner.yml</code> file, you can clone the project by executing:
> <code>docker-compose exec -T web ./vendor/bin/run drupal:site-clone</code>
> </summary>
> 
> ```yaml
> commands:
>   drupal:site-clone:
>     - "cp -Rf $PWD/${drupal.root}/sites/${drupal.site.sites_subdir}/default.settings.php $PWD/${drupal.root}/sites/${drupal.site.sites_subdir}/settings.php"
>     - "ln -sf $PWD/settings.local.php $PWD/${drupal.root}/sites/${drupal.site.sites_subdir}/settings.local.php"
>     - "mkdir -p ${drupal.root}/sites/${drupal.site.sites_subdir}/files/private_files"
>     - "mkdir -p ${drupal.root}/profiles/${drupal.site.profile}/libraries/mpdf/graph_cache"
>     - "chmod -R 777 ${drupal.root}/sites/${drupal.site.sites_subdir}/files"
>     - "chmod -R 777 ${drupal.root}/profiles/${drupal.site.profile}/libraries/mpdf/graph_cache"
>     - "${runner.bin_dir}/drush -r ${drupal.root}  sql-drop -y"
>     - "while ! mysqladmin ping --user=user -h mysql --password=password --silent; do echo Waiting for mysql; sleep 3; done"
>     - "${runner.bin_dir}/drush -r ${drupal.root} sql-create --target=default -y"
>     - "${runner.bin_dir}/drush -r ${drupal.root} sql-create --target=extra -y"
>     - "${runner.bin_dir}/drush -r ${drupal.root}  sqlc --target=default < ./vendor/project/database/drupal.sql"
> ```
> </details>