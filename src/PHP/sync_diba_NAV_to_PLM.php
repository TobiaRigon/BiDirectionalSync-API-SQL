<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'get_tkn.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'functions_diba.php';
require_once 'functions_Diba_NAV_to_PLM.php';

// Definizione delle costanti
define('SITE_ID', 'S01');
define('TABLE_CODE', 'MOD');

// Carica le variabili d'ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

refreshToken(); // Aggiorna Token

$baseUrl = MOD_ENDPOINT;
$token = API_TOKEN; // Recupera il token d'ambiente

// Debug: Assicuriamoci che le variabili d'ambiente siano caricate
echo "Base URL: $baseUrl\n</br>";

if (empty($baseUrl) || empty($token)) {
    die("Errore: Le variabili d'ambiente non sono state caricate correttamente.\n</br>");
}

// Recupera tutti i docid dai prodotti finiti tramite l'API
$docIds = getAllDocIds($baseUrl, $token);

echo "Doc IDs estratti: \n</br>";
foreach ($docIds as $docId) {
    echo $docId . "\n</br>";
}
echo "\n-----------------------------\n</br>";

// Salva i docid in un file per un'analisi successiva
file_put_contents('docids.json', json_encode($docIds));
