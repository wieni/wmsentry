<?php

namespace Drupal\wmsentry\Logger;

use Drupal\Component\ClassFinder\ClassFinder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\user\UserInterface;
use Drupal\wmsentry\Event\SentryBeforeBreadcrumbEvent;
use Drupal\wmsentry\Event\SentryBeforeSendEvent;
use Drupal\wmsentry\Event\SentryOptionsAlterEvent;
use Drupal\wmsentry\Event\SentryScopeAlterEvent;
use Drupal\wmsentry\WmsentryEvents;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use function Sentry\addBreadcrumb;
use Sentry\Breadcrumb;
use function Sentry\captureEvent;
use function Sentry\captureException;
use Sentry\ClientBuilder;
use Sentry\ClientInterface;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\Serializer\RepresentationSerializer;
use Sentry\Serializer\Serializer;
use Sentry\Severity;
use Sentry\Stacktrace;
use Sentry\State\Scope;
use function Sentry\withScope;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
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
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        ConfigFactoryInterface $config,
        LogMessageParserInterface $parser,
        EventDispatcherInterface $eventDispatcher,
        ModuleHandlerInterface $moduleHandler,
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->config = $config->get('wmsentry.settings');
        $this->parser = $parser;
        $this->eventDispatcher = $eventDispatcher;
        $this->moduleHandler = $moduleHandler;
        $this->entityTypeManager = $entityTypeManager;
        $this->client = $this->getClient();

        SentrySdk::getCurrentHub()->bindClient($this->client);

        /**
         * Replace the Drupal error handler
         * @see _wmsentry_error_handler_real
         */
        $this->moduleHandler->loadInclude('wmsentry', 'module');
        set_error_handler('_wmsentry_error_handler_real');

        // Add Drush console error event listener.
        if (class_exists(Drush::class) && method_exists(Drush::class, 'service')) {
            Drush::service('eventDispatcher')->addListener(ConsoleEvents::ERROR, [$this, 'logDrush']);
        }
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

    public function log($level, $message, array $context = []): void
    {
        $this->logException($level, $message, $context);
        $this->logBreadcrumb($level, $message, $context);
    }

    public function logDrush(ConsoleErrorEvent $event): void
    {
        captureException($event->getError());
    }

    protected function logBreadcrumb($level, $message, array $context): void
    {
        addBreadcrumb(Breadcrumb::fromArray([
            'level' => $this->getLogLevel($level),
            'category' => $context['channel'],
            'message' => $this->formatMessage($message, $context),
        ]));
    }

    protected function logException($level, $message, array $context): void
    {
        if (!$this->isLogLevelIncluded($level)) {
            return;
        }

        if (isset($context['%type']) && !$this->isExceptionIncluded($context['%type'])) {
            return;
        }

        withScope(function (Scope $scope) use ($level, $message, $context): void {
            $payload = [
                'level' => $this->getLogLevel($level),
                'message' => $this->formatMessage($message, $context),
                'logger' => $context['channel'],
                'stacktrace' => $this->buildStacktrace($context),
            ];

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

            $this->eventDispatcher->dispatch(
                WmsentryEvents::SCOPE_ALTER,
                new SentryScopeAlterEvent($scope, $context)
            );

            captureEvent($payload);
        });
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
            'in_app_exclude' => $this->normalizePaths($this->config->get('in_app_exclude')),
            'in_app_include' => $this->normalizePaths($this->config->get('in_app_include')),
        ]);

        if ($value = $this->config->get('release')) {
            $options->setRelease($value);
        }

        if ($value = $this->config->get('environment')) {
            $options->setEnvironment($value);
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

            $toIgnore = array_map(
                function (string $className) use ($finder) {
                    if (file_exists($className)) {
                        return realpath($className);
                    }

                    return realpath($finder->findFile($className));
                },
                [
                    self::class,
                    \Drupal\Core\Logger\LoggerChannel::class,
                    \Psr\Log\LoggerTrait::class,
                    \Sentry\State\Hub::class,
                    DRUPAL_ROOT . '/../vendor/sentry/sentry/src/functions.php',
                ]
            );

            while (!empty($backtrace) && in_array($backtrace[0]['file'], $toIgnore, true)) {
                array_shift($backtrace);
            }
        }

        $stacktrace = new Stacktrace(
            $this->client->getOptions(),
            new Serializer($this->client->getOptions()),
            new RepresentationSerializer($this->client->getOptions())
        );

        return array_reduce(
            $backtrace,
            function (Stacktrace $stacktrace, array $frame): Stacktrace {
                $file = $frame['file'] ?? '[internal]';
                $line = $frame['line'] ?? 0;

                if (!$this->config->get('include_stacktrace_func_args')) {
                    $frame['args'] = [];
                }

                $stacktrace->addFrame($file, $line, $frame);

                return $stacktrace;
            },
            $stacktrace
        );
    }

    protected function getUserData(array $context): array
    {
        $data = [
            'id' => (string) ($context['uid'] ?? '0'),
            'ip_address' => $context['ip'],
        ];

        if (!isset($context['uid'])) {
            return $data;
        }

        /** @var UserInterface $user */
        $user = $this->entityTypeManager->getStorage('user')->load($context['uid']);

        if ($user) {
            $data['username'] = $user->getDisplayName();
            $data['email'] = $user->getEmail();
        }

        return $data;
    }

    protected function isLogLevelIncluded(int $level): bool
    {
        $index = $level + 1;

        return !empty($this->config->get("log_levels.{$index}"));
    }

    protected function isExceptionIncluded(string $type): bool
    {
        return !in_array($type, $this->config->get('excluded_exceptions'), true);
    }

    protected function normalizePaths(array $paths): array
    {
        return array_map(
            static function (string $path): string {
                return DRUPAL_ROOT . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
            },
            $paths
        );
    }
}
