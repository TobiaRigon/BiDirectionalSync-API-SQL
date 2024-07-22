<?php
$columnMapping = [
    'siteid' => '[Site ID]',
    'defid' => '[Definition ID]',
    'objtype' => '[Object Type]',
    'doc_defid' => '[Document Definition ID]',
    'doc_code' => '[Document Code]',
    'doc_material' => '[Document Material]',
    'doc_description' => '[Document Description]',
    'ordine' => '[Order]',
    'codice' => '[Code]',
    'ordecolo' => '[Description]',
    'stato' => '[Status]',
    'fproduzione' => '[Production Flag]',
    'fcompletato' => '[Completion Flag]'
];
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
                                            'fcompletato' => $dataItem['fcompletato'] ?? null,
                                            'updated_ts' => $dataItem['updated']['ts'] ?? null // Aggiungi il timestamp di aggiornamento
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
function getRelevantDocCodesFromData($relevantData)
{
    $docCodes = [];

    foreach ($relevantData as $item) {
        foreach ($item['data'] as $dataItem) {
            $docCodes[] = $dataItem['doc_code'];
        }
    }

    // Rimuovi eventuali duplicati
    $docCodes = array_unique($docCodes);

    // Debug: stampa i doc_code
    echo "Relevant Doc Codes:\n";
    print_r($docCodes);

    return $docCodes;
}
function getRelevantDibaFromSql($relevantData)
{
    // Recupera i doc_code da relevantData
    $relevantDocCodes = getRelevantDocCodesFromData($relevantData);

    // Definisci il server, l'utente, la password e il database
    $serverName = NOME_SERVER;
    $connectionInfo = array(
        "UID" => UID,
        "PWD" => PWD,
        "Database" => DB_TKN
    );

    // Connessione al database SQL Server
    $conn = sqlsrv_connect($serverName, $connectionInfo);

    // Verifica la connessione
    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Converti l'array di doc_code in una stringa per la query SQL
    $docCodesString = implode("','", $relevantDocCodes);

    // Query SQL per ottenere i dati
    $sql = "
        SELECT 
            'S01' as siteid,
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
            CASE BOMH.[BOM Complete] WHEN 1 THEN 'X' ELSE '' END as fcompletato,
            BOMH.[Last Date Modified] as updated_ts
        FROM [PP_2017_TST].[dbo].[Pelletterie Palladio\$Production BOM Header] as BOMH (nolock)
        INNER JOIN [PP_2017_TST].dbo.[Pelletterie Palladio\$Item] as I (nolock)
            ON BOMH.[PFItem No_] = I.[No_]
        WHERE I.[Item Category Code] = 'PF'
            AND BOMH.[Last Date Modified] >= '2024-01-01'
            AND I.[No_] IN ('$docCodesString')
        ORDER BY I.[No_], CASE I.[Production BOM No_] WHEN BOMH.No_ THEN 1 ELSE 2 END
    ";

    // Esegui la query
    $stmt = sqlsrv_query($conn, $sql);

    // Verifica se la query è fallita
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $mssqlData = [];

    // Fetch dei dati ottenuti dalla query
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Converti il valore in UTF-8 se è una stringa
        $row = array_map(function ($value) {
            return is_string($value) ? convertToUtf8($value) : $value;
        }, $row);
        $mssqlData[] = $row;
    }

    // Libera le risorse del risultato
    sqlsrv_free_stmt($stmt);

    // Chiudi la connessione al database
    sqlsrv_close($conn);

    return $mssqlData;
}
function compareApiAndSqlData($apiData, $sqlData)
{
    $updatedRecords = [];
    $missingRecords = [];

    // Creare un array associativo per i dati SQL con doc_code come chiave
    $sqlDataAssoc = [];
    foreach ($sqlData as $sqlRecord) {
        $sqlDataAssoc[$sqlRecord['doc_code']] = $sqlRecord;
    }

    // Confrontare i dati API con i dati SQL
    foreach ($apiData as $apiItem) {
        foreach ($apiItem['data'] as $apiRecord) {
            $docCode = $apiRecord['doc_code'];
            $apiUpdatedTs = isset($apiRecord['updated_ts']) ? new DateTime($apiRecord['updated_ts']) : null;

            // Se il record esiste in SQL
            if (isset($sqlDataAssoc[$docCode])) {
                $sqlUpdatedTs = isset($sqlDataAssoc[$docCode]['updated_ts']) ? new DateTime($sqlDataAssoc[$docCode]['updated_ts']) : null;

                // Confrontare le date di modifica
                if ($apiUpdatedTs && $sqlUpdatedTs && $apiUpdatedTs > $sqlUpdatedTs) {
                    $updatedRecords[] = $apiRecord;
                }
            } else {
                // Il record è presente in API ma mancante in SQL
                $missingRecords[] = $apiRecord;
            }
        }
    }

    return [
        'updated' => $updatedRecords,
        'missing' => $missingRecords
    ];
}
function generateInsertQueries($missingRecords, $columnMapping)
{
    $queries = [];
    foreach ($missingRecords as $record) {
        $columns = [];
        $values = [];
        foreach ($record as $key => $value) {
            if (isset($columnMapping[$key])) {
                $columns[] = $columnMapping[$key];
                $values[] = is_numeric($value) ? $value : "'" . str_replace("'", "''", $value) . "'";
            }
        }
        $columnsStr = implode(', ', $columns);
        $valuesStr = implode(', ', $values);
        $queries[] = "INSERT INTO [PP_2017_TST].[dbo].[Pelletterie Palladio\$Production BOM Header] ($columnsStr) VALUES ($valuesStr);";
    }
    return $queries;
}
function generateUpdateQueries($updatedRecords, $columnMapping)
{
    $queries = [];
    foreach ($updatedRecords as $record) {
        $setClauses = [];
        foreach ($record as $key => $value) {
            if (isset($columnMapping[$key])) {
                $column = $columnMapping[$key];
                $value = is_numeric($value) ? $value : "'" . str_replace("'", "''", $value) . "'";
                $setClauses[] = "$column = $value";
            }
        }
        $setClauseStr = implode(', ', $setClauses);
        $docCode = $record['doc_code']; // Assume 'doc_code' is the unique identifier for each record
        $queries[] = "UPDATE [PP_2017_TST].[dbo].[Pelletterie Palladio\$Production BOM Header] SET $setClauseStr WHERE [Document Code] = '$docCode';";
    }
    return $queries;
}
