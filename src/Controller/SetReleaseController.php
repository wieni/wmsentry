<?php

namespace Drupal\wmsentry\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SetReleaseController implements ContainerInjectionInterface
{
    /** @var RedirectDestinationInterface */
    protected $destination;
    /** @var MessengerInterface */
    protected $messenger;
    /** @var StateInterface */
    protected $state;
    /** @var TimeInterface */
    protected $time;

    public static function create(ContainerInterface $container)
    {
        $instance = new static();
        $instance->destination = $container->get('redirect.destination');
        $instance->messenger = $container->get('messenger');
        $instance->state = $container->get('state');
        $instance->time = $container->get('datetime.time');

        return $instance;
    }

    public function set(Request $request): Response
    {
        if (!$release = $request->query->get('release')) {
            return new Response(
                'The release ID needs to be passed through the release query parameter.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $isValid = $this->isValidToken(
            $request->query->get('token'),
            $request->query->get('timestamp')
        );

        if (!$isValid) {
            return new JsonResponse(
                'The token and/or timestamp query parameters are incorrect.',
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->state->set('wmsentry.release', $release);

        return new Response(
            'Successfully changed the current Sentry release'
        );
    }

    public function unset(): Response
    {
        $this->state->delete('wmsentry.release');
        $this->messenger->addStatus('Successfully deleted the Sentry release override');

        try {
            $destination = $this->destination->get();
            $url = Url::fromUserInput($destination)->setAbsolute()->toString();
            return RedirectResponse::create($url);
        } catch (\InvalidArgumentException $e) {
        }

        return Response::create();
    }

    protected function isValidToken(?string $token, ?int $timestamp): bool
    {
        if (!$token || !$timestamp) {
            return false;
        }

        if (($this->time->getRequestTime() - $timestamp) >= 300) {
            return false;
        }

        return hash_equals(Crypt::hmacBase64($timestamp, Settings::get('hash_salt')), $token);
    }
}
