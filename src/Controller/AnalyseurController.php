<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AnalyseResult;

class AnalyseurController extends AbstractController
{
    
    private function add_http($url) {
        if (strpos($url, '://') === false) {
            $url = 'http://' . $url;
        }
        return $url;
    }

    #[Route('/start_worker', name: 'start_worker', methods: ['POST'])]
    public function startWorker(Request $request, LoggerInterface $logger, EntityManagerInterface $em): Response
    {
        $data = json_decode($request->getContent(), true);
        $url = $this->add_http($request->request->get('url'));
        if (!$url) {
            return $this->json(['message' => 'No URL provided'], Response::HTTP_BAD_REQUEST);
        }
        $depth = $request->request->get('depth') ?? 1;

        $analyse = new AnalyseResult();
        $analyse->setUrl($url);
        $analyse->setDepth($depth);
        $analyse->setAnalyseEnCours(true);
        $analyse->setLinksToAnalyse([$url]); // Initialisation avec le premier lien
        $analyse->setLinksNbr(0); // Default value if not set yet
        $analyse->setImagesNbr(0); // Default value
        $analyse->setTotalTime(new \DateTime('@0'));
        $em->persist($analyse);
        $em->flush();

        $this->launchWorkers($analyse, $em);

        return $this->json(['message' => "Analysis initiated for $url with depth $depth."]);
    }
        
    private function launchWorkers(AnalyseResult $analyse, EntityManagerInterface $em)
    {
        $links = $analyse->getLinksToAnalyse();
        $depth = $analyse->getDepth();

        if ($depth > 0 && count($links) > 0) {
            // Launch up to 5 workers simultaneously
            foreach (array_slice($links, 0, 5) as $link) {
                $command = "docker run --network=host php-url-analyser " . escapeshellarg($link) . " > /dev/null 2>&1 &";
                shell_exec($command);
            }

            // Remove links that have been handed off to workers
            $remaining_links = array_slice($links, 5);
            $analyse->setLinksToAnalyse($remaining_links);
            $analyse->setDepth($depth - 1);
            $em->flush();
        }
    }
    
    #[Route('/resultat', name: 'analyse_resultat', methods: ['POST'])]
    public function resultat(Request $request, LoggerInterface $logger, EntityManagerInterface $em): Response
    {
        $content = json_decode($request->getContent(), true);
        $url_analysed = $content['resultats']['url'] ?? null;
    
        if (!$url_analysed) {
            $logger->error("URL key is missing in the data array.");
            return new Response("URL key is missing.", Response::HTTP_BAD_REQUEST);
        }
    
        $analyse = $em->getRepository(AnalyseResult::class)->findOneBy(['analyse_en_cours' => true]);
        if (!$analyse) {
            $logger->error("No ongoing analysis found for the URL: {$url_analysed}.");
            return new Response("No ongoing analysis found.", Response::HTTP_BAD_REQUEST);
        }
    
        // Accumulate links and other results
        $new_links = $content['resultats']['links'] ?? [];
        $existing_links = $analyse->getLinksToAnalyse();
        $all_found_links = $analyse->getLinksFound() ?? [];
    
        $updated_links_to_analyse = array_unique(array_merge($existing_links, $new_links));
        $updated_found_links = array_unique(array_merge($all_found_links, $new_links));
    
        $analyse->setLinksToAnalyse($updated_links_to_analyse);
        $analyse->setLinksFound($updated_found_links);
        $analyse->setImagesNbr($content['resultats']['images_nbr'] ?? 0); // You might also want to accumulate this
        $total_time = $analyse->getTotalTime() + $content['resultats']['load_time'];

        $analyse->setTotalTime(new \DateTime('@' . ($total_time ?? 0))); // Adjust according to how you calculate total time
    
        // Check if more links to process or if depth limit reached
        if ($analyse->getDepth() > 0 && count($updated_links_to_analyse) > 0) {
            $this->launchWorkers($analyse, $em);
        } else {
            $analyse->setAnalyseEnCours(false);
            $em->flush();
            $logger->error("Analysis completed and database updated.");
        }
    
        $em->flush();
        return new Response("Results received and database updated, continuing analysis.");
    }
    

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(EntityManagerInterface $em): JsonResponse
    {
        $analyse = $em->getRepository(AnalyseResult::class)->findOneBy(['analyse_en_cours' => false]);
    
        if (!$analyse) {
            return $this->json(['message' => 'Analysis is still processing or no analysis has been initiated.']);
        }
    
        $result = [
            'url' => $analyse->getUrl(),
            'links_nbr' => $analyse->getLinksNbr(),
            'links_found' => $analyse->getLinksFound(),
            'images_nbr' => $analyse->getImagesNbr(),
            'total_time' => $analyse->getTotalTime()->format('H:i:s'),
            'analyse_en_cours' => $analyse->isAnalyseEnCours()
        ];
    
        // Supprimer l'enregistrement de la base de données
        $em->remove($analyse);
        $em->flush();

        return $this->json($result);
    }
    

}
