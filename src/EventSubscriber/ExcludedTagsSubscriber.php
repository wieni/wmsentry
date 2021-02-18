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

    protected function getAllTags(Event $event): array
    {
        $tags = [
            'environment' => $event->getEnvironment(),
            'level' => (string) $event->getLevel(),
            'logger' => $event->getLogger(),
            'server_name' => $event->getServerName(),
        ];

        if ($os = $event->getOsContext()) {
            $tags['os.name'] = $os->getName();
            $tags['os.version'] = $os->getVersion();
            $tags['os.build'] = $os->getBuild();
        }

        if ($runtime = $event->getRuntimeContext()) {
            $tags['runtime.name'] = $runtime->getName();
            $tags['runtime.version'] = $runtime->getVersion();
        }

        if ($user = $event->getUser()) {
            $tags['user.id'] = $user->getId();
            $tags['user.ip_address'] = $user->getIpAddress();
            $tags['user.username'] = $user->getUsername();
            $tags['user.email'] = $user->getEmail();
        }

        return array_merge(
            $event->getTags(),
            $event->getExtra(),
            $tags
        );
    }
}
