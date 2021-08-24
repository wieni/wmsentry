<?php

namespace Drupal\wmsentry\Event;

use Sentry\Event;
use Drupal\Component\EventDispatcher\Event as EventBase;

class SentryBeforeSendEvent extends EventBase
{
    /** @var Event */
    protected $event;
    /** @var bool */
    protected $excluded = false;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function isExcluded(): bool
    {
        return $this->excluded;
    }

    public function setExcluded(bool $excluded = true): self
    {
        $this->excluded = $excluded;

        return $this;
    }
}
