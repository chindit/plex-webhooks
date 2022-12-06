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
    #[Route('/webhook', name: 'app_webhook')]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $webhookContent = json_decode($request->getContent(), true);

        $webhook = (new PlexWebhook())
            ->setContent($webhookContent)
            ->setType($webhookContent['event']);
        $entityManager->persist($webhook);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_ACCEPTED);
    }
}
