<?php

namespace Drupal\wmsentry\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\wmsentry\Event\SentryBeforeSendEvent;
use Drupal\wmsentry\WmsentryEvents;
use Sentry\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExcludedTagsSubscriber implements EventSubscriberInterface
{
    /** @var ConfigFactoryInterface */
    protected $config;

    public function __construct(
        ConfigFactoryInterface $config
    ) {
        $this->config = $config;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WmsentryEvents::BEFORE_SEND => ['onBeforeSend'],
        ];
    }

    public function onBeforeSend(SentryBeforeSendEvent $event): void
    {
        $excludedTags = $this->config->get('wmsentry.settings')->get('excluded_tags') ?? [];

        if (empty($excludedTags)) {
            return;
        }

        $allTags = $this->getAllTags($event->getEvent());

        foreach ($excludedTags as $excludedTag) {
            $tag = $excludedTag['tag'] ?? null;
            $value = $excludedTag['value'] ?? null;

            if (isset($allTags[$tag]) && $allTags[$tag] === $value) {
                $event->setExcluded();
            }
        }
    }

    protected function getAllTags(Event $event)
    {
        return array_merge(
            $event->getTagsContext()->toArray(),
            $event->getExtraContext()->toArray(),
            [
                'environment' => $event->getEnvironment(),
                'level' => (string) $event->getLevel(),
                'logger' => $event->getLogger(),
                'server_name' => $event->getServerName(),
                'os.name' => $event->getServerOsContext()->getName(),
                'os.version' => $event->getServerOsContext()->getVersion(),
                'os.build' => $event->getServerOsContext()->getBuild(),
                'runtime.name' => $event->getRuntimeContext()->getName(),
                'runtime.version' => $event->getRuntimeContext()->getVersion(),
                'user.id' => $event->getUserContext()->getId(),
                'user.ip_address' => $event->getUserContext()->getIpAddress(),
                'user.username' => $event->getUserContext()->getUsername(),
                'user.email' => $event->getUserContext()->getEmail(),
            ]
        );
    }
}
