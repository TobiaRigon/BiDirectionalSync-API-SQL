<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // Assicurati che l'autoloader di Composer sia incluso

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

// Carica il file .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Definisce le costanti immediatamente dopo il caricamento delle variabili di ambiente
define('NOME_SERVER', $_ENV['NOME_SERVER']);
define('UID', $_ENV['UID']);
define('PWD', $_ENV['PWD']);
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
    $client = new Client();
    $url = MOD_ENDPOINT;
    $items = [];
    $skip = 0;
    $limit = 1000; // Assumi che ci sia un limite di 1000 item per pagina

    while (true) {
        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . API_TOKEN,
                ],
                'query' => [
                    'limit' => $limit,
                    'skip' => $skip,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (empty($data)) {
                break;
            }

            $items = array_merge($items, $data);
            $skip += $limit;
        } catch (ClientException $e) {
            echo 'Errore nella chiamata API: ', $e->getMessage(), "\n";
            break;
        }
    }

    return $items;
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
function prepareBulkData($matchedItems, $sqlItems)
{
    $bulkData = [];
    foreach ($sqlItems as $sqlItem) {
        if (in_array($sqlItem['Item'], $matchedItems)) {
            $filePath = realpath($sqlItem['Image_Path']);
            if ($filePath !== false && is_file($filePath)) { // Aggiungi controllo per file esistente e non directory
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

// Funzione per ottenere il tipo MIME del file
function getMimeType($filePath)
{
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    return $mimeType;
}

// Funzione per ottenere l'estensione corretta in base al tipo MIME
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

// Funzione per rinominare il file se necessario
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

// Funzione per inviare i dati all'API
function uploadMedia($bulkData)
{
    $client = new Client();
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

        try {
            $response = $client->request('POST', MEDIA_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . API_TOKEN,
                ],
                'multipart' => [
                    [
                        'name' => 'siteid',
                        'contents' => $data['siteid'],
                    ],
                    [
                        'name' => 'defid',
                        'contents' => $data['defid'],
                    ],
                    [
                        'name' => 'doc_code',
                        'contents' => $data['doc_code'],
                    ],
                    [
                        'name' => 'mediaTitle',
                        'contents' => $data['mediaTitle'],
                    ],
                    [
                        'name' => 'file',
                        'contents' => fopen($imagePath, 'r'),
                        'filename' => basename($imagePath),
                    ],
                ],
            ]);

            $responses[] = json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            echo 'Errore nella chiamata API: ', $e->getMessage(), "\n";
            $responses[] = [
                'status' => 'errore',
                'message' => $e->getMessage(),
            ];
        }
    }

    return $responses;
}
