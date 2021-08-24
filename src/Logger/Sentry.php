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
use Drupal\Core\State\StateInterface;
use Drupal\user\UserInterface;
use Drupal\wmsentry\Event\SentryBeforeBreadcrumbEvent;
use Drupal\wmsentry\Event\SentryBeforeSendEvent;
use Drupal\wmsentry\Event\SentryOptionsAlterEvent;
use Drupal\wmsentry\Event\SentryScopeAlterEvent;
use Drupal\wmsentry\WmsentryEvents;
use Drush\Drush;
use Psr\Log\LoggerInterface;
use Sentry\EventHint;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\StacktraceBuilder;
use Sentry\UserDataBag;
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
    /** @var StateInterface */
    protected $state;
    /** @var StacktraceBuilder */
    protected $stackTraceBuilder;
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;
    /** @var ModuleHandlerInterface */
    protected $moduleHandler;
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;

    public function __construct(
        ConfigFactoryInterface $config,
        LogMessageParserInterface $parser,
        StateInterface $state,
        EventDispatcherInterface $eventDispatcher,
        ModuleHandlerInterface $moduleHandler,
        EntityTypeManagerInterface $entityTypeManager
    ) {
        $this->config = $config->get('wmsentry.settings');
        $this->parser = $parser;
        $this->state = $state;
        $this->eventDispatcher = $eventDispatcher;
        $this->moduleHandler = $moduleHandler;
        $this->entityTypeManager = $entityTypeManager;
        $this->client = $this->getClient();
        $this->stackTraceBuilder = new StacktraceBuilder(
            $this->client->getOptions(),
            new RepresentationSerializer($this->client->getOptions())
        );

        SentrySdk::getCurrentHub()->bindClient($this->client);

        /**
         * Replace the Drupal error handler
         * @see _wmsentry_error_handler_real
         */
        $this->moduleHandler->loadInclude('wmsentry', 'module');
        set_error_handler('_wmsentry_error_handler_real');

        // Add Drush console error event listener.
        if (class_exists(Drush::class) && method_exists(Drush::class, 'service') && Drush::hasService('eventDispatcher')) {
            Drush::service('eventDispatcher')->addListener(ConsoleEvents::ERROR, [$this, 'logDrush']);
        }
    }

    public function onBeforeSend(Event $event): ?Event
    {
        /** @var SentryBeforeSendEvent $beforeSendEvent */
        $beforeSendEvent = $this->eventDispatcher->dispatch(
            new SentryBeforeSendEvent($event),
            WmsentryEvents::BEFORE_SEND
        );

        return $beforeSendEvent->isExcluded() ? null : $beforeSendEvent->getEvent();
    }

    public function onBeforeBreadcrumb(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        /** @var SentryBeforeBreadcrumbEvent $beforeBreadcrumbEvent */
        $beforeBreadcrumbEvent = $this->eventDispatcher->dispatch(
            new SentryBeforeBreadcrumbEvent($breadcrumb),
            WmsentryEvents::BEFORE_BREADCRUMB
        );

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
            'level' => (string) $this->getLogLevel($level),
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

        $event = Event::createEvent();
        $event->setLevel($this->getLogLevel($level));
        $event->setMessage($this->formatMessage($message, $context));
        $event->setLogger($context['channel']);
        $event->setStacktrace($this->buildStacktrace($context));
        $event->setUser($this->getUserData($context));

        $event->setTags(array_reduce(
            ['channel', '%type'],
            static function (array $tags, string $key) use ($context) {
                if (isset($context[$key])) {
                    $tags[ltrim($key, '%@')] = $context[$key];
                }

                return $tags;
            },
            []
        ));

        $event->setExtra(array_reduce(
            ['link', 'referer', 'request_uri'],
            static function (array $tags, string $key) {
                if (isset($context[$key])) {
                    $tags[$key] = $context[$key];
                }

                return $tags;
            },
            []
        ));

        $eventHint = new EventHint();
        $eventHint->extra = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        if ($stacktrace = $event->getStacktrace()) {
            $eventHint->stacktrace = $stacktrace;
        }

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $eventHint->exception = $context['exception'];
        }

        withScope(function (Scope $scope) use ($event, $eventHint, $context): void {
            $this->eventDispatcher->dispatch(
                new SentryScopeAlterEvent($scope, $context),
                WmsentryEvents::SCOPE_ALTER
            );

            captureEvent($event, $eventHint);
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
            'in_app_exclude' => $this->normalizePaths($this->config->get('in_app_exclude') ?? []),
            'in_app_include' => $this->normalizePaths($this->config->get('in_app_include') ?? []),
            'default_integrations' => false,
            'integrations' => [
                new RequestIntegration(),
                new TransactionIntegration(),
                new FrameContextifierIntegration(),
                new EnvironmentIntegration(),
            ],
        ]);

        if ($value = $this->getRelease()) {
            $options->setRelease($value);
        }

        if ($value = $this->config->get('environment')) {
            $options->setEnvironment($value);
        }

        $this->eventDispatcher->dispatch(
            new SentryOptionsAlterEvent($options),
            WmsentryEvents::OPTIONS_ALTER
        );

        return $this->client = (new ClientBuilder($options))->getClient();
    }

    protected function getLogLevel(int $rfc): ?Severity
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

        if (isset($levels[$rfc])) {
            return new Severity($levels[$rfc]);
        }

        return null;
    }

    protected function getRelease(): ?string
    {
        if ($override = $this->state->get('wmsentry.release')) {
            return $override;
        }

        return $this->config->get('release');
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
        $includeArgs = $this->config->get('include_stacktrace_func_args');

        if (!empty($context['backtrace'])) {
            $backtrace = $context['backtrace'];
            if (!$includeArgs) {
                foreach ($backtrace as &$frame) {
                    unset($frame['args']);
                }
            }
        } else {
            $backtrace = \debug_backtrace($includeArgs ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS);
            $finder = new ClassFinder();

            $toIgnore = array_map(
                static function (string $className) use ($finder) {
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

        $stacktrace = $this->stackTraceBuilder->buildFromBacktrace($backtrace, '', 0);
        $stacktrace->removeFrame(count($stacktrace->getFrames()) - 1);

        return $stacktrace;
    }

    protected function getUserData(array $context): UserDataBag
    {
        $data = new UserDataBag();
        $data->setId((string) ($context['uid'] ?? '0'));

        try {
            $data->setIpAddress($context['ip']);
        } catch (\InvalidArgumentException $e) {
        }

        if ($uid = $data->getId()) {
            /** @var UserInterface $user */
            $user = $this->entityTypeManager
                ->getStorage('user')
                ->load($uid);

            if ($user) {
                $data->setUsername($user->getDisplayName());
                $data->setEmail($user->getEmail());
            }
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
        foreach ($this->config->get('excluded_exceptions') as $excludedClass) {
            if (is_a($type, $excludedClass, true)) {
                return false;
            }
        }

        return true;
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
