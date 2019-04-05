# Prooph Event Engine

[![Build Status](https://travis-ci.org/event-engine/php-engine.svg?branch=master)](https://travis-ci.org/event-engine/php-engine)
[![Coverage Status](https://coveralls.io/repos/github/event-engine/php-engine/badge.svg?branch=master)](https://coveralls.io/github/event-engine/php-engine?branch=master)
[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/prooph/improoph)

**The world's only CQRS / ES framework that lets you pick your Flavour**


## Intro

Event Engine is a CQRS / EventSourcing framework for PHP to help you rapidly develop event sourced applications, while providing a path to refactor towards a richer domain model as needed. Customize Event Engine with Flavours. Choose between different programming styles.

## Choose Your Flavour

![Choose Your Flavour](https://event-engine.io/img/Choose_Flavour_no_h.png)

## Event Sourcing Engine

![Event Sourcing Engine](https://event-engine.io/api/img/Aggregate_Lifecycle.png)

## Installation

Head over to the [skeleton](https://github.com/event-engine/php-engine-skeleton)!

## Tutorial

[![Tutorial](https://event-engine.io/img/tutorial_screen.png)](https://event-engine.io/tutorial/)

**[GET STARTED](https://event-engine.io/tutorial/)**

## Documentation

Source of the docs is managed in a separate [repo](https://github.com/event-engine/docs)

## Run Tests

Some tests require existence of prooph/event-store tests which are usually not installed due to `.gitattributes` excluding them.
Unfortunately, composer does not offer a reinstall command so we have to remove `prooph/event-store` package from the vendor folder
manually and install it again using `--prefer-source` flag.

```bash
$ rm -rf vendor/prooph/event-store
$ docker run --rm -it -v $(pwd):/app --user="$(id -u):$(id -g)" prooph/composer:7.2 install --prefer-source
```


## Powered by prooph software

[![prooph software](https://github.com/codeliner/php-ddd-cargo-sample/blob/master/docs/assets/prooph-software-logo.png)](http://prooph.de)

Event Engine is maintained by the [prooph software team](http://prooph-software.de/). The source code of Event Machine 
is open sourced along with an API documentation and a [Getting Started Tutorial](https://event-engine.io/tutorial/). Prooph software offers commercial support and workshops
for Event Engine as well as for the [prooph components](http://getprooph.org/).

If you are interested in this offer or need project support please [get in touch](http://getprooph.org/#get-in-touch)