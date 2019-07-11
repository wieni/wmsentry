<a href="https://www.wieni.be">
    <img src="https://www.wieni.be/themes/custom/drupack/logo.svg" alt="Wieni logo" title="Wieni" align="right" height="60" />
</a>

wmsentry
======================

[![Latest Stable Version](https://poser.pugx.org/wieni/wmsentry/v/stable)](https://packagist.org/packages/wieni/wmsentry)
[![Total Downloads](https://poser.pugx.org/wieni/wmsentry/downloads)](https://packagist.org/packages/wieni/wmsentry)
[![License](https://poser.pugx.org/wieni/wmsentry/license)](https://packagist.org/packages/wieni/wmsentry)

> A module for sending errors to Sentry in Drupal 8.

## Installation
This module uses the Sentry PHP package ([`sentry/sentry`](https://github.com/getsentry/sentry-php)), which is not tied to any specific library that sends HTTP messages. Instead,
it uses [Httplug](https://github.com/php-http/httplug) to let users choose whichever
PSR-7 implementation and HTTP client they want to use.

If you just want to get started quickly you should run the following command:

```bash
composer require wieni/wmsentry php-http/curl-client guzzlehttp/psr7
```
For more information, please refer to the _Install_ section of the [`sentry/sentry-php`](https://github.com/getsentry/sentry-php#install) repository README.

