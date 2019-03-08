<?php

namespace Drupal\wmsentry\Utility;

use Drupal\Core\Utility\Error as ErrorBase;

/**
 * Drupal error utility class.
 */
class Error extends ErrorBase
{
    protected static $blacklistFunctions = [
        'debug',
        '_drupal_error_handler',
        '_drupal_exception_handler',
        'handleError'
    ];
}
