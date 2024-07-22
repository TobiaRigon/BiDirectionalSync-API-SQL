<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'get_tkn.php';
require_once 'config.php'; // Questo carica le variabili d'ambiente
require_once 'functions.php';
require_once 'functions_diba.php';

// Definizione delle costanti
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
echo "</br>DIBA dal''API:\n</br>";
print_r($relevantData);
echo "\n</br>-----------------------------</br>\n";

// foreach ($relevantData as $item) {
//     echo "\n</br> 'Dettagli Item'\n";
//     echo json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
//     echo "\n</br>-----------------------------</br>\n";
// }

$filteredData = getRelevantDibaFromSql($relevantData);
echo "</br>Dati filtrati:\n</br>";
print_r($filteredData);
echo "\n</br>-----------------------------</br>\n";


$result = compareApiAndSqlData($relevantData, $filteredData);

echo "</br> Updated Records:\n</br>";
print_r($result['updated']);

echo "</br>Missing Records:\n</br>";
print_r($result['missing']);
echo "\n</br>-----------------------------</br>\n";


// Genera le query di inserimento
$missingRecords = $result['missing'];
$insertQueries = generateInsertQueries($missingRecords);

// Stampa le query di inserimento
echo "Insert Queries:\n\n";
foreach ($insertQueries as $query) {
    echo $query . "\n\n";
}

// Genera le query di aggiornamento
$updatedRecords = $result['updated'];
$updateQueries = generateUpdateQueries($updatedRecords);

// Stampa le query di aggiornamento
echo "Update Queries:\n\n";
foreach ($updateQueries as $query) {
    echo $query . "\n\n";
}
