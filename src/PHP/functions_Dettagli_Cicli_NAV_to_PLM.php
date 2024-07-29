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
        // Commenta AND I.[No_] = 'LVM44875' per eseguire su tutti i prodotti
        $query = "
                 SELECT 
            -- RIGHE CICLO
                    'S01' as siteid,
                    'S01MODOPEDATA' as defid,
                    'SGRID' as objtype,
                    'S01MOD' as doc_deifd,
                    REPLACE(I.[No_],' ','�') as doc_code,
                    L.[Routing Link Code] as routinglc,
                    '' as doc_material,
                    H.Description as 'doc_description',
                    CAST(L.[Operation No_] as int) as sort,
                    CASE ISNULL(L.[Next Operation No_],'') WHEN '' THEN 10 ELSE CAST(L.[Next Operation No_] as int) END as nextope,
                    CASE ISNULL(L.[Previous Operation No_],'') WHEN '' THEN -10 ELSE CAST(L.[Previous Operation No_] as int) END  as prevope,
                    L.[No_] as phase,
                    L.[Work Center No_] as workcent,
                    L.[Work Center Group Code] as workgc,
                    L.[Setup Time] as qty,
                    L.[Routing Link Code] as routinglc,
                    L.[WIP Code] as wipcode,				-- SE OBBLIGATORIO
                    '' as fcost,
                    '' as notes,
                    '' as dropped
            FROM 
                [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Item] (nolock) as I
                    INNER JOIN [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Routing Header] (nolock) as H
                    ON I.[Routing No_] = H.[No_]
                    INNER JOIN 
                    [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Routing Line] (nolock) as L
                        ON H.No_ = L.[Routing No_]
            WHERE I.[Item Category Code] = 'PF'
                AND H.[Type] = 0 AND I.[No_] = 'LVM44875'
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
 * Funzione per estrarre i doc_code dai dati restituiti da recuperaDatiDalDB
 */
function estraiCodici($dati)
{
    $codici = [];

    foreach ($dati as $row) {
        if (isset($row['doc_code'])) {
            $codici[] = $row['doc_code'];
        }
    }

    return $codici;
}

/**
 * Funzione per recuperare docid per ogni doc_code
 */
function recuperaDocId($docCodes, $token)
{
    $docIdArray = [];

    foreach ($docCodes as $docCode) {
        $url = ASSET_API_URL . "S01MOD/?code=$docCode";
        echo $url;
        $response = makeGetRequest($url, $token);

        // Supponiamo che la risposta sia un array e prendiamo il primo elemento
        if (isset($response[0]['docid']) && isset($response[0]['code'])) {
            $docIdArray[] = [
                'doc_code' => $response[0]['code'],
                'docid' => $response[0]['docid']
            ];
        } else {
            echo "Errore nel recupero di docid per il doc_code: $docCode\n";
        }
    }

    return $docIdArray;
}
/**
 * Funzione per preparare i dati nel formato richiesto
 */
function preparaDati($results)
{
    $dataArray = [];

    foreach ($results as $row) {
        $dataArray[] = [
            "defid" => $row['defid'],
            "sort" => (int)$row['sort'], // Key
            "doc_code" => $row['doc_code'], // Main doc code
            "doc_material" => $row['doc_material'],
            "nextope" => (int)$row['nextope'],
            "prevope" => (int)$row['prevope'],
            "phase" => $row['phase'],
            "workcgc" => $row['workcent'],
            "qty" => $row['qty'],
            "routinglc" => $row['routinglc'],
            "wipcode" => $row['wipcode'],
            "fcost" => $row['fcost'],
            "notes" => $row['notes'],
            "dropped" => $row['dropped']
        ];
    }

    return $dataArray;
}

/**
 * Funzione per dividere i dati in batch
 */
function divideInBatch($data, $maxSizeInBytes = MAX_BATCH_SIZE_BYTES) // 50 MB
{
    $batches = [];
    $currentBatch = [];
    $currentBatchSize = 0;

    foreach ($data as $item) {
        $jsonData = json_encode($item);
        $jsonDataSize = strlen($jsonData);

        if ($currentBatchSize + $jsonDataSize > $maxSizeInBytes) {
            $batches[] = $currentBatch;
            $currentBatch = [];
            $currentBatchSize = 0;
        }

        $currentBatch[] = $item;
        $currentBatchSize += $jsonDataSize;
    }

    if (!empty($currentBatch)) {
        $batches[] = $currentBatch;
    }

    return $batches;
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
        $apiUrl = "$baseApiUrl" . "load_data/$siteid/$defid";
        echo 'invio url:' . $apiUrl;
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $token",
            "Connection: keep-alive"
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
function inviaDatiInBatchConRetry($data, $baseApiUrl, $token, $siteid, $defid, $maxRetries = 5)
{
    // Calcola il peso totale dei dati e il numero di batch
    list($totalSizeMB, $numBatches) = calcolaPesoEBatch($data);

    echo "Peso totale del body JSON: " . round($totalSizeMB, 2) . " MB\n";
    echo "Numero di batch: $numBatches\n";

    // Dividi i dati in batch con un limite di 50 MB per batch
    $batches = divideInBatch($data);

    foreach ($batches as $batch) {
        inviaBatchConRetry($batch, $baseApiUrl, $token, $siteid, $defid, $maxRetries);
    }
}
/**
 * Funzione per calcolare il peso del body JSON e il numero di batch
 */
function calcolaPesoEBatch($data, $maxSizeInBytes = MAX_BATCH_SIZE_BYTES) // 50 MB
{
    $batches = divideInBatch($data, $maxSizeInBytes);
    $totalSize = 0;

    foreach ($batches as $batch) {
        $jsonData = json_encode($batch);
        $totalSize += strlen($jsonData);
    }

    $numBatches = count($batches);
    $totalSizeMB = $totalSize / (1024 * 1024); // Converti in MB

    return [$totalSizeMB, $numBatches];
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
