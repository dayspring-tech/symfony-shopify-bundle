<?php

namespace CodeCloud\Bundle\ShopifyBundle\Controller;

use CodeCloud\Bundle\ShopifyBundle\Event\WebhookEvent;
use CodeCloud\Bundle\ShopifyBundle\Model\ShopifyStoreManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WebhookController extends Controller
{
    /**
     * @var ShopifyStoreManagerInterface
     */
    private $storeManager;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param ShopifyStoreManagerInterface $storeManager
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(ShopifyStoreManagerInterface $storeManager, EventDispatcherInterface $eventDispatcher)
    {
        $this->storeManager = $storeManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function handleWebhook(Request $request)
    {
        $oauthSharedSecret = $this->getParameter('codecloud_shopify.oauth.shared_secret');
        $hmac_raw = hash_hmac('sha256', $request->getContent(), $oauthSharedSecret, true);
        $hmac_base64 = base64_encode($hmac_raw);
        if ($hmac_base64 !== $request->headers->get('x-shopify-hmac-sha256')) {
            throw new HttpException(403, 'hmac does not match');
        }

        $topic     = $request->headers->get('x-shopify-topic');
        $storeName = $request->headers->get('x-shopify-shop-domain');

        if (!$topic || !$storeName) {
            throw new NotFoundHttpException();
        }

        if (!$this->storeManager->storeExists($storeName)) {
            throw new NotFoundHttpException();
        }

        if (empty($request->getContent())) {
            // todo log!
            throw new BadRequestHttpException('Webhook must have body content');
        }

        $payload = \GuzzleHttp\json_decode($request->getContent(), true);

        $this->eventDispatcher->dispatch(WebhookEvent::NAME, new WebhookEvent(
            $topic,
            $storeName,
            $payload
        ));

        return new Response('Shopify Webhook Received');
    }
}
