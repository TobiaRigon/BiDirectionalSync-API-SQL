<?php
require_once 'functions_sync.php'; // Include le funzioni e la configurazione necessarie
require_once 'get_tkn.php';
refreshToken(); // Aggiorna Token
// Recupera le immagini con le relative Source Primary Key 1
$sqlItems = getSqlItemsWithImages();

// Recupera tutti gli item da MOD API
$apiItems = getApiItems();

// Trova gli item corrispondenti tra SQL e API
$matchedItems = findMatchingItems($sqlItems, $apiItems);

// Prepara i dati da inviare in bulk all'API
$bulkData = prepareBulkData($matchedItems, $sqlItems);

// Stampa l'endpoint e i dati da inviare per il debug
echo "Endpoint: " . MEDIA_ENDPOINT . "\n";
echo "Dati da inviare:\n";
echo json_encode($bulkData, JSON_PRETTY_PRINT) . "\n";

// Esegui la chiamata all'API e stampa la risposta
$responses = uploadMedia($bulkData);
echo "Risposte dell'API:\n";
echo json_encode($responses, JSON_PRETTY_PRINT) . "\n";
