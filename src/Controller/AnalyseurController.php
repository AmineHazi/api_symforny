<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class AnalyseurController extends AbstractController
{
    #[Route('/start_worker', name: 'start_worker', methods: ['POST'])]
    public function startWorker(Request $request, LoggerInterface $logger): Response
    {
        // Récupérer l'URL depuis la requête
        $url = $request->request->get('url');

        if (!$url) {
            return $this->json([
                'message' => 'No URL provided',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Logique pour démarrer le worker Docker
        echo("trying to start the worker");
    $command = "docker run --network=host php-url-analyser " . escapeshellarg($url) . " > /dev/null 2>&1 &";
        $output = shell_exec($command . " 2>&1");
        $logger->info("Docker command output: " . $output);

        // Réponse indiquant que le worker a été démarré
        return $this->json([
            'message' => "Initiated analysis for $url.",
        ]);
    }
    
    #[Route('/resultat', name: 'analyse_resultat', methods: ['POST'])]
    public function resultat(Request $request, LoggerInterface $logger): Response
    {
        $contenu = $request->getContent();
        $logger->info("Résultat de l'analyse reçu : ".$contenu);
        
        return new Response("Résultat reçu et enregistré dans les logs.");
    }


}
