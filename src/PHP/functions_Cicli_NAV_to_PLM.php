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
                    -- CTE per ottenere le informazioni delle DIBA
            WITH BOMInfo AS (
                SELECT 
                    I.[No_] as doc_code,
                    row_number() OVER (PARTITION BY I.[No_] ORDER BY CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 1 ELSE 2 END, BOMH.[Creation Date]) * 10 as ordine,
                    BOMH.[Description] as ordecolo,
                    BOMH.[No_] as codice,
                    CASE BOMH.Status WHEN 0 THEN 'N' WHEN 1 THEN 'CE' WHEN 2 THEN 'IC' WHEN 3 THEN 'CL' END as stato,
                    CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 'X' ELSE '' END as fproduzione,
                    CASE BOMH.[BOM Complete] WHEN 1 THEN 'X' ELSE '' END as fcompletato
                FROM 
                    [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Production BOM Header] as BOMH (nolock)
                INNER JOIN 
                    [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Item] as I (nolock)
                ON 
                    BOMH.[PFItem No_] = I.[No_]
                WHERE 
                    I.[Item Category Code] = 'PF'
            )

            -- Query principale per ottenere le informazioni dei cicli e unire le DIBA
            SELECT 
                'S01' as siteid,
                'S01MODOPEHEA' as defid,
                'FORM' as objtype,
                'S01MOD' as doc_defid,
                REPLACE(I.[No_],' ','�') as doc_code,
                '' as doc_material,
                H.[Description] as doc_description,
                H.[No_] as codice,
                H.[Description] as descrizione,
                CASE H.[Type] WHEN 0 THEN 'P' WHEN 1 THEN 'S' END as tipo,
                CASE H.[Status] WHEN 0 THEN 'N' WHEN 1 THEN 'C' WHEN 3 THEN 'X' END as stato,
                '' as fcompletato,
                CASE WHEN H.[No_] = I.[Routing No_] THEN 'X' ELSE '' END as fproduzione,  -- Colonna che indica se è in produzione
               -- CAST(BOMInfo.ordine AS NVARCHAR(50)) + ' ' + BOMInfo.ordecolo as dibarif,  -- Colonna che include ordine e ordecolo
                CASE BOMInfo.stato 
                    WHEN 'N' THEN 'N Nuovo'
                    WHEN 'CE' THEN 'CE Certificato'
                    WHEN 'CL' THEN 'CL Chiuso'
                    ELSE BOMInfo.stato 
                END as stato  -- Estende i valori 'N', 'CE' e 'CL'
            FROM 
                [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Item] (nolock) as I
            INNER JOIN 
                [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Routing Header] (nolock) as H
            ON 
                (H.[No_] = I.[No_] OR 
                H.[No_] LIKE I.[No_] + '_M%' OR 
                H.[No_] LIKE I.[No_] + 'V%')
            LEFT JOIN 
                BOMInfo
            ON 
                BOMInfo.doc_code = I.[No_] AND BOMInfo.codice = I.[Production BOM No_]
            WHERE 
                I.[Item Category Code]='PF'  AND I.[No_] = 'LVM44875'
            ORDER BY 
                I.[No_], BOMInfo.ordine;
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
    $produzioneArray = [];
    $nonProduzioneArray = [];

    // Prepara i dati con l'ordine corretto
    foreach ($results as $row) {
        $data = [
            "doc_defid" => $row['doc_defid'], // Main defid
            "doc_code" => $row['doc_code'], // Main doc code
            "codice" => $row['codice'], // Utilizza direttamente 'code'
            "defid" => $row['defid'], // Object defid
            "siteid" => $row['siteid'],
            // "dibarif" => $row['dibarif'],
            "doc_description" => $row['doc_description'],
            "descrizione" => $row['descrizione'], // Conversione a float
            "stato" => $row['stato'], // Gestito in correggiDati
            "tipo" => $row['tipo'], // Conversione a booleano
            "fcompletato" => $row['fcompletato'] === 'X', // Conversione a booleano
            "fproduzione" => $row['fproduzione'] === 'X' // Conversione a booleano
        ];

        // Se 'fproduzione' è true, assegna ordine 10
        if ($data['fproduzione'] === true) {
            $data["ordine"] = 10;
            $produzioneArray[] = $data;
        } else {
            $nonProduzioneArray[] = $data;
        }
    }

    // Combina i dati con 'ordine' prima e poi senza 'ordine'
    $dataArray = array_merge($produzioneArray, $nonProduzioneArray);

    return $dataArray;
}
/**
 * Funzione per recuperare i parent codici dal database
 */
function recuperaParentCodici($serverName, $database, $username, $password)
{
    try {
        // Creazione della connessione PDO
        $conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Definizione della query
        $query = "
        SELECT DISTINCT BOMH.[No_] as codice
        FROM [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Production BOM Header] as BOMH (nolock)
        INNER JOIN [PP_2017_PROD].dbo.[Pelletterie Palladio\$Item] as I (nolock)
            ON BOMH.[PFItem No_] = I.[No_]
        WHERE I.[Item Category Code] = 'PF'
        ORDER BY BOMH.[No_];
        ";

        // Esecuzione della query
        $stmt = $conn->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        return $results;
    } catch (PDOException $e) {
        echo "Errore di connessione: " . $e->getMessage();
        return [];
    }
}
/**
 * Funzione per filtrare i dati in base a doc_code in $codesS01MOD, parent_codice in $parent_codici e code in $codesS01MAT
 */
function filtraDatiPerCodici($datiPreparati, $codesS01MOD)
{
    return array_values(array_filter($datiPreparati, function ($item) use ($codesS01MOD) {
        return in_array($item['doc_code'], $codesS01MOD);
    }));
}
/**
 * Funzione per correggere i dati del campo offsetlt
 */
function correggiDati($dati)
{
    foreach ($dati as &$dato) {
        // Correggi il campo offsetlt
        if (is_null($dato['offsetlt']) || $dato['offsetlt'] == 0) {
            $dato['offsetlt'] = 7;
        } else {
            $dato['offsetlt'] = null;
        }
    }
    return $dati;
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
        $apiUrl = "$baseApiUrl/load_data/$siteid/$defid";

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
