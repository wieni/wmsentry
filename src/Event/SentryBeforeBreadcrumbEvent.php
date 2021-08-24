<?php

namespace Drupal\wmsentry\Event;

use Sentry\Breadcrumb;
use Drupal\Component\EventDispatcher\Event;

class SentryBeforeBreadcrumbEvent extends Event
{
    /** @var Breadcrumb */
    protected $breadcrumb;
    /** @var bool */
    protected $excluded = false;

    public function __construct(Breadcrumb $breadcrumb)
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getBreadcrumb(): Breadcrumb
    {
        return $this->breadcrumb;
    }

    public function isExcluded(): bool
    {
        return $this->excluded;
    }

    public function setExcluded(bool $excluded): self
    {
        $this->excluded = $excluded;

        return $this;
    }
}
