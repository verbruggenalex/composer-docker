# GDC (Git-Docker-Composer)

## Concept introduction

This repository holds a proof of concept as to how you can use one single
repository as a full build, test and deploy system with absolute minimal
requirements. It is focused on being light, fast and easy to setup without any
additional configuration.

A project uses git hooks as the controller for docker. A git hook sets up the
environment and executes any commands to setup the project. There should be no
manual commands executed either on local or remote environments. The git hooks
should be on the environment and control what should be done with the project.

- `git checkout` **=>** `docker-compose up -d` **=>** `composer install`

## Host binary requirements

To use this project workflow there are three binary requirements on the host.
The version requirements still have to be lowered if possible as the POC was
set up with the versions defined below:

* [git (>= 2.14.1)](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)
* [docker (>= 17.12.0-ce)](https://docs.docker.com/install/)
* [docker-compose (>= 2.21.2)](https://docs.docker.com/compose/install/#install-compose)

## Source code requirements

To use this project workflow there are three source code requirements:

* [docker-compose.yml](./docker-compose.yml)
* [composer.json](./composer.json)
* [runner.yml](./runner.yml)

## Installation procedure

Git acts as the main controller for projects. A project can be started by cloning it
with a template definition defined. In this example you should create a file under
`~/git-hooks/clone/hooks/post-checkout`. Do not forget to make the hook file
executable.

```bash
#!/bin/sh

# Post checkout hook. Setup environment, run composer install and clone site.
mkdir web && \
docker-compose up -d && \
docker-compose exec -T web composer install --ansi --no-interaction --no-suggest && \
docker-compose exec -T web ./vendor/bin/run drupal:site-clone
```

After this is done you can run the following command to install the project.
This will result in a clone of the project for development purposes.

```bash
git clone git@github.com:verbruggenalex/composer-docker.git --template=~/git-hooks/clone/
```
