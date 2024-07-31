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
                AND H.[Type] = 0  AND I.[No_] = 'LVM44875'
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
        if (isset($row['doc_code']) && !in_array($row['doc_code'], $codici)) {
            $codici[] = $row['doc_code'];
        }
    }

    return $codici;
}

/**
 * Funzione per preparare i dati nel formato richiesto, includendo anche il docid
 */
function preparaDati($results)
{
    $dataArray = [];

    // Definisci la mappatura dei campi routinglc
    $routinglcMapping = [
        'ASS00' => 'ASS00 Assemblaggio',
        'ASS01' => 'ASS01 Assemblaggio Angoli',
        'ASS02' => 'ASS02 Assemblaggio Tracolla',
        'ASS03' => 'ASS03 Assemblaggio Maniglia',
        'ASS05' => 'ASS05 Assemblaggio Portanome',
        'ASS06' => 'ASS06 Assemblaggio Filetto',
        'ASS07' => 'ASS07 Assemblaggio Componenti',
        'ASS08' => 'ASS08 Assemblaggio Fodera',
        'ASS09' => 'ASS09 Assemblaggio Quadrante',
        'BV113' => 'BV113 Taglio Pelle Liscio',
        'BV114' => 'BV114 Tg Pelle Intrecciato',
        'BV132' => 'BV132 Taglio Fodere Infust',
        'BV185' => 'BV185 Tg Pelle Strong',
        'BV231' => 'BV231 Trancia',
        'BV244' => 'BV244 Ricamo',
        'BV333' => 'BV333 Assemblaggio',
        'BV435' => 'BV435 Intreccio 9X12',
        'CCO00' => 'CCO00 Colore a costa',
        'CCO01' => 'CCO01 Colore a Costa',
        'CCO99' => 'CCO99 Colore a Costa Finale',
        'CLAAS00' => 'CLAAS00 C/L Att. Assemblaggio',
        'CLACO00' => 'CLACO00 C/L Att. Colore',
        'CLAPR00' => 'CLAPR00 C/L Att. Preparazione',
        'CLASP00' => 'CLASP00 C/L Att. Spedizione',
        'CLAST00' => 'CLAST00 C/L Att. Embossage',
        'CLATG00' => 'CLATG00 C/L Att. Taglio',
        'CNT00' => 'CNT00 Conta',
        'CNT01' => 'CNT01 Conta 1',
        'CTQ98' => 'CTQ98 CQ - Controllo Kit Componenti',
        'CTQ99' => 'CTQ99 CQ - Controllo Kit Componenti',
        'IMB00' => 'IMB00 Imballaggio',
        'KTI00' => 'KTI00 Kitting Imballo',
        'PRA00' => 'PRA00 Pre-Assemblaggio Borsa',
        'PRA08' => 'PRA08 Pre-Assemblaggio Fodera',
        'PRE00' => 'PRE00 Preparazione',
        'PRE001' => 'PRE001 Preparazione 2',
        'PRE01' => 'PRE01 Preparazione Angoli',
        'PRE02' => 'PRE02 Preparazione Tracolla',
        'PRE03' => 'PRE03 Preparazione Maniglia',
        'PRE04' => 'PRE04 Preparazione Pannello',
        'PRE05' => 'PRE05 Preparazione Portanome',
        'PRE07' => 'PRE07 Preparazione Componenti',
        'PRE08' => 'PRE08 Preparazione Fodera',
        'PRE09' => 'PRE09 Preparazione Quadrante',
        'PSS00' => 'PSS00 Post-Assemblaggio',
        'STA04' => 'STA04 Stampa Pannello',
        'TGL00' => 'TGL00 Taglio',
        'TGL01' => 'TGL01 Taglio Alex',
        'TGL05' => 'TGL05 Taglio Fodera',
        'TGL09' => 'TGL09 Taglio Esotico'
    ];

    // Definisci la mappatura dei campi wipcode
    $wipcodeMapping = [
        'ASS-000' => 'ASS-000 BORSA ASSEMBLATA',
        'ASS-001' => 'ASS-001 ANGOLI ASSEMBLATI',
        'ASS-002' => 'ASS-002 TRACOLLA ASSEMBLATA',
        'ASS-003' => 'ASS-003 MANIGLIA ASSEMBLATA',
        'ASS-005' => 'ASS-005 PORTANOME ASSEMBLATO',
        'ASS-007' => 'ASS-007 COMPONENTI ASSEMBLATI',
        'ASS-008' => 'ASS-008 FODERA ASSEMBLATA',
        'ASS-009' => 'ASS-009 BORSA PREASSEMBLATA',
        'ASS-010' => 'ASS-010 PORTANOME ASSEMBLATO',
        'ASS-100' => 'ASS-100 BORSA ASSEMBLATA + KIT',
        'ASS-110' => 'ASS-110 BORSA CONTROLLATA',
        'ASS-111' => 'ASS-111 SWATCH ASSEMBLATO',
        'ASS-120' => 'ASS-120 BORSA CONTROLLATA',
        'CCO-000' => 'CCO-000 PELLE COLORATA',
        'CCO-001' => 'CCO-001 BORSA COLORATA',
        'CCO-002' => 'CCO-002 TRACOLLA COLORATA',
        'CCO-003' => 'CCO-003 MANIGLIA COLORATA',
        'CCO-0031' => 'CCO-0031 MANIGLIA COLORATA',
        'CCO-005' => 'CCO-005 PORTANOME COLORATO',
        'CCO-0051' => 'CCO-0051 PORTANOME COLORATO',
        'CCO-007' => 'CCO-007 COMPONENTI COLORATI',
        'CCO-0071' => 'CCO-0071 COMPONENTI COLORATI',
        'CCO-008' => 'CCO-008 FODERA COLORATA',
        'CQU-000' => 'CQU-000 PRODOTTO CONTROLLATO',
        'EMB-004' => 'EMB-004 PANNELLO EMBOSSATO',
        'PRA-000' => 'PRA-000 BORSA PRE-ASSEMBLATA',
        'PRA-003' => 'PRA-003 MANIGLIA PRE-ASSEMBLATA',
        'PRA-007' => 'PRA-007 TIRETTO PRE-ASSEMBLATO',
        'PRE-000' => 'PRE-000 BORSA PREPARATA',
        'PRE-001' => 'PRE-001 ANGOLI PREPARATI',
        'PRE-0011' => 'PRE-0011 BORSA PREPARATA',
        'PRE-002' => 'PRE-002 TRACOLLA PREPARATA',
        'PRE-0021' => 'PRE-0021 TRACOLLA PREPARATA',
        'PRE-003' => 'PRE-003 MANIGLIA PREPARATA',
        'PRE-0031' => 'PRE-0031 MANIGLIA PREPARATA',
        'PRE-004' => 'PRE-004 PANNELLO PREPARATO',
        'PRE-005' => 'PRE-005 PORTANOME PREPARATO',
        'PRE-0051' => 'PRE-0051 PORTANOME PREPARATO',
        'PRE-007' => 'PRE-007 COMPONENTI PREPARATI',
        'PRE-0071' => 'PRE-0071 COMPONENTI PREPARATI',
        'PRE-008' => 'PRE-008 FODERA PREPARATA',
        'PRE-009' => 'PRE-009 QUADRANTE PREPARATO',
        'PRE-010' => 'PRE-010 PELLE PREPARATA',
        'RIP-000' => 'RIP-000 BORSA DA RIPARARE',
        'STA-004' => 'STA-004 PANNELLO STAMPATO',
        'TGL-000' => 'TGL-000 PELLE TAGLIATA',
        'TGL-001' => 'TGL-001 ANGOLI TAGLIATI',
        'TGL-002' => 'TGL-002 TRACOLLA TAGLIATA',
        'TGL-003' => 'TGL-003 MANIGLIA TAGLIATA',
        'TGL-004' => 'TGL-004 PANNELLO TAGLIATO',
        'TGL-005' => 'TGL-005 PORTANOME TAGLIATO',
        'TGL-006' => 'TGL-006 FILETTO TAGLIATO',
        'TGL-007' => 'TGL-007 COMPONENTI TAGLIATI',
        'TGL-008' => 'TGL-008 FODERA TAGLIATA',
        'TGL-009' => 'TGL-009 QUADRANTE TAGLIATO',
        'TGL-010' => 'TGL-010 PELLE ESOTICA',
        'TGL-011' => 'TGL-011 MATERIALE TAGLIATO (Ex-A676)',
        'TGL-012' => 'TGL-012 RINFORZI TAGLIATI',
        'TGL-998' => 'TGL-998 MATERIALE DA TAGLIO (Ex-A001)',
        'TGL-999' => 'TGL-999 MATERIALE DA TAGLIO (Ex-A003)'
    ];

    foreach ($results as $row) {
        $doc_code = $row['doc_code'];
        $docid = isset($docIdMap[$doc_code]) ? $docIdMap[$doc_code] : null;

        $routinglc = isset($routinglcMapping[$row['routinglc']]) ? $routinglcMapping[$row['routinglc']] : $row['routinglc'];
        $wipcode = isset($wipcodeMapping[$row['wipcode']]) ? $wipcodeMapping[$row['wipcode']] : $row['wipcode'];

        $dataArray[] = [
            "defid" => $row['defid'],
            "sort" => (int)$row['sort'], // Key
            "doc_code" => $doc_code, // Main doc code
            "doc_defid" => $row['doc_deifd'], // Add the docid corresponding to the doc_code
            "doc_material" => $row['doc_material'],
            "nextope" => (int)$row['nextope'],
            "prevope" => (int)$row['prevope'],
            "phase" => $row['phase'],
            "workcgc" => $row['workgc'],
            "qty" => $row['qty'],
            // Usa mappatura campi completi
            // "routinglc" => $routinglc, // Use mapped routinglc value
            // "wipcode" => $wipcode, // Use mapped wipcode value
            // Usa campi grezzi
            "routinglc" => $row['routinglc'], // Use row routinglc value
            "wipcode" => $row['wipcode'], // Use row wipcode value

            "fcost" => $row['fcost'] === 'X',
            "notes" => $row['notes'],
            "dropped" => $row['dropped'] === 'X'
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
