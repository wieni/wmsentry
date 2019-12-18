wmsentry
======================

[![Latest Stable Version](https://poser.pugx.org/wieni/wmsentry/v/stable)](https://packagist.org/packages/wieni/wmsentry)
[![Total Downloads](https://poser.pugx.org/wieni/wmsentry/downloads)](https://packagist.org/packages/wieni/wmsentry)
[![License](https://poser.pugx.org/wieni/wmsentry/license)](https://packagist.org/packages/wieni/wmsentry)

> A module for sending errors to Sentry in Drupal 8.

## Why?
- We use [Sentry](https://sentry.io) to monitor our sites and to track
  errors
- We couldn't find an existing (stable) module for integrating Sentry
  with Drupal, using v2 of the Sentry SDK.

## Installation
This module requires PHP 7.2 or higher and uses the Sentry PHP package
([`sentry/sentry`](https://github.com/getsentry/sentry-php)), which is
not tied to any specific library that sends HTTP messages. Instead, it
uses [Httplug](https://github.com/php-http/httplug) to let users choose
whichever PSR-7 implementation and HTTP client they want to use.

If you just want to get started quickly you should run the following command:

```bash
composer require wieni/wmsentry php-http/curl-client guzzlehttp/psr7
```
For more information, please refer to the _Install_ section of the [`sentry/sentry-php`](https://github.com/getsentry/sentry-php#install) repository README.

## How does it work?
### Configuration
Once enabled, you can configure the module through the settings form at
`/admin/config/development/logging/sentry`. 

To change the configuration of the module, users need the permission
`administer wmsentry settings`.

To dynamically set the environment, release or other config values, you
can override the config in settings.php:
```php
$config['wmsentry.settings'] = [
    'dsn' => $_ENV['SENTRY_DSN'],
    'environment' => $_SERVER['APP_ENV'],
];
```

### Events

#### `Drupal\wmsentry\WmsentryEvents::BEFORE_BREADCRUMB`
This function is called before the breadcrumb is added to the scope.
When nothing is returned from the function the breadcrumb is dropped.
The callback typically gets a second argument (called a “hint”) which
contains the original object that the breadcrumb was created from to
further customize what the breadcrumb should look like.

#### `Drupal\wmsentry\WmsentryEvents::BEFORE_SEND`
This function can return a modified event object or nothing to skip
reporting the event. This can be used for instance for manual PII
stripping before sending.
     
#### `Drupal\wmsentry\WmsentryEvents::SCOPE_ALTER`
This function is called before the scope is added to the captured event.
The scope holds data that should implicitly be sent with Sentry events.
It can hold context data, extra parameters, level overrides,
fingerprints etc.
     
#### `Drupal\wmsentry\WmsentryEvents::OPTIONS_ALTER`
This function is called before the client is created with an options
object. The options object is a configuration container for the Sentry
client.
     
## Maintainers
* [**Dieter Holvoet**](https://github.com/DieterHolvoet) - *Initial
  work*

See also the list of
[contributors](https://github.com/wieni/wmsentry/contributors) who
participated in this project.

## Changelog
All notable changes to this project will be documented in the
[CHANGELOG](CHANGELOG.md) file.

## Security
If you discover any security-related issues, please email
[info@wieni.be](mailto:info@wieni.be) instead of using the issue
tracker.

## License
Distributed under the MIT License. See the [LICENSE](LICENSE.md) file
for more information.
