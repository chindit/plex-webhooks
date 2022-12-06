<?php

namespace App\Controller;

use App\Entity\PlexWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebhookController extends AbstractController
{
    #[Route('/', name: 'app_webhook')]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $webhookContent = json_decode($request->request->get('payload'), true);
        if (!$webhookContent) {
            return $this->json(null, Response::HTTP_BAD_REQUEST);
        }

        $webhook = (new PlexWebhook())
            ->setContent($webhookContent)
            ->setType($webhookContent['event']);

        if ($request->files->has('thumb')) {
            $webhook->setThumb(file_get_contents($request->files->get('thumb')->getPathname()));
        }

        $entityManager->persist($webhook);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_ACCEPTED);
    }
}
