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
        $analyse->setDockerNb(0);
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
        $workers_running = $analyse->getDockerNb();
        if (count($links) > 0 && $workers_running < 5) {
            // Launch up to 5 workers simultaneously
            foreach (array_slice($links, 0, 5 - $workers_running) as $link) {
                $command = "docker run --network=host php-url-analyser " . escapeshellarg($link) . " > /dev/null 2>&1 &";
                shell_exec($command);
                $workers_running++;
                $analyse->setDockerNb($workers_running);
            }

            // Remove links that have been handed off to workers
            $remaining_links = array_slice($links, 5);
            $analyse->setLinksToAnalyse($remaining_links);
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
        $workers_running = $analyse->getDockerNb();
        $analyse->setDockerNb($workers_running - 1);
        $current_depth = $content['resultats']['depth'];
    
        $new_links = $content['resultats']['internalLinks'] ?? [];
        $all_found_links = $analyse->getLinksFound() ?? [];
        $updated_found_links = array_unique(array_merge($all_found_links, $new_links));
        $analyse->setLinksFound($updated_found_links);
        $total_images = $analyse->getImagesNbr();
        $analyse->setImagesNbr(($total_images+count($content['resultats']['images'])) ?? 0); // You might also want to accumulate this
        $totalTime = $analyse->getTotalTime()->getTimestamp() + ($content['resultats']['load_time']/1000) ?? 0;
        $analyse->setTotalTime(new \DateTime('@' . $totalTime));
        $existing_links = $analyse->getLinksToAnalyse();
        $updated_links_to_analyse = array_unique(array_merge($existing_links, $new_links));


        $logger->error($current_depth);
        $logger->error($analyse->getDepth());
        if($current_depth < $analyse->getDepth()){
            
            // Accumulate links and other results
            
            $updated_links_to_analyse = array_unique(array_merge($existing_links, $new_links));
            $analyse->setLinksToAnalyse($updated_links_to_analyse);
        }
        
        


        // Check if more links to process or if depth limit reached
        if (count($updated_links_to_analyse) > 0) {
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
            'links_nbr' => count($analyse->getLinksFound()),
            'links_found' => $analyse->getLinksFound(),
            'images_nbr' => $analyse->getImagesNbr(),
            'total_time' => $analyse->getTotalTime()->format('H:i:s'),
            'analyse_en_cours' => $analyse->isAnalyseEnCours()
        ];
    
        // Supprimer l'enregistrement de la base de données
        $em->remove($analyse);
        $em->flush();

        $command = "docker rm $(docker ps -aq) > /dev/null 2>&1 &";
        shell_exec($command);

        return $this->json($result);
    }
    

}
