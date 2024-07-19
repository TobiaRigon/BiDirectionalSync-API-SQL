<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'get_tkn.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'functions_diba.php';

define('SITE_ID', 'S01');
define('TABLE_CODE', 'MOD');

refreshToken(); // Aggiorna Token

// Recupera tutti i codici
$codici = getCodeToUpdate();
echo "Codici estratti: \n";
print_r($codici);
echo "\n</br>-----------------------------</br>\n";

// Recupera tutti i dati da S01MOD
$allData = getAllDataFromS01MOD();
// echo "Dati recuperati da S01MOD: \n";
// print_r($allData);

// Recupera i docid per i codici specificati e crea la mappa doc_id -> code
list($docIds, $docIdToCodeMap) = getDocIdsAndCreateMap($allData, $codici);
echo "Doc IDs estratti: \n";
print_r($docIds);
echo "\n</br>-----------------------------</br>\n";

// Ottieni le informazioni dai docid
$itemsToUpdate = getDataFromDocIds($docIds);

// Estrai i dati rilevanti usando la mappa
$relevantData = extractRelevantData($itemsToUpdate, $docIdToCodeMap);

foreach ($relevantData as $item) {
    echo "\n</br> 'Dettagli Item'\n";
    echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n</br>-----------------------------</br>\n";
}
