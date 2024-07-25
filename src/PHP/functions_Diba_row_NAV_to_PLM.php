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
        'S01MODBOMDATA' as defid,
        'SGRID' as objtype,
        'S01MOD' as doc_defid,
        'S01MODBOMHE1' as parent_defid,
        BOMH2.[doc_code] as doc_code,
        '' as doc_material,
        BOMH2.description as doc_description,
        BOMH2.ordine as parent_ordine,
        BOMH2.[codice] as parent_codice,
        -- row_number() over (partition by BOML.[Production BOM No_] order by BOMH2.ordine) *10 as ordine,
        BOML.[Line No_] as ordine,
        BOML.[No_] as code,
        convert( varchar,cast(cast(BOML.Quantity as DECIMAL(18,6)) as float) ) as qty,
        convert(varchar,cast(cast([Scrap _] as decimal(18,6)) as float) )  as percscarto,
        convert(varchar,cast(cast(BOML.[Gross Qty_]  as DECIMAL(18,6)) as float) ) as qtylord,
        BOML.[Unit of Measure Code] as uom,
        CONCAT(BOML.[Routing Link Code],' ',RL.[Description]) as codecolle,
        rtrim(BOML.[Lead-Time Offset]) as offsetlt,
        CASE BOML.[PFProvided by Vendor] WHEN 1 THEN 'X' ELSE '' END as fornitofor,
        CASE BOML.[Exclude from MRP] WHEN 1 THEN 'X' ELSE '' END as frmp,
        '' as dropped
  FROM  [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Production BOM Line] as BOML (nolock)
        INNER JOIN  (
                        SELECT   
                                I.[No_] as doc_code,
                                row_number() over (partition by I.[No_] order by CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 1 ELSE 2 END, BOMH.[Creation Date]) * 10 as ordine,
                                BOMH.[No_] as codice,
                                BOMH.[Description] as description,
                                I.[Production BOM No_] as Prod_BOM
                          FROM [PP_2017_PROD].[dbo].[Pelletterie Palladio\$Production BOM Header] as BOMH (nolock)
                            INNER JOIN [PP_2017_PROD].dbo.[Pelletterie Palladio\$Item] as I (nolock)
                                ON BOMH.[PFItem No_] = I.[No_] -- AND I.[Production BOM No_] <> I.[No_]
                          WHERE I.[Item Category Code] = 'PF' -- and I.[No_] = 'LVM44875' -- 'LVGI0518'
    --                          order by I.[No_], CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 1 ELSE 2 END -- I.[Production BOM No_]
                        ) as BOMH2
                        ON  BOML.[Production BOM No_] = BOMH2.codice
            INNER JOIN [PP_2017_PROD].dbo.[Pelletterie Palladio\$Routing Link] as RL
                ON BOML.[Routing Link Code] = RL.[Code]
   --  WHERE BOMH2.[doc_code] = 'LVM44875'
    order by BOMH2.codice

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

    // Elenco dei valori ammessi per 'codecolle'
    // $codecolleAmmessi = [
    //     "PRE00 Preparazione",
    //     "ASS00 Assemblaggio",
    //     "CTQ99 CQ - Controllo Kit Componenti",
    //     "TGL00 Taglio"
    // ];

    foreach ($results as $row) {
        // Controlla se 'codecolle' è ammesso
        // if (!in_array($row['codecolle'], $codecolleAmmessi)) {
        //     continue; // Salta questo record se 'codecolle' non è ammesso
        // }

        $dataArray[] = [
            "doc_defid" => $row['doc_defid'], // Main defid
            "doc_code" => $row['doc_code'], // Main doc code
            "code" => $row['code'], // Utilizza direttamente 'code'
            "defid" => $row['defid'], // Object defid
            "siteid" => $row['siteid'],
            "parent_defid" => $row['parent_defid'],
            "parent_ordine" => $row['parent_ordine'],
            "objtype" => $row['objtype'],
            "doc_description" => $row['doc_description'],
            "ordine" => $row['ordine'],
            "parent_codice" => $row['parent_codice'],
            "percscarto" => (float)$row['percscarto'], // Conversione a float
            "qty" => (float)$row['qty'], // Conversione a float
            "qtylord" => (float)$row['qtylord'], // Conversione a float
            "uom" => $row['uom'],
            "codecolle" => $row['codecolle'], // Mappato e solo il codice
            "offsetlt" => $row['offsetlt'], // Gestito in correggiDati
            "fornitofor" => $row['fornitofor'] === 'X', // Conversione a booleano
            "fmrp" => $row['frmp'] === 'X', // Conversione a booleano
        ];
    }

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
function filtraDatiPerCodici($datiPreparati, $codesS01MOD, $parent_codici, $codesS01MAT)
{
    return array_values(array_filter($datiPreparati, function ($item) use ($codesS01MOD, $parent_codici, $codesS01MAT) {
        return in_array($item['doc_code'], $codesS01MOD)
            && in_array($item['parent_codice'], $parent_codici)
            && in_array($item['code'], $codesS01MAT);
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
