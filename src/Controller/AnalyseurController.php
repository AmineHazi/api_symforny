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
        // if (strpos($url, '://') === false) {
        //     $url = 'http://' . $url;
        // }
        return $url;
    }

    #[Route('/start_worker', name: 'start_worker', methods: ['POST'])]
    public function startWorker(Request $request, LoggerInterface $logger, EntityManagerInterface $em): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            // Set CORS headers
            $response = new Response();
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '3600');
        }


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
        $links_to_delete = array();
        if (count($links) > 0 && $workers_running < 5) {
            // Launch up to 5 workers simultaneously
            $myfile= fopen("testfile.txt", "a");
            fwrite($myfile,"\r\n------------LIENS QUI VONT ETRE ANALYSES-----------------\r\n");
            fwrite($myfile,print_r(array_slice($links, 0, 5 - $workers_running)));
            fwrite($myfile,"\r\n-----------------------------\r\n");
            fwrite($myfile,"\r\n------------LIENS QUI VONT ETRE ANALYSES-----------------\r\n");
            fclose($myfile);


            foreach (array_slice($links, 0, 5 - $workers_running) as $link) {
                $command = "docker run --network=host php-url-analyser " . escapeshellarg($link) . " > /dev/null 2>&1 &";
                shell_exec($command);
                $workers_running++;

                $links_to_delete[] = $link;

                // Add the link to the analysed links
                $analysed_links = $analyse->getAnalysedLinks();
                $analyse->setAnalysedLinks(array_merge($analysed_links, [$link]));
                $analyse->setDockerNb($workers_running);
            }
            // // Remove links that have been handed off to workers that are in the links_to_delete array
            foreach ($links as $key => $string) {
                // Check if the string is present in $links_to_delete
                if (in_array($string, $links_to_delete)) {
                    // Remove the string from $links
                    unset($links[$key]);
                }
            }
            //$analyse->setLinksToAnalyse($links);

            //**********************************
        $myfile = fopen("testfile.txt", "a");

        fwrite($myfile,"\r\n------------LIENS A SUPP-----------------\r\n");
        fwrite($myfile,implode($links_to_delete));
        fwrite($myfile,"\r\n-----------------------------\r\n");
        fclose($myfile);
        //***
            
            $analyse->setLinksToAnalyse($links);
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
        //récupérer le nombre de workers en cours
        $workers_running = $analyse->getDockerNb();

        // décrementer le nombre de workers en cours
        $analyse->setDockerNb($workers_running - 1);

        // récupère la profondeur du lien analysé 
        $current_depth = $content['resultats']['depth'];
    
        // les liens internes trouvés dans la page
        $new_links = $content['resultats']['internalLinks'] ?? [];
        // Recupère de la bdd les liens déjà trouvés (TOUS LES LIENS)
        $all_found_links = $analyse->getLinksFound() ?? [];

        // on rajoute les liens trouver au liens déjà existant dans la bdd
        $updated_found_links = array_unique(array_merge($all_found_links, $new_links));
        //**********************************
        $myfile = fopen("testfile.txt", "a");
        fwrite($myfile,"\r\nLIENS TROUVES DANS LA PAGE\r\n");

        fwrite($myfile,implode($new_links));
        fwrite($myfile,"\r\n-----------------------------\r\n");
        fclose($myfile);
        //**********************************

        $analyse->setLinksFound($updated_found_links);
        
        // récupérer les images et additionner les nouvelles imgs trouvées
        $total_images = $analyse->getImagesNbr();
        $analyse->setImagesNbr(($total_images+count($content['resultats']['images'])) ?? $total_images); // You might also want to accumulate this
        
        // récupérer le temps total et additionner le temps de chargement de la page
        // FAUSSE À REGLER
        $totalTime = $analyse->getTotalTime()->getTimestamp() + ($content['resultats']['load_time']/1000) ?? 0;
        $analyse->setTotalTime(new \DateTime('@' . $totalTime));
        
        // récupère les liens à analyser de la bdd
        $to_be_analysed_links = $analyse->getLinksToAnalyse();
        
        // récupère les liens déja analyser depuis la bdd
        $analysed_links = $analyse->getAnalysedLinks();

        // garder que les liens qui n''ont pas été analysé 
        $links_found_to_be_analysed = array();

        foreach ($new_links as $string) {
            // Check if the string is not present in to_be_analysed_links
            if (!in_array($string, $analysed_links)) {
                // Add the string to array1
                $links_found_to_be_analysed[] = $string;
            }
        }



        $logger->error($current_depth);
        $logger->error($analyse->getDepth());
        $updated_links_to_analyse = array_unique(array_merge($to_be_analysed_links, $links_found_to_be_analysed));
        if($current_depth < $analyse->getDepth()){
            
            // Accumulate links and other results
            
            $analyse->setLinksToAnalyse($updated_links_to_analyse);
        }
        
        


        // Check if more links to process or if depth limit reached
        if (count($updated_links_to_analyse) > 0 && $current_depth < $analyse->getDepth()) {
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
