<?php
require_once 'functions_sync.php'; // Include le funzioni e la configurazione necessarie
require_once 'get_tkn.php';

define('TRACKING_FILE', './../json/uploaded_images.json'); // File JSON per tracciare le immagini caricate

refreshToken(); // Aggiorna Token

// Recupera le immagini con le relative Source Primary Key 1
$sqlItems = getSqlItemsWithImages();

// Recupera tutti gli item da MOD API
$apiItems = getApiItems();

// Trova gli item corrispondenti tra SQL e API
$matchedItems = findMatchingItems($sqlItems, $apiItems);

// Carica il file JSON
$uploadedImages = loadUploadedImages(TRACKING_FILE);

// Prepara i dati da inviare in bulk all'API
$bulkData = prepareBulkData($matchedItems, $sqlItems, $uploadedImages);

// Stampa l'endpoint e i dati da inviare per il debug
echo "Endpoint: " . MEDIA_ENDPOINT . "\n";
echo "Dati da inviare:\n";
echo json_encode($bulkData, JSON_PRETTY_PRINT) . "\n";

// Esegui la chiamata all'API e stampa la risposta
$responses = uploadMedia($bulkData, $uploadedImages);
echo "Risposte dell'API:\n";
echo json_encode($responses, JSON_PRETTY_PRINT) . "\n";

// Salva il file JSON aggiornato
saveUploadedImages(TRACKING_FILE, $uploadedImages);

function loadUploadedImages($filename)
{
    if (!file_exists($filename)) {
        return [];
    }
    $data = file_get_contents($filename);
    return json_decode($data, true);
}

function saveUploadedImages($filename, $uploadedImages)
{
    $data = json_encode($uploadedImages, JSON_PRETTY_PRINT);
    file_put_contents($filename, $data);
}
