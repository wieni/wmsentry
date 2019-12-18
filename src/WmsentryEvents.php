<?php

namespace Drupal\wmsentry;

final class WmsentryEvents
{
    /**
     * This function is called before the breadcrumb is added to the scope.
     * When nothing is returned from the function the breadcrumb is dropped.
     * The callback typically gets a second argument (called a “hint”) which
     * contains the original object that the breadcrumb was created from to
     * further customize what the breadcrumb should look like.
     *
     * The event object is an instance of
     * @uses \Drupal\wmsentry\Event\SentryBeforeBreadcrumbEvent
     */
    public const BEFORE_BREADCRUMB = 'wmsentry.before_breadcrumb';

    /**
     * This function can return a modified event object or nothing
     * to skip reporting the event. This can be used for instance
     * for manual PII stripping before sending.
     *
     * The event object is an instance of
     * @uses \Drupal\wmsentry\Event\SentryBeforeSendEvent
     */
    public const BEFORE_SEND = 'wmsentry.before_send';

    /**
     * This function is called before the scope is added to the captured event.
     * The scope holds data that should implicitly be sent with Sentry events. It
     * can hold context data, extra parameters, level overrides, fingerprints etc.
     *
     * The event object is an instance of
     * @uses \Drupal\wmsentry\Event\SentryScopeAlterEvent
     */
    public const SCOPE_ALTER = 'wmsentry.scope_alter';

    /**
     * This function is called before the client is created with an options object.
     * The options object is a configuration container for the Sentry client.
     *
     * The event object is an instance of
     * @uses \Drupal\wmsentry\Event\SentryOptionsAlterEvent
     */
    public const OPTIONS_ALTER = 'wmsentry.options_alter';
}
