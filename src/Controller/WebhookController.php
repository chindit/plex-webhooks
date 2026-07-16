<?php

namespace App\Controller;

use App\Entity\PlexWebhook;
use App\Repository\PlexWebhookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    /**
     * Maximum accepted size for an uploaded thumbnail (2 MB).
     */
    private const MAX_THUMB_SIZE = 2 * 1024 * 1024;

    public function __construct(
        #[Autowire('%env(string:WEBHOOK_TOKEN)%')]
        private readonly string $webhookToken,
    ) {
    }

    #[Route('/', name: 'app_webhook', methods: ['POST'])]
    public function index(Request $request, PlexWebhookRepository $repository): JsonResponse
    {
        if ($this->webhookToken === '' || !hash_equals($this->webhookToken, (string) $request->query->get('token'))) {
            return $this->json(null, Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->request->get('payload');
        if (!is_string($payload) || $payload === '') {
            return $this->json(null, Response::HTTP_BAD_REQUEST);
        }

        $webhookContent = json_decode($payload, true);
        if (!is_array($webhookContent) || !isset($webhookContent['event'])) {
            return $this->json(null, Response::HTTP_BAD_REQUEST);
        }

        $webhook = (new PlexWebhook())
            ->setContent($webhookContent)
            ->setType($webhookContent['event']);

        $thumb = $request->files->get('thumb');
        if ($thumb instanceof UploadedFile && $this->isAcceptableThumb($thumb)) {
            $webhook->setThumb(file_get_contents($thumb->getPathname()));
        }

        $repository->save($webhook, true);

        return $this->json(null, Response::HTTP_ACCEPTED);
    }

    private function isAcceptableThumb(UploadedFile $thumb): bool
    {
        return $thumb->isValid()
            && $thumb->getSize() <= self::MAX_THUMB_SIZE
            && str_starts_with((string) $thumb->getMimeType(), 'image/');
    }
}
