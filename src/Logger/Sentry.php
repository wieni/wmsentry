<?php

namespace Drupal\wmsentry\Logger;

use Drupal\Component\ClassFinder\ClassFinder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\wmsentry\Event\SentryBeforeBreadcrumbEvent;
use Drupal\wmsentry\Event\SentryBeforeSendEvent;
use Drupal\wmsentry\Event\SentryOptionsAlterEvent;
use Drupal\wmsentry\Event\SentryScopeAlterEvent;
use Drupal\wmsentry\WmsentryEvents;
use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\Serializer;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Sentry implements LoggerInterface
{
    use DependencySerializationTrait;
    use RfcLoggerTrait;

    /** @var ConfigFactoryInterface */
    protected $config;
    /** @var LogMessageParserInterface */
    protected $parser;
    /** @var ClientInterface */
    protected $client;
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    public function __construct(
        ConfigFactoryInterface $config,
        LogMessageParserInterface $parser,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->config = $config->get('wmsentry.settings');
        $this->parser = $parser;
        $this->eventDispatcher = $eventDispatcher;
        $this->client = $this->getClient();

        /**
         * Replace the Drupal error handler
         * @see _wmsentry_error_handler_real
         */
        set_error_handler('_wmsentry_error_handler_real');
    }

    protected function getClient(): ?ClientInterface
    {
        if (isset($this->client)) {
            return $this->client;
        }

        $options = new Options([
            'dsn' => $this->config->get('dsn'),
            'attach_stacktrace' => true,
            'before_send' => [$this, 'onBeforeSend'],
            'before_breadcrumb' => [$this, 'onBeforeBreadcrumb'],
        ]);

        if ($value = $this->config->get('release')) {
            $options->setRelease($value);
        }

        if ($value = $this->config->get('environment')) {
            $options->setEnvironment($value);
        }

        if ($value = $this->config->get('excluded_exceptions')) {
            $options->setExcludedExceptions($value);
        }

        $this->eventDispatcher->dispatch(WmsentryEvents::OPTIONS_ALTER, new SentryOptionsAlterEvent($options));

        return $this->client = (new ClientBuilder($options))->getClient();
    }

    protected function getLogLevel(int $rfc): ?string
    {
        $levels = [
            RfcLogLevel::EMERGENCY => Severity::FATAL,
            RfcLogLevel::ALERT => Severity::FATAL,
            RfcLogLevel::CRITICAL => Severity::FATAL,
            RfcLogLevel::ERROR => Severity::ERROR,
            RfcLogLevel::WARNING => Severity::WARNING,
            RfcLogLevel::NOTICE => Severity::INFO,
            RfcLogLevel::INFO => Severity::INFO,
            RfcLogLevel::DEBUG => Severity::DEBUG,
        ];

        return $levels[$rfc] ?? null;
    }

    protected function formatMessage(string $message, array $context): string
    {
        /** @see errors.inc */
        if (isset($context['@message'])) {
            return $context['@message'];
        }

        $placeholders = $this->parser->parseMessagePlaceholders($message, $context);

        if (empty($placeholders)) {
            return $message;
        }

        return strtr($message, $placeholders);
    }

    protected function buildStacktrace(array $context): Stacktrace
    {
        if (!empty($context['backtrace'])) {
            $backtrace = $context['backtrace'];

        } else {
            $backtrace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $finder = new ClassFinder();

            if ($backtrace[0]['file'] === realpath($finder->findFile(\Drupal\Core\Logger\LoggerChannel::class))) {
                array_shift($stack);

                if ($stack[0]['file'] === realpath($finder->findFile(\Psr\Log\LoggerTrait::class))) {
                    array_shift($stack);
                }
            }
        }

        $stacktrace = new Stacktrace(
            $this->client->getOptions(),
            new Serializer($this->client->getOptions()),
            new RepresentationSerializer($this->client->getOptions())
        );

        return array_reduce(
            $backtrace,
            function (Stacktrace $stacktrace, array $frame) {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? 0;
                $stacktrace->addFrame($file, $line, $frame);

                return $stacktrace;
            },
            $stacktrace
        );
    }

    protected function buildScope(array $context): Scope
    {
        $scope = new Scope;

        foreach (['channel', '%type'] as $key) {
            if (isset($context[$key])) {
                $scope->setTag(ltrim($key, '%@'), $context[$key]);
            }
        }

        foreach (['link', 'referer', 'request_uri'] as $key) {
            if (!empty($context[$key])) {
                $scope->setExtra($key, $context[$key]);
            }
        }

        $scope->setUser($this->getUserData($context));

        $this->eventDispatcher->dispatch(WmsentryEvents::SCOPE_ALTER, new SentryScopeAlterEvent($scope));

        return $scope;
    }

    protected function getUserData(array $context): array
    {
        $data = [
            'id' => (string) ($context['uid'] ?? '0'),
            'ip_address' => $context['ip'],
        ];

        if ($context['user'] instanceof AccountProxyInterface) {
            $data['username'] = $context['user']->getDisplayName();
            $data['email'] = $context['user']->getEmail();
        }

        return $data;
    }

    protected function isLogLevelIncluded(int $level): bool
    {
        $index = $level + 1;

        return !empty($this->config->get("log_levels.{$index}"));
    }

    public function onBeforeSend(Event $event): ?Event
    {
        /** @var SentryBeforeSendEvent $beforeSendEvent */
        $beforeSendEvent = $this->eventDispatcher->dispatch(WmsentryEvents::BEFORE_SEND, new SentryBeforeSendEvent($event));

        return $beforeSendEvent->isExcluded() ? null : $beforeSendEvent->getEvent();
    }

    public function onBeforeBreadcrumb(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        /** @var SentryBeforeBreadcrumbEvent $beforeBreadcrumbEvent */
        $beforeBreadcrumbEvent = $this->eventDispatcher->dispatch(WmsentryEvents::BEFORE_BREADCRUMB, new SentryBeforeBreadcrumbEvent($breadcrumb));

        return $beforeBreadcrumbEvent->isExcluded() ? null : $beforeBreadcrumbEvent->getBreadcrumb();
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        if (!$this->isLogLevelIncluded($level)) {
            return;
        }

        $scope = $this->buildScope($context);

        $payload = [
            'level' => $this->getLogLevel($level),
            'message' => $this->formatMessage($message, $context),
            'logger' => $context['channel'],
            'stacktrace' => $this->buildStacktrace($context),
        ];

        $this->client->captureEvent($payload, $scope);
    }
}
