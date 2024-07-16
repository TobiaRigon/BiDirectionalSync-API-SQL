<?php
ini_set('max_execution_time', 600); // Aumenta il tempo massimo di esecuzione a 600 secondi

require_once __DIR__ . '/../../vendor/autoload.php'; // Assicurati che l'autoloader di Composer sia incluso

use Dotenv\Dotenv;

// Carica il file .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Definisce le costanti immediatamente dopo il caricamento delle variabili di ambiente
define('API_TOKEN', $_ENV['API_TOKEN']);
define('MOD_ENDPOINT', $_ENV['MOD_ENDPOINT']);
define('MEDIA_ENDPOINT', $_ENV['MEDIA_ENDPOINT']);

// Funzione per recuperare le immagini con le relative Source Primary Key 1
function getSqlItemsWithImages()
{
    $serverName = NOME_SERVER;
    $connectionInfo = array("UID" => UID, "PWD" => PWD, "Database" => "PP_2017_TST");
    $conn = sqlsrv_connect($serverName, $connectionInfo);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $sql = "SELECT [Source Primary Key 1] as Item, [File Name] as Image_Path
            FROM [PP_2017_TST].[dbo].[Pelletterie Palladio\$PFHyperlink]
            WHERE [Source Primary Key 1] IS NOT NULL";

    $stmt = sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $items = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $items[] = $row;
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    return $items;
}

// Funzione per recuperare tutti gli item da MOD API
function getApiItems()
{
    $items = [];
    $skip = 0;
    $limit = 1000; // Assumi che ci sia un limite di 1000 item per pagina

    while (true) {
        $response = makeApiRequest(MOD_ENDPOINT, [
            'limit' => $limit,
            'skip' => $skip,
        ]);

        if (empty($response)) {
            break;
        }

        $items = array_merge($items, $response);
        $skip += $limit;
    }

    return $items;
}

function makeApiRequest($url, $queryParams)
{
    $ch = curl_init();
    $url = sprintf("%s?%s", $url, http_build_query($queryParams));

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . API_TOKEN,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Errore nella chiamata API: ' . curl_error($ch) . "\n";
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

// Funzione per trovare gli item corrispondenti tra SQL e API
function findMatchingItems($sqlItems, $apiItems)
{
    $apiItemsMap = [];
    foreach ($apiItems as $item) {
        $apiItemsMap[$item['code']] = $item;
    }

    $matchedItems = [];
    foreach ($sqlItems as $sqlItem) {
        if (isset($apiItemsMap[$sqlItem['Item']])) {
            $matchedItems[] = $sqlItem['Item'];
        }
    }

    return $matchedItems;
}

// Funzione per preparare i dati da inviare in bulk all'API
function prepareBulkData($matchedItems, $sqlItems, $uploadedImages)
{
    $bulkData = [];
    foreach ($sqlItems as $sqlItem) {
        if (in_array($sqlItem['Item'], $matchedItems)) {
            $filePath = realpath($sqlItem['Image_Path']);
            if ($filePath !== false && is_file($filePath)) { // Aggiungi controllo per file esistente e non directory
                if (isset($uploadedImages[$filePath])) {
                    // Salta il caricamento se l'immagine è già stata caricata
                    continue;
                }

                // Controlla il tipo MIME e l'estensione del file
                $mimeType = getMimeType($filePath);
                $correctExt = getCorrectExtension($mimeType);
                if ($correctExt !== null) {
                    $filePath = renameFileIfNecessary($filePath, $correctExt);
                    $bulkData[] = [
                        'siteid' => 'S01',
                        'defid' => 'S01MOD',
                        'doc_code' => $sqlItem['Item'],
                        'mediaTitle' => basename($filePath),
                        'file' => $filePath,
                    ];
                } else {
                    echo "Tipo MIME non supportato per il file: " . $filePath . "\n";
                }
            } else {
                echo "Percorso non valido o non è un file: " . $sqlItem['Image_Path'] . "\n";
            }
        }
    }
    return $bulkData;
}

// Funzione per inviare i dati all'API
function uploadMedia($bulkData, &$uploadedImages)
{
    $responses = [];

    foreach ($bulkData as $data) {
        $imagePath = $data['file'];
        $mimeType = getMimeType($imagePath);
        $correctExt = getCorrectExtension($mimeType);

        if ($correctExt === null) {
            echo "Tipo MIME non supportato per il file: $imagePath\n";
            continue;
        }

        $imagePath = renameFileIfNecessary($imagePath, $correctExt);

        $response = makeCurlRequest(MEDIA_ENDPOINT, [
            'siteid' => $data['siteid'],
            'defid' => $data['defid'],
            'doc_code' => $data['doc_code'],
            'mediaTitle' => $data['mediaTitle'],
            'file' => new CURLFile($imagePath, $mimeType, basename($imagePath))
        ]);

        if ($response) {
            $responses[] = $response;
            // Aggiorna il file JSON con il nuovo percorso dell'immagine
            $uploadedImages[$imagePath] = true;
        } else {
            $responses[] = [
                'status' => 'errore',
                'message' => 'Errore nella chiamata API',
            ];
        }
    }

    return $responses;
}

function makeCurlRequest($url, $postData)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . API_TOKEN,
    ]);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Errore nella chiamata API: ' . curl_error($ch) . "\n";
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

function getMimeType($filePath)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    return $mimeType;
}

function getCorrectExtension($mimeType)
{
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/webp' => 'webp'
    ];
    return $extensions[$mimeType] ?? null;
}

function renameFileIfNecessary($filePath, $correctExt)
{
    $currentExt = pathinfo($filePath, PATHINFO_EXTENSION);
    if ($currentExt !== $correctExt) {
        $newPath = pathinfo($filePath, PATHINFO_DIRNAME) . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.' . $correctExt;
        if (!rename($filePath, $newPath)) {
            throw new Exception("Error renaming file from $filePath to $newPath");
        }
        return $newPath;
    }
    return $filePath;
}
