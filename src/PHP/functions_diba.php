<?php
// Funzione per ottenere i codici da aggiornare
function getCodeToUpdate()
{
    $endpoint = getEnvVariable('DIBA_ENDPOINT');
    $token = getEnvVariable('API_TOKEN');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ));
    curl_setopt($ch, CURLOPT_POST, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Errore cURL: ' . curl_error($ch);
        exit();
    }

    curl_close($ch);

    $newDiba = array_map('str_getcsv', explode("\n", $response));
    $codici = array();
    foreach ($newDiba as $row) {
        if ($row[0] != 'Data ultimo aggiornamento;"Codice"' && isset($row[0])) {
            $fields = str_getcsv($row[0], ';');
            if (isset($fields[1])) {
                $codici[] = trim($fields[1], '"');
            }
        }
    }

    return $codici;
}
// Funzione per ottenere i dati dalla pagina API con skip e limit
function getApiPage($skip)
{
    $endpoint = ASSET_API_URL;
    $token = API_TOKEN;
    $url = $endpoint . 'S01MOD' . "?limit=" . PER_PAGE . "&skip=" . $skip;
    $headers = array(
        "Authorization: Bearer " . $token,
        "Content-Type: application/json"
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Errore cURL: ' . curl_error($ch);
        exit();
    }

    curl_close($ch);

    $data = json_decode($response, true);
    // echo $url;
    return $data;
}
// Funzione per ottenere tutti i dati da S01MOD usando skip e limit
function getAllDataFromS01MOD()
{
    $allData = array();
    $skip = 0;

    while (true) {
        $data = getApiPage($skip);

        if (empty($data)) {
            break;
        }

        $allData = array_merge($allData, $data);

        if (count($data) < PER_PAGE) {
            break;
        }

        $skip += PER_PAGE;
    }

    echo "Totale dati recuperati da S01MOD: " . count($allData) . "\n"; // Stampa il numero totale di dati recuperati

    return $allData;
}
// Funzione per ottenere i docid dai codici e creare la mappa docid -> codice
function getDocIdsAndCreateMap($allData, $codici)
{
    $docIds = array();
    $docIdToCodeMap = array();

    foreach ($codici as $codice) {
        $found = false;
        foreach ($allData as $item) {
            if (isset($item['code']) && $item['code'] === $codice) {
                $docIds[] = $item['docid'];
                $docIdToCodeMap[(string)$item['docid']] = $codice;
                $found = true;
                break; // Una volta trovato il codice, possiamo passare al prossimo
            }
        }
        // DEBUG Codici non trovati
        if (!$found) {
            echo "Codice non trovato: $codice\n"; // Stampa se un codice non viene trovato
        }
    }

    return [$docIds, $docIdToCodeMap];
}

// Funzione per ottenere le informazioni dai docid
function getDataFromDocIds($docIds)
{
    $itemsToUpdate = array();
    $token = API_TOKEN;
    $baseUrl = BASE_API_URL . 'documents/S01/S01MOD/';

    foreach ($docIds as $docId) {
        $url = $baseUrl . $docId;
        $headers = array(
            "Authorization: Bearer " . $token,
            "Content-Type: application/json"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true); // Imposta il metodo come POST

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'Errore cURL: ' . curl_error($ch);
            continue;
        }

        curl_close($ch);

        $itemsToUpdate[] = json_decode($response, true);
    }

    return $itemsToUpdate;
}


// Funzione per estrarre i dati rilevanti
function extractRelevantData($itemsToUpdate, $docIdToCodeMap)
{
    // Initialize an empty array to store the extracted _data items
    $relevantData = [];

    // Loop through each item in the input array
    foreach ($itemsToUpdate as $item) {
        // Check if the element has _sections
        if (isset($item['_sections'])) {
            // Initialize an array to collect data for the current item
            $itemData = [];

            // Extract the doc_code from the docIdToCodeMap using the item's _id
            $docId = isset($item['_id']) ? (is_array($item['_id']) ? $item['_id']['_str'] : $item['_id']) : null;
            $docCode = $docId && isset($docIdToCodeMap[(string)$docId]) ? $docIdToCodeMap[(string)$docId] : null;

            // Loop through each section in _sections
            foreach ($item['_sections'] as $section) {
                // Check if the section has _objects
                if (isset($section['_objects'])) {
                    // Loop through each object in _objects
                    foreach ($section['_objects'] as $obj) {
                        // Check if the objtype is SGRID
                        if (isset($obj['objtype']) && $obj['objtype'] === 'SGRID') {
                            // Check if the object has _data
                            if (isset($obj['_data'])) {
                                // Loop through each data item
                                foreach ($obj['_data'] as $dataItem) {
                                    // Filter only the relevant data items
                                    if (!empty($dataItem['ordecolo'])) {
                                        // Create a filtered data item with correct mapping
                                        $filteredDataItem = [
                                            'siteid' => $dataItem['siteid'] ?? null,
                                            'defid' => $dataItem['defid'] ?? null,
                                            'objtype' => $obj['objtype'], // SGRID
                                            'doc_defid' => $dataItem['defid'] ?? null, // Preso da defid nel _data
                                            'doc_code' => $docCode, // Codice dell'item principale
                                            'doc_material' => null, // Not available in the provided data
                                            'doc_description' => $dataItem['ordecolo'] ?? null,
                                            'ordine' => $dataItem['ordine'] ?? null,
                                            'codice' => $dataItem['codice'] ?? null, // Lo stesso di doc_code
                                            'ordecolo' => $dataItem['ordecolo'] ?? null,
                                            'stato' => $dataItem['decode_stato']['display'] ?? null,
                                            'fproduzione' => $dataItem['fproduzione'] ?? null,
                                            'fcompletato' => $dataItem['fcompletato'] ?? null
                                        ];

                                        // Add the filtered data item to the itemData array
                                        $itemData[] = $filteredDataItem;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Add the collected item data to the relevantData array if it has SGRID data
            if (!empty($itemData)) {
                $relevantData[] = [
                    'item_id' => $item['_id'] ?? null,
                    'data' => $itemData
                ];
            }
        }
    }

    return $relevantData;
}
