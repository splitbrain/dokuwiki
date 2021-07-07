# DokuWiki Docker Image

This directory contains an experimental attempt to create an official docker image. It is base on the official [php image](https://hub.docker.com/_/php) with Apache.

## What's special?

There are literally hundreds of DokuWiki images on Docker hub. So what does this one differently?

DokuWiki's file layout is made to encapsulate all of DokuWiki in a single directory. This makes it very portable (just copy it to a different server) but makes it a bit harder to dockerize, because it's not easy to separate the user content (page, media, configuration, plugins, templates) from the source code itself. You can read more in [Running DokuWiki on Docker](https://www.patreon.com/posts/42961375)

This image uses a novel approach to solve the problem: an overlay file system approach. The given volume is mounted as the upper filesystem on top of the orginal DokuWiki directory. This means all files that are written by the Docker container will be stored outside the container, while all unchanged files are provided by the container file system.

This means using this wiki feels 100% like a standard bare metal install, but a clear separation between user created and distribution files is always maintained.

## Building the image

In this directory use the following command to build the image:

    docker build . -t splitbrain/dokuwiki:stable

The default image is based on the last `stable` release. Alternatively you can use any other branch name, like `master`:

    docker build . --build-arg BRANCH=master -t splitbrain/dokuwiki:master

## Running the image

When running the image you need to provide a volume mounted to `/overlay/storage`. Inside this volume two directories will be created: `work` is used by the overlay file system as a scratch directory, `dokuwiki` will contain all your user files. The layout within the `dokuwiki` directory follows exactly the official layout, but it only contains files that are different from the official distribution.

To be able to create the overlay file system, the container needs to be started with the `SYS_ADMIN` capability:

    docker run --name dokuwiki -it -v /tmp/volume/:/overlay/storage --cap-add=SYS_ADMIN -p 8080:80 splitbrain/dokuwiki

## ToDo

* improve documentation above
    * best practices for running the container?
* improve the container
    * currently no special care of UID/GIDs have been taken
    * upgrade the underlying Debian on build
    * optimize build for smaller builds
    * maybe use a smaller base image?
* improve the default configuration
    * provide .htaccess for rewriting
* implement automated build process
    * use github actions to autobuild the container
    * auto deploy to docker hub
