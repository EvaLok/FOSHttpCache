<?php

/*
 * This file is part of the FOSHttpCache package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\HttpCache\SymfonyCache;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Purge handler for the symfony built-in HttpCache.
 *
 * @author David Buchmann <mail@davidbu.ch>
 *
 * {@inheritdoc}
 */
class PurgeSubscriber extends AccessControlledSubscriber
{
    /**
     * The options configured in the constructor argument or default values.
     *
     * @var array
     */
    private $options = array();

    /**
     * When creating this subscriber, you can configure a number of options.
     *
     * - purge_method:         HTTP method that identifies purge requests.
     * - purge_client_matcher: RequestMatcher to identify valid purge clients.
     * - purge_client_ips:     IP or array of IPs that are allowed to purge.
     *
     * Only set one of purge_client_ips and purge_client_matcher.
     *
     * @param array $options Options to overwrite the default options
     *
     * @throws \InvalidArgumentException if unknown keys are found in $options
     */
    public function __construct(array $options = array())
    {
        $extra = array_diff(array_keys($options), array('purge_client_matcher', 'purge_client_ips', 'purge_method'));
        if (count($extra)) {
            throw new \InvalidArgumentException(sprintf(
                'Unsupported purge configuration option(s) "%s"',
                implode(', ', $extra)
            ));
        }

        parent::__construct(
            isset($options['purge_client_matcher']) ? $options['purge_client_matcher'] : null,
            isset($options['purge_client_ips']) ? $options['purge_client_ips'] : null
        );

        $this->options['purge_method'] = isset($options['purge_method']) ? $options['purge_method'] : 'PURGE';
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::PRE_INVALIDATE => 'handlePurge',
        );
    }

    /**
     * Look at unsafe requests and handle purge requests.
     *
     * Prevents access when the request comes from a non-authorized client.
     *
     * @param CacheEvent $event
     */
    public function handlePurge(CacheEvent $event)
    {
        $request = $event->getRequest();
        if ($this->options['purge_method'] !== $request->getMethod()) {
            return;
        }

        if (!$this->isRequestAllowed($request)) {
            $event->setResponse(new Response('', 400));

            return;
        }

        $response = new Response();
        if ($event->getKernel()->getStore()->purge($request->getUri())) {
            $response->setStatusCode(200, 'Purged');
        } else {
            $response->setStatusCode(200, 'Not found');
        }
        $event->setResponse($response);
    }
}
