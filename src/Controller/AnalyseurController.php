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
        // Get the JSON content from the request
        $contenu = $request->getContent();
        $logger->info("Résultat de l'analyse reçu : ".$contenu);
        
        // Decode the JSON content to an associative array
        $data = json_decode($contenu, true);
    
        // Check if decoding was successful and data is an array
        if (!is_array($data)) {
            $logger->error("Failed to decode JSON.");
            return new Response("Invalid JSON data.", Response::HTTP_BAD_REQUEST);
        }
    
        // Extract needed data from the JSON array
        $resultats = $data['resultats'] ?? null;
        if ($resultats) {
            $url_analysed = $resultats['url'] ?? 'URL not found';
            $http_status = $resultats['http_status'] ?? 'HTTP status not found';
            $time = $resultats['load_time'] ?? 'Load time not found';
            $links = $resultats['links'] ?? [];
            $images = $resultats['images'] ?? [];
            
            // Log extracted data for verification
            $logger->info("URL Analysed: $url_analysed");
            $logger->info("HTTP Status: $http_status");
            $logger->info("Load Time: $time");
            $logger->info("Links: " . json_encode($links));
            $logger->info("Images: " . json_encode($images));
        } else {
            $logger->error("Results data not found.");
            return new Response("Results data not found in JSON.", Response::HTTP_BAD_REQUEST);
        }
    
        return new Response("Résultat reçu et enregistré dans les logs.");
    }
    


}
