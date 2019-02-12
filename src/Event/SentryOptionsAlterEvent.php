<?php

namespace Drupal\wmsentry\Event;

use Sentry\Options;
use Symfony\Component\EventDispatcher\Event;

class SentryOptionsAlterEvent extends Event
{
    /** @var Options */
    protected $options;

    public function __construct(Options $options)
    {
        $this->options = $options;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }
}
