<?php

namespace Drupal\wmsentry\Event;

use Sentry\State\Scope;
use Drupal\Component\EventDispatcher\Event;

class SentryScopeAlterEvent extends Event
{
    /** @var Scope */
    protected $scope;
    /** @var array */
    protected $context;

    public function __construct(Scope $scope, array $context)
    {
        $this->scope = $scope;
        $this->context = $context;
    }

    public function getScope(): Scope
    {
        return $this->scope;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
