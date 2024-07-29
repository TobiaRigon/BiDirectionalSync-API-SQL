<?php

/**
 * Funzione per recuperare i dati dal database
 */
function recuperaDatiDalDB($serverName, $database, $username, $password)
{
    try {
        // Creazione della connessione PDO
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Definizione della query
        $query = "
        SELECT  'S01' as siteid,
                'S01MODBOMHE1' as defid,
                'SGRID' as objtype,
                'S01MOD' as doc_defid,
                I.[No_] as doc_code,
                '' as doc_material,
                BOMH.[Description] as doc_description,
                row_number() over (partition by I.[No_] order by CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 1 ELSE 2 END, BOMH.[Creation Date]) * 10 as ordine,
                BOMH.[No_] as codice,
                BOMH.[Description] as ordecolo,
                CASE BOMH.Status WHEN 0 THEN 'N' WHEN 1 THEN 'CE' WHEN 2 THEN 'IC' WHEN 3 THEN 'CL' END as stato,
                CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 'X' ELSE '' END as fproduzione,
                CASE BOMH.[BOM Complete] WHEN 1 THEN 'X' ELSE '' END as fcompletato
        FROM [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Production BOM Header] as BOMH (nolock)
        INNER JOIN [PP_2017_PROD].dbo.[Pelletterie Palladio\$Item] as I (nolock)
            ON BOMH.[PFItem No_] = I.[No_] -- AND I.[Production BOM No_] <> I.[No_]
        WHERE I.[Item Category Code] = 'PF'  and I.[No_] = 'LVM44875' -- 'LVGI0518'
        order by I.[No_], CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 1 ELSE 2 END -- I.[Production BOM No_]
        ";

        // Esecuzione della query
        $stmt = $conn->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    } catch (PDOException $e) {
        echo "Errore di connessione: " . $e->getMessage();
        return [];
    }
}

/**
 * Funzione per preparare i dati nel formato richiesto
 */
function preparaDati($results)
{
    $dataArray = [];

    foreach ($results as $row) {
        $dataArray[] = [
            "doc_defid" => $row['doc_defid'], // Main defid
            "doc_code" => $row['doc_code'], // Main doc code
            "doc_material" => $row['doc_material'], // Main doc material
            "defid" => $row['defid'], // Object defid
            "siteid" => $row['siteid'],
            "objtype" => $row['objtype'],
            "doc_description" => $row['doc_description'],
            "ordine" => $row['ordine'],
            "codice" => $row['codice'],
            "ordecolo" => $row['ordecolo'],
            "stato" => $row['stato'],
            "fproduzione" => $row['fproduzione'],
            "fcompletato" => $row['fcompletato']
        ];
    }

    return $dataArray;
}

/**
 * Funzione per filtrare i dati in base ai doc_code presenti in $codici
 */
function filtraDatiPerCodici($datiPreparati, $codici)
{
    return array_values(array_filter($datiPreparati, function ($item) use ($codici) {
        return in_array($item['doc_code'], $codici);
    }));
}

/**
 * Funzione per filtrare e correggere i dati
 */
function filtraECorreggiDati($dati)
{
    $datiCorretti = [];
    $produzionePerDocCode = [];

    foreach ($dati as $item) {
        // Controlla e correggi il campo 'stato'
        if (!isset($item['stato']) || !in_array($item['stato'], ['N', 'CE', 'CL'])) {
            echo "Errore: campo 'stato' non valido per il doc_code " . $item['doc_code'] . "\n";
            continue;
        }

        // Gestisci il flag 'fproduzione'
        if (isset($item['fproduzione']) && $item['fproduzione'] === 'X') {
            if (!isset($produzionePerDocCode[$item['doc_code']])) {
                $produzionePerDocCode[$item['doc_code']] = 0;
            }
            $produzionePerDocCode[$item['doc_code']]++;

            if ($produzionePerDocCode[$item['doc_code']] > 1) {
                echo "Errore: più di una riga con flag 'fproduzione' per il doc_code " . $item['doc_code'] . "\n";
                continue;
            }
        }

        $datiCorretti[] = $item;
    }

    return $datiCorretti;
}

/**
 * Funzione per dividere i dati in batch
 */
function divideInBatch($data, $batchSize)
{
    return array_chunk($data, $batchSize);
}

/**
 * Funzione per inviare un batch di dati all'API con retry e backoff esponenziale solo in caso di errore 502
 */
function inviaBatchConRetry($batch, $baseApiUrl, $token, $siteid, $defid, $maxRetries = 5)
{
    $attempt = 0;
    $success = false;

    while ($attempt < $maxRetries && !$success) {
        $jsonData = json_encode($batch, JSON_PRETTY_PRINT);
        $apiUrl = "$baseApiUrl/load_data/$siteid/$defid";
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $token"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            echo "Batch inviato con successo\n";
            $success = true;
        } elseif ($httpCode == 502) {
            $attempt++;
            $waitTime = pow(2, $attempt); // Backoff esponenziale
            echo "Errore 502 nell'invio del batch: Retry $attempt/$maxRetries in $waitTime seconds...\n";
            sleep($waitTime);
        } else {
            echo "Errore nell'invio del batch: HTTP $httpCode - $response\n";
            break; // Uscire dal loop per errori diversi da 502
        }
    }

    if (!$success) {
        echo "Errore nell'invio del batch \n";
    }
}

/**
 * Funzione per inviare i dati all'API in batch con retry condizionale
 */
function inviaDatiInBatchConRetry($data, $baseApiUrl, $token, $siteid, $defid, $batchSize, $maxRetries = 5)
{
    // Dividi i dati in batch
    $batches = divideInBatch($data, $batchSize);

    foreach ($batches as $batch) {
        inviaBatchConRetry($batch, $baseApiUrl, $token, $siteid, $defid, $maxRetries);
    }
}

function fetchAllCodes($baseUrl, $token)
{
    $codes = [];
    $skip = 0;
    $limit = 1000; // Imposta il limite di risultati per ogni richiesta
    $hasMoreData = true;

    while ($hasMoreData) {
        $url = "$baseUrl?skip=$skip&limit=$limit";
        $response = makeGetRequest($url, $token);

        // Debug: Stampa la risposta per vedere cosa viene restituito
        echo "URL: $url\n";
        // echo "Response: " . json_encode($response) . "\n";

        if ($response && is_array($response)) {
            foreach ($response as $item) {
                if (isset($item['code'])) {
                    $codes[] = $item['code'];
                }
            }
            // Controlla se ci sono più dati da recuperare
            $hasMoreData = count($response) == $limit;
            $skip += $limit;
        } else {
            $hasMoreData = false; // Termina il loop se non ci sono più dati
        }
    }

    return $codes;
}

function makeGetRequest($url, $token)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);

    // Debug: Stampa eventuali errori cURL
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    curl_close($ch);

    return json_decode($response, true);
}


// Funzione per recuperare i codici da un dato endpoint
function getCodesFromEndpoint($endpoint, $token)
{
    $baseUrl = ASSET_API_URL . $endpoint;
    echo "Base URL: $baseUrl\n";
    return fetchAllCodes($baseUrl, $token);
}
