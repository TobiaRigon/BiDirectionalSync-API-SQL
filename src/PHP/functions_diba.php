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

// Funzione per ottenere i docid dai codici
function getDocIds($allData, $codici)
{
    $docIds = array();

    foreach ($codici as $codice) {
        $found = false;
        foreach ($allData as $item) {
            if (isset($item['code']) && $item['code'] === $codice) {
                $docIds[] = $item['docid'];
                $found = true;
                break; // Una volta trovato il codice, possiamo passare al prossimo
            }
        }
        // DEBUG Codici non trovati
        // if (!$found) {
        //     echo "Codice non trovato: $codice\n"; // Stampa se un codice non viene trovato
        // }
    }

    return $docIds;
}
