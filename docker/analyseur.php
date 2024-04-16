<?php

// Cache pour éviter les appels cURL multiples sur la même URL
$cache = [];

// Fonction pour ajouter "http://" à une URL si nécessaire
function add_http($url) {
    if (strpos($url, '://') === false) {
        $url = 'http://' . $url;
    }
    return $url;
}

// Fonction pour récupérer le contenu d'une URL avec cURL et stocker les résultats dans le cache
function file_get_contents_curl($url) {
    global $cache; // Utilise le cache global
    if (!isset($cache[$url])) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        $cache[$url] = ['content' => $data, 'info' => $info];
    }
    return $cache[$url]['content'];
}

// Fonction pour récupérer le temps de réponse et le statut HTTP en une seule opération
function get_url_info($url) {
    global $cache;
    if (!isset($cache[$url])) {
        file_get_contents_curl($url); // Cela remplit le cache si ce n'est pas déjà fait
    }
    $info = $cache[$url]['info'];

    $path = parse_url($url, PHP_URL_PATH);
    $depth = $path == NULL ? 0 : count(array_filter(explode('/', $path), function($value) { return $value !== ''; }));
    return [
        'http_status' => $info['http_code'],
        'load_time' => $info['total_time'] * 1000, // Temps de chargement en millisecondes
        'depth' => $depth,
    ];
}

// Fonction pour récupérer les liens <a> et <img> d'une URL
function getLinks($url) {
    $urlContent = file_get_contents_curl($url);
    $dom = new DOMDocument();
    @$dom->loadHTML($urlContent);
    $xpath = new DOMXPath($dom);
     
    $base = parse_url($url, PHP_URL_HOST); // Récupérer le domaine de l'URL de base
     
    $internalLinks = [];
    $externalLinks = [];
    $images = [];
     
    $hrefs = $xpath->query("//a[@href]");
    foreach ($hrefs as $href) {
    $hrefValue = $href->getAttribute('href');
    // Vérifier si le lien est complet
    if (filter_var($hrefValue, FILTER_VALIDATE_URL)) {
        $hrefDomain = parse_url($hrefValue, PHP_URL_HOST);
        if (strpos($hrefDomain,$base) !== false) {
            $internalLinks[] = $hrefValue; // Lien interne
        } else {
            $externalLinks[] = $hrefValue; // Lien externe
        }
    } else {
        // Ajouter le domaine si le lien est relatif
            $internalLinks[] = $url . '/' . ltrim($hrefValue, '/');
        }
    }
     
    $srcs = $xpath->query("//img[@src]");
    foreach ($srcs as $src) {
    $images[] = $src->getAttribute('src');
    }
     
    return [
    'internalLinks' => array_unique($internalLinks),
    'externalLinks' => array_unique($externalLinks),
    'images' => array_unique($images)
    ];
}

// Fonction pour analyser une URL sans profondeur
function depth_zero($url) {
    $info = get_url_info($url);
    $linksImages = getLinks($url);
	
    return [
        'url' => $url,
        'depth' => $info['depth'],
        'http_status' => $info['http_status'],
        'load_time' => $info['load_time'],
        'internalLinks' => $linksImages['internalLinks'],
        'externalLinks' => $linksImages['externalLinks'],
        'images' => $linksImages['images'],
    ];
}

// Fonction principale pour analyser une URL et afficher le résultat en JSON
function analyse_simple($url) {
	$url = add_http($url);
	$resultat = depth_zero($url);

	// L'URL de votre API Symfony qui reçoit les résultats
	$urlApiSymfony = 'http://127.0.0.1:8000/resultat';

	// Préparer les données à envoyer
	$donnees = json_encode(['resultats' => $resultat]);
	// Initialiser cURL
	$ch = curl_init($urlApiSymfony);

	// Configurer les options cURL
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $donnees);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
	'Content-Type: application/json',
	'Content-Length: ' . strlen($donnees)
	]);

	// Exécuter la requête
	$response = curl_exec($ch);
	curl_close($ch);

	// Afficher la réponse (pour débogage)
	echo $response;

}

// Utilisation
if (isset($argv[1])) {
    $url = $argv[1];
    analyse_simple($url);
} else {
    echo "Usage: php script.php <url>\n";
}

