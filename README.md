# SofascorePurgatoryBundle

[![Latest Stable Version](https://poser.pugx.org/sofascore/purgatory-bundle/v/stable)](https://packagist.org/packages/sofascore/purgatory-bundle)
[![Build Status](https://github.com/sofascore/purgatory-bundle/workflows/Tests/badge.svg)](https://github.com/sofascore/purgatory-bundle/actions)
[![Code Coverage](https://codecov.io/gh/sofascore/purgatory-bundle/graph/badge.svg?token=HWMVLVSTIC)](https://codecov.io/gh/sofascore/purgatory-bundle)
[![License](https://poser.pugx.org/sofascore/purgatory-bundle/license)](https://packagist.org/packages/sofascore/purgatory-bundle)

A Symfony bundle for creating and sending cache purge requests to HTTP cache backends like Varnish.

## Features

* TODO

## Requirements

* [PHP 8.1](http://php.net/releases/8_1_0.php) or greater
* [Symfony 5.4](https://symfony.com/roadmap/5.4) or [Symfony 6.4](https://symfony.com/roadmap/6.4) or greater

## Installation

1. Require the bundle with [Composer](https://getcomposer.org/):

    ```sh
    composer require sofascore/purgatory-bundle
    ```

1. Create the bundle configuration file under `config/packages/purgatory.yaml`. Here is a reference
   configuration file:

    ```yaml
    purgatory:

        # List of files or directories where Purgatory will look for additional purge definitions.
        mapping_paths:        []

        # Route names that match the given regular expressions will be ignored.
        route_ignore_patterns: []

            # Examples:
            # - /^_profiler/
            # - /^_wdt/
        doctrine_middleware:
            enabled:              true

            # Explicitly set the priority of Purgatory's Doctrine middleware.
            priority:             null

        # Explicitly set the priorities of Purgatory's Doctrine event listener.
        doctrine_event_listener_priorities:
            preRemove:            null
            postPersist:          null
            postUpdate:           null

            # This event is not registered when the Doctrine middleware is enabled.
            postFlush:            null
        purger:

            # The ID of a service that implements the "Sofascore\PurgatoryBundle\Purger\PurgerInterface" interface
            name:                 null # Example: symfony

            # The hosts from which URLs should be purged
            hosts:                []

            # The service ID of the HTTP client to use, must be an instance of Symfony's HTTP client
            http_client:          null
        messenger:

            # Set the name of the messenger transport to use
            transport:            null

            # Set the name of the messenger bus to use
            bus:                  null

            # Set the number of urls to dispatch per message
            batch_size:           null

        # Enables the data collector and profiler panel if the profiler is enabled.
        profiler_integration: true
    ```

1. Enable the bundle in `config/bundles.php` by adding it to the array:

    ```php
    Sofascore\PurgatoryBundle\PurgatoryBundle::class => ['all' => true],
    ```

## Usage

TODO

## Versioning

This project adheres to [Semantic Versioning 2.0.0](http://semver.org/).

## Reporting issues

Use the [issue tracker](https://github.com/sofascore/purgatory-bundle/issues) to report any issues you might have.

## License

See the [LICENSE](LICENSE) file for license rights and limitations (MIT).
