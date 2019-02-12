<?php

namespace Drupal\wmsentry\Event;

use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\Event;

class SentryScopeAlterEvent extends Event
{
    /** @var Scope */
    protected $scope;

    public function __construct(Scope $scope)
    {
        $this->scope = $scope;
    }

    public function getScope(): Scope
    {
        return $this->scope;
    }
}
