<?php
//==== RICEZIONE DEI DATI ====
// Variabile globale per contare le chiamate API
$apiCallCount = 0;
// CHIAMATE PARALLELE
// Funzione per ottenere i dati API in base al tablecode sinc
function getApiDataPage($tablecode, $skipArray)
{
    global $tablecodesConfig, $apiCallCount;

    // Parametri API
    $api_params = json_decode('{"access_token":"' . API_TOKEN . '"}');

    // Definisce gli header per la richiesta API, inclusa l'autorizzazione con il token
    $headers = array(
        "Authorization: Bearer " . $api_params->access_token,
        "Content-Type: application/json"
    );

    $urlArray = [];
    foreach ($skipArray as $skip) {
        if ($tablecode === 'SUP' || $tablecode === 'MAT' || $tablecode === 'MOD') {
            $defid = $tablecodesConfig[$tablecode]['defid'];
            $datas = array(
                'limit' => PER_PAGE,
                'skip' => $skip
            );

            $query_string = http_build_query($datas);
            $urlArray[] = ASSET_API_URL . $defid . "?$query_string";
        } else {
            $fields = $tablecodesConfig[$tablecode]['fields'] ?? '';
            $datas = array(
                'siteid' => 'S01',
                'tblcode' => $tablecode,
                'fields' => $fields,
                'limit' => PER_PAGE,
                'skip' => $skip
            );

            $query_string = http_build_query($datas);
            $urlArray[] = API_URL . '?' . $query_string;
        }
    }

    $multiCurl = [];
    $result = [];
    $mh = curl_multi_init();

    foreach ($urlArray as $i => $url) {
        $multiCurl[$i] = curl_init();
        curl_setopt($multiCurl[$i], CURLOPT_URL, $url);
        curl_setopt($multiCurl[$i], CURLOPT_HTTPHEADER, $headers);
        curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($mh, $multiCurl[$i]);
    }

    $index = null;
    do {
        curl_multi_exec($mh, $index);
        curl_multi_select($mh);
    } while ($index > 0);

    foreach ($multiCurl as $i => $ch) {
        $response = curl_multi_getcontent($ch);
        $apiCallCount++;
        $data = json_decode($response, true);

        if (!is_array($data)) {
            die('Errore nella decodifica dei dati JSON');
        }

        foreach ($data as &$record) {
            if (isset($record['UOM']) && strlen($record['UOM']) > 2) {
                $record['UOM'] = substr($record['UOM'], 0, 2);
            }
        }

        // Stampa l'URL per il debug
        echo "DEBUG - Endpoint API:(getApiDataPage): $url\n";

        $result = array_merge($result, $data);
        curl_multi_remove_handle($mh, $ch);
    }

    curl_multi_close($mh);

    return $result;
}
// Funzione per ottenere tutti i dati API in base al tablecode sinc
function getApiData($tablecode)
{
    $allData = [];
    $skip = 0;

    // File di salvataggio temporaneo
    $tempFile = "temp_data_$tablecode.json";

    // Se esiste un file temporaneo, recupera i dati parziali
    if (file_exists($tempFile)) {
        $allData = json_decode(file_get_contents($tempFile), true);
        $skip = count($allData);
    }

    $batchSize = 10; // Numero di richieste da eseguire in parallelo
    while (true) {
        $skipArray = range($skip, $skip + ($batchSize - 1) * PER_PAGE, PER_PAGE);
        $data = getApiDataPage($tablecode, $skipArray);

        $allData = array_merge($allData, $data);

        // Salva i dati parziali nel file temporaneo
        file_put_contents($tempFile, json_encode($allData));

        if (count($data) < PER_PAGE * $batchSize) {
            break;
        }

        $skip += PER_PAGE * $batchSize;

        // Aggiungi un intervallo di tempo per evitare il superamento del tempo massimo di esecuzione
        sleep(1);
    }

    // Rimuovi il file temporaneo dopo aver recuperato tutti i dati
    unlink($tempFile);

    return json_encode($allData);
}
// // CHIAMATE SINCRONE
// Funzione per ottenere i dati API in base al tablecode
// function getApiDataPage($tablecode, $skip)
// {
//     global $tablecodesConfig, $apiCallCount;

//     // Parametri API
//     $api_params = json_decode('{"access_token":"' . API_TOKEN . '"}');

//     // Definisce gli header per la richiesta API, inclusa l'autorizzazione con il token
//     $headers = array(
//         "Authorization: Bearer " . $api_params->access_token,
//         "Content-Type: application/json"
//     );

//     if ($tablecode === 'SUP' || $tablecode === 'MAT' || $tablecode === 'MOD') {
//         // Per SUP e MAT, utilizza l'endpoint specifico e la paginazione
//         $defid = $tablecodesConfig[$tablecode]['defid'];
//         $datas = array(
//             'limit' => PER_PAGE,
//             'skip' => $skip
//         );

//         // Costruisce la stringa della query senza siteid e tblcode
//         $query_string = http_build_query($datas);
//         $url = ASSET_API_URL . $defid . "?$query_string";
//     } else {
//         // Per le tabelle standard, utilizza il comportamento esistente
//         $fields = $tablecodesConfig[$tablecode]['fields'] ?? '';

//         $datas = array(
//             'siteid' => 'S01',
//             'tblcode' => $tablecode,
//             'fields' => $fields,
//             'limit' => PER_PAGE,  // Limita a 10000 record per pagina
//             'skip' => $skip  // Numero di record da saltare
//         );

//         // Costruisce la stringa della query
//         $query_string = http_build_query($datas);
//         $url = API_URL . '?' . $query_string;
//     }

//     // Stampa l'URL per il debug
//     echo "DEBUG - Endpoint API:(getApiDataPage): $url\n";

//     // Inizializza una sessione cURL
//     $ch = curl_init();

//     // Imposta l'URL di destinazione della richiesta
//     curl_setopt($ch, CURLOPT_URL, $url);

//     // Imposta gli header della richiesta
//     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

//     // Imposta l'opzione per restituire il risultato della richiesta come stringa
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//     // Esegue la richiesta cURL
//     $response = curl_exec($ch);

//     // Incrementa il contatore delle chiamate API
//     $apiCallCount++;

//     // Controlla se ci sono errori nell'esecuzione della richiesta
//     if (curl_errno($ch)) {
//         $error_msg = curl_error($ch);
//         curl_close($ch);
//         die('Errore cURL: ' . $error_msg);
//     }

//     // Chiude la sessione cURL
//     curl_close($ch);

//     // Decodifica la risposta JSON
//     $data = json_decode($response, true);

//     // Verifica se i dati sono stati decodificati correttamente
//     if (!is_array($data)) {
//         die('Errore nella decodifica dei dati JSON');
//     }

//     // Troncamento UOM a due caratteri
//     foreach ($data as &$record) {
//         if (isset($record['UOM']) && strlen($record['UOM']) > 2) {
//             $record['UOM'] = substr($record['UOM'], 0, 2);
//         }
//     }

//     return $data;
// }
// // Funzione per ottenere tutti i dati API in base al tablecode
// function getApiData($tablecode)
// {
//     $allData = [];
//     $skip = 0;

//     // File di salvataggio temporaneo
//     $tempFile = "temp_data_$tablecode.json";

//     // Se esiste un file temporaneo, recupera i dati parziali
//     if (file_exists($tempFile)) {
//         $allData = json_decode(file_get_contents($tempFile), true);
//         $skip = count($allData);
//     }

//     while (true) {
//         $data = getApiDataPage($tablecode, $skip);

//         // Unisci i dati ottenuti con i dati precedenti
//         $allData = array_merge($allData, $data);

//         // Salva i dati parziali nel file temporaneo
//         file_put_contents($tempFile, json_encode($allData));

//         // Verifica se il numero di dati ricevuti è minore del limite, indicando che non ci sono più dati
//         if (count($data) < PER_PAGE) {
//             break;
//         }

//         // Incrementa il numero di record da saltare per la prossima richiesta
//         $skip += PER_PAGE;

//         // Aggiungi un intervallo di tempo per evitare il superamento del tempo massimo di esecuzione
//         sleep(1); // Intervallo di 1 secondo tra ogni batch
//     }

//     // Rimuovi il file temporaneo dopo aver recuperato tutti i dati
//     unlink($tempFile);

//     // Converti tutti i dati in JSON
//     return json_encode($allData);
// }
// Funzione per ottenere la query SQL in base al tablecode
function getQuery($tablecode)
{
    global $tablecodesConfig;
    return $tablecodesConfig[$tablecode]['query'] ?? '';
}
// Funzione per ottenere i dati SQL in base al tablecode
function getSqlData($tablecode)
{
    global $tablecodesConfig;

    // Ottieni la configurazione per il tablecode specificato
    $config = $tablecodesConfig[$tablecode];

    $serverName = NOME_SERVER;
    $msconnectionInfo = array(
        "Database" => $config['database'],
        "UID" => UID,
        "PWD" => PWD
    );

    // Stabilisce una connessione con il server SQL Server
    $msconn = sqlsrv_connect($serverName, $msconnectionInfo);

    // Verifica se la connessione è fallita
    if ($msconn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Esegui la query SQL
    $stmt = sqlsrv_query($msconn, getQuery($tablecode));

    // Verifica se la query è fallita
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $mssqlData = [];
    $fieldMapping = $config['field_mapping'] ?? [];

    // Fetch dei dati ottenuti dalla query
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Applica la mappatura dei campi se esiste
        foreach ($fieldMapping as $apiField => $sqlField) {
            if (isset($row[$sqlField])) {
                // Troncamento del campo Base Unit of Measure
                if ($sqlField == 'Base Unit of Measure') {
                    $row[$sqlField] = substr($row[$sqlField], 0, 2);
                }
                $row[$apiField] = $row[$sqlField];
            }
        }
        $mssqlData[] = array_map(function ($value) {
            if (is_string($value)) {
                // Converti il valore in UTF-8 se è una stringa
                return convertToUtf8($value);
            }
            return $value;
        }, $row);
    }

    // Libera le risorse del risultato
    sqlsrv_free_stmt($stmt);
    // Chiudi la connessione al database
    sqlsrv_close($msconn);

    // Converti i dati in JSON
    $jsonData = json_encode($mssqlData);

    // Verifica se c'è stato un errore nella codifica JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg();

        // Forza la conversione dei dati problematici
        foreach ($mssqlData as $key => $value) {
            $mssqlData[$key] = array_map(function ($v) {
                return is_string($v) ? convertToUtf8($v) : $v;
            }, $value);
        }
        $jsonData = json_encode($mssqlData);

        // Verifica se c'è stato un errore nella codifica JSON dopo la conversione forzata
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("JSON Encoding Error: " . json_last_error_msg());
        }
    }

    return $jsonData;
}
function scrivi_log($log, $filename)
{

    file_put_contents($filename, json_encode($log, JSON_PRETTY_PRINT));
}
// Funzione per leggere i file di log
function leggi_log($logFile)
{
    if (!file_exists($logFile)) {
        file_put_contents($logFile, json_encode([]));
    }

    $logContent = file_get_contents($logFile);
    $logData = json_decode($logContent, true);



    return $logData;
}
//==== CONVERSIONI E UTILITÀ ============================================================================================================
// Funzione per verificare l'esistenza delle variabili di ambiente
function getEnvVariable($key)
{
    if (!isset($_ENV[$key])) {
        die("Errore: La variabile di ambiente '{$key}' non è definita nel file .env.");
    }
    return $_ENV[$key];
}
// Funzione per convertire i valori in UTF-8
function convertToUtf8($value)
{
    // Verifica se il valore è già codificato in UTF-8
    if (mb_detect_encoding($value, 'UTF-8', true) === false) {
        // Codifica il valore in UTF-8 se non lo è già
        $value = utf8_encode($value);
    }
    // Rimuovi eventuali caratteri non validi
    return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
}
// Funzione per creare una mappa dei dati
function crea_mappa_dati($data)
{
    $dataMap = [];
    foreach ($data as $item) {
        $dataMap[strtolower(trim($item['code']))] = $item; // Convert to lowercase
    }
    return $dataMap;
}
function truncateFields($data, $tablecode)
{
    global $tablecodesConfig;

    if (isset($tablecodesConfig[$tablecode]['truncate_fields'])) {
        $truncateFields = $tablecodesConfig[$tablecode]['truncate_fields'];

        foreach ($truncateFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = substr($data[$field], 0, 2);
            }
        }
    }

    return $data;
}
// Funzione per verificare se è la prima esecuzione dello script
function isFirstExecution()
{
    $firstExecutionFile = FIRST_EXECUTION_FILE;
    if (!file_exists($firstExecutionFile)) {
        file_put_contents($firstExecutionFile, 'Delete this file before running the script for the first time to prevent unwanted modifications');
        echo "Prima esecuzione: BLOCCA_ESECUZIONE automatico attivo.\n </br>";
        return true;
    }
    echo "Esecuzione successiva: BLOCCA_ESECUZIONE automatico non attivo di default.\n </br>";
    return false;
}
//==== CONFRONTO DEI DATI ========================================================================================================================
// Funzione per confrontare le descrizioni
function confronta_descrizioni($item, $item2, $logEntry, $lastLogEntry)
{
    $status = 'congruenza';
    $description_api = $item2['description'] ?? ''; // Mantieni la descrizione API originale
    $description_sql = $item['description'] ?? '';

    // Converti entrambe le descrizioni in maiuscolo per il confronto
    if (trim(strtoupper($description_sql)) != trim(strtoupper($description_api))) {
        $status = 'incongruenza dati';

        if ($logEntry && $lastLogEntry) {
            if (strtoupper(trim($logEntry['description_sql'] ?? '')) != strtoupper(trim($lastLogEntry['description_sql'] ?? ''))) {
                $description_api = $item2['description']; // Mantieni la descrizione API originale
            }
        } else {
            $description_api = $item2['description']; // Mantieni la descrizione API originale
        }
    }

    return [
        'item2' => $item2,
        'description_api' => $description_api,
        'status' => $status
    ];
}
// Funzione per confrontare i campi specifici
function confronta_campi($item1, $item2, $fields, $logEntry, $lastLogEntry, $tablecode)
{
    global $tablecodesConfig;
    $fieldResults = [];
    $status = 'congruenza';

    $truncateFields = $tablecodesConfig[$tablecode]['truncate_fields'] ?? [];

    // Troncatura dei campi specificati
    $item1 = truncateFields($item1, $tablecode);
    $item2 = truncateFields($item2, $tablecode);

    foreach ($fields as $field) {
        $field = trim($field);

        $value1 = isset($item1[$field]) ? trim($item1[$field]) : '';
        $value2 = isset($item2[$field]) ? trim($item2[$field]) : '';

        // Converti tutti i campi in maiuscolo per il confronto
        $value1 = strtoupper($value1);
        $value2 = strtoupper($value2);

        // Specific rule for 'family' field in MOD table
        if ($tablecode === 'MOD' && $field === 'family') {
            $value1 = preg_replace('/[^0-9]/', '', $value1);
            $value2 = preg_replace('/[^0-9]/', '', $value2);
        }

        // Specific rule for 'brand' field in MOD table
        if ($tablecode === 'MOD' && $field === 'brand') {
            // Extract the brand code within parentheses
            if (preg_match('/\((.*?)\)/', $value2, $matches)) {
                $value2 = $matches[1];
            }
        }

        // Specific rule for 'prstatus' field
        if ($field === 'prstatus') {
            $value1 = strtok($value1, ' ');
            $value2 = strtok($value2, ' ');
        }

        // Check for incongruence
        if ($value1 !== $value2) {
            $status = 'incongruenza dati';
        }

        // Log the comparison details
        $fieldResults[$field] = [
            'sql' => $value1,
            'api' => $value2,
            'status' => ($value1 === $value2) ? 'congruenza' : 'incongruenza dati'
        ];
    }

    return [
        'fieldResults' => $fieldResults,
        'status' => $status
    ];
}
// Funzione per aggiornare il log
function aggiorna_log($log, $tablecode, $code, $description_sql, $description_api, $fieldResults, $timestamp)
{
    if (!isset($log[$tablecode])) {
        $log[$tablecode] = [];
    }

    // Se il record è presente solo in SQL
    if (!isset($description_api)) {
        $log[$tablecode][$code] = [
            'description_sql' => $description_sql,
            'description_api' => null,
            'fields' => [],
            'last_comparison' => $timestamp
        ];
    }
    // Se il record è presente solo in API
    elseif (!isset($description_sql)) {
        $log[$tablecode][$code] = [
            'description_sql' => null,
            'description_api' => $description_api,
            'fields' => [],
            'last_comparison' => $timestamp
        ];
    }
    // Se il record è presente in entrambe le fonti
    else {
        $log[$tablecode][$code] = [
            'description_sql' => $description_sql,
            'description_api' => $description_api,
            'fields' => $fieldResults, // Assicurati che stiamo usando i risultati dei fields corretti
            'last_comparison' => $timestamp
        ];
    }

    return $log;
}
// Funzione principale per confrontare i dati
function confronta_dati($data1, $data2, $tablecode, &$bulkData, &$datiMancantiPerSql, &$datiEliminatiInSql)
{
    global $tablecodesConfig;
    $risultati = [];

    if (!isset($tablecodesConfig[$tablecode])) {
        die("Configurazione per tablecode $tablecode non trovata.");
    }
    $config = $tablecodesConfig[$tablecode];
    $fields = array_map('trim', explode(',', $config['fields']));

    $log = leggi_log(LOG_FILE);
    $lastLog = leggi_log(LAST_LOG_FILE);

    $sqlDataMap = crea_mappa_dati($data1);
    $apiDataMap = crea_mappa_dati($data2);

    $timestamp = date('Y-m-d H:i:s');

    foreach ($sqlDataMap as $code => $item) {
        $logEntry = $log[$tablecode][$code] ?? null;
        $lastLogEntry = $lastLog[$tablecode][$code] ?? null;

        if (isset($apiDataMap[$code])) {
            $item2 = $apiDataMap[$code];
            $descrizioneResult = confronta_descrizioni($item, $item2, $logEntry, $lastLogEntry);
            $description_api = $descrizioneResult['description_api'];
            $status = $descrizioneResult['status'];

            $campiResult = confronta_campi($item, $item2, $fields, $logEntry, $lastLogEntry, $tablecode);
            $fieldResults = $campiResult['fieldResults'];

            if ($campiResult['status'] === 'incongruenza dati') {
                $status = 'incongruenza dati';
            }

            $log = aggiorna_log($log, $tablecode, $code, $item['description'], $description_api, $fieldResults, $timestamp);

            $risultati[] = [
                'tablecode' => $tablecode,
                'code_sql' => $item['code'],
                'code_api' => $item2['code'],
                'description_sql' => $item['description'],
                'description_api' => $item2['description'],
                'fields' => $fieldResults,
                'status' => $status,
                'logEntry' => $log[$tablecode][$code] ?? null,
                'lastLogEntry' => $lastLog[$tablecode][$code] ?? null
            ];
        } else {
            if (!empty(trim($item['description']))) {
                $log = aggiorna_log($log, $tablecode, $code, $item['description'], null, [], $timestamp);

                $risultati[] = [
                    'tablecode' => $tablecode,
                    'code_sql' => $item['code'],
                    'code_api' => null,
                    'description_sql' => $item['description'],
                    'description_api' => null,
                    'fields' => null,
                    'status' => 'codice API mancante',
                    'logEntry' => $log[$tablecode][$code] ?? null,
                    'lastLogEntry' => $lastLog[$tablecode][$code] ?? null
                ];

                if ($logEntry === null && !isset($lastLog[$tablecode][$code])) {
                    unset($item['tblcode'], $item['parentcode'], $item['siteid']);
                    if (isset($item['dropped'])) {
                        $item['dropped'] = filter_var($item['dropped'], FILTER_VALIDATE_BOOLEAN);
                    }
                    $dataToPush = [
                        'code' => $item['code'],
                        'description' => $item['description']
                    ];

                    foreach ($item as $key => $value) {
                        if (!in_array($key, ['tblcode', 'code', 'description', 'dropped'])) {
                            if (in_array($key, $config['truncate_fields'] ?? [])) {
                                $value = substr($value, 0, 2);
                            }
                            $dataToPush[$key] = $value;
                        }
                    }

                    $bulkData[$tablecode][] = $dataToPush;
                }
            }
        }
    }

    foreach ($apiDataMap as $code => $item) {
        if (!isset($sqlDataMap[$code])) {
            $logEntry = $log[$tablecode][$code] ?? null;
            $lastLogEntry = $lastLog[$tablecode][$code] ?? null;

            $log = aggiorna_log($log, $tablecode, $code, null, $item['description'], [], $timestamp);

            $risultati[] = [
                'tablecode' => $tablecode,
                'code_sql' => null,
                'code_api' => $item['code'],
                'description_sql' => null,
                'description_api' => $item['description'],
                'fields' => null,
                'status' => 'codice SQL mancante',
                'logEntry' => $log[$tablecode][$code] ?? null,
                'lastLogEntry' => $lastLog[$tablecode][$code] ?? null
            ];

            if ($logEntry === null && !isset($lastLog[$tablecode][$code])) {
                unset($item['tblcode'], $item['parentcode'], $item['siteid']);
                if (isset($item['dropped'])) {
                    $item['dropped'] = filter_var($item['dropped'], FILTER_VALIDATE_BOOLEAN);
                }
                $item['tblcode'] = $tablecode;
                $datiMancantiPerSql[] = $item;
            } else {
                foreach ($data1 as $sqlRow) {
                    if ($sqlRow['code'] === $code) {
                        $datiEliminatiInSql[$tablecode][] = [
                            'code_sql' => $sqlRow['code'],
                            'code_api' => $item['code'],
                            'description_api' => $item['description']
                        ];
                        break;
                    }
                }
            }
        }
    }

    scrivi_log($log, LOG_FILE);

    return $risultati;
}
// Funzione per trovare i dati presenti nell'API ma mancanti nel SQL
function trova_dati_mancanti_in_sql($sqlDataMap, $apiData)
{
    $datiMancantiInSql = [];

    // Crea una mappa per i dati API
    $apiDataMap = crea_mappa_dati($apiData);

    // Trova i codici presenti nell'API ma mancanti nel SQL
    foreach ($apiDataMap as $code => $item) {
        if (!isset($sqlDataMap[$code])) {
            $datiMancantiInSql[] = $item;
        }
    }

    return $datiMancantiInSql;
}
//==== DEBUG E STAMPA RISULTATI ================================================================================================
// Funzione per stampare i risultati del confronto
function stampa_risultati($risultati, $tablecode)
{
    global $tablecodesConfig;

    // Recupera il nome della tabella e i campi da confrontare dalla configurazione
    $table_name = $tablecodesConfig[$tablecode]['table_name'] ?? $tablecode;
    $fields = explode(',', $tablecodesConfig[$tablecode]['fields']);

    if (!empty($risultati)) {
        echo "Risultati per $table_name ($tablecode):<br>";
        echo "<table border='1'>
                <tr>
                    <th>Tablecode</th>
                    <th>Code SQL</th>
                    <th>Code API</th>
                    <th>Description SQL</th>
                    <th>Description API</th>";

        // Aggiungi dinamicamente le intestazioni delle colonne dei campi specifici
        foreach ($fields as $field) {
            $field = trim($field);
            if (!empty($field)) {
                echo "<th>{$field} SQL</th><th>{$field} API</th>";
            }
        }

        echo "<th>Status</th></tr>";

        foreach ($risultati as $riga) {
            $rowStyle = '';
            if ($riga['status'] === 'incongruenza dati') {
                $rowStyle = 'style="color: red;"';
            } elseif ($riga['status'] === 'congruenza') {
                $rowStyle = 'style="color: green;"';
            } elseif ($riga['status'] === 'codice API mancante') {
                $rowStyle = 'style="color: orange;"';
            } elseif ($riga['status'] === 'codice SQL mancante') {
                $rowStyle = 'style="color: blue;"';
            }
            echo "<tr $rowStyle>
                    <td>{$riga['tablecode']}</td>
                    <td>{$riga['code_sql']}</td>
                    <td>{$riga['code_api']}</td>
                    <td>{$riga['description_sql']}</td>
                    <td>{$riga['description_api']}</td>";

            // Aggiungi dinamicamente i campi specifici
            foreach ($fields as $field) {
                $field = trim($field);
                if (!empty($field)) {
                    $field_sql = $riga['fields'][$field]['sql'] ?? '';
                    $field_api = $riga['fields'][$field]['api'] ?? '';
                    echo "<td>{$field_sql}</td>
                          <td>{$field_api}</td>";
                }
            }

            echo "<td>{$riga['status']}</td>
                </tr>";
        }

        echo "</table><br>";
    } else {
        echo "Nessun risultato per $table_name ($tablecode).<br>";
    }
}
// Funzione di confronto e stampa risultato confronto
function debug_confronto_dati($risultati, $tablecode, &$datiEliminatiInSql, &$bulkData)
{
    $sqlDataNew = [];
    $apiDataNew = [];
    $datiAggiuntiInApi = [];
    $datiAggiuntiInSql = [];
    $datiRimossiInApi = [];
    $datiRimossiInSql = [];
    $datiDiscordantiNuoviInApi = [];

    echo "<h3>Debug per il tablecode: $tablecode</h3>";

    $datiMancantiInSql = array_filter($risultati, function ($riga) {
        return $riga['status'] === 'codice SQL mancante';
    });

    $datiMancantiInApi = array_filter($risultati, function ($riga) {
        return $riga['status'] === 'codice API mancante';
    });

    $datiIncongruenti = array_filter($risultati, function ($riga) {
        return $riga['status'] === 'incongruenza dati';
    });

    echo "<h4>Dati Mancanti in SQL:</h4>";
    if (!empty($datiMancantiInSql)) {
        foreach ($datiMancantiInSql as $riga) {
            echo "Code API: {$riga['code_api']}, Description API: {$riga['description_api']}<br>";

            $logEntry = $riga['lastLogEntry'] ?? null;
            $currentLogEntry = $riga['logEntry'] ?? null;

            echo "Confronto con il vecchio log (SQL mancante):<br>";
            echo "Vecchio log: ";
            print_r($logEntry);
            echo "Ultimo log: ";
            print_r($currentLogEntry);

            if (!$logEntry) {
                $datiAggiuntiInApi[] = $riga;
                echo "Risultato: Dato aggiunto in API.<br>";
            } elseif ($logEntry && $currentLogEntry && $logEntry == $currentLogEntry) {
                echo "Risultato: Impossibile determinare, log invariato.<br>";
            } elseif (!empty($logEntry['description_api']) && !empty($logEntry['description_sql'])) {
                $datiRimossiInSql[] = $riga;
                echo "Risultato: Dato rimosso in SQL.<br>";
                $datiEliminatiInSql[$tablecode][] = [
                    'code_sql' => $riga['code_sql'],
                    'code_api' => $riga['code_api'],
                    'description_api' => $riga['description_api']
                ];
            } else {
                echo "Risultato: Impossibile determinare.<br>";
            }
        }
    } else {
        echo "Nessun dato mancante in SQL.<br>";
    }

    echo "<h4>Dati Mancanti in API:</h4>";
    if (!empty($datiMancantiInApi)) {
        foreach ($datiMancantiInApi as $riga) {
            echo "Code SQL: {$riga['code_sql']}, Description SQL: {$riga['description_sql']}<br>";

            $logEntry = $riga['lastLogEntry'] ?? null;
            $currentLogEntry = $riga['logEntry'] ?? null;

            echo "Confronto con il vecchio log (API mancante):<br>";
            echo "Vecchio log: ";
            print_r($logEntry);
            echo "Ultimo log: ";
            print_r($currentLogEntry);

            if (!$logEntry) {
                $datiAggiuntiInSql[] = $riga;
                echo "Risultato: Dato aggiunto in SQL.<br>";
            } elseif ($logEntry && $currentLogEntry && $logEntry == $currentLogEntry) {
                echo "Risultato: Impossibile determinare, log invariato.<br>";
            } elseif (!empty($logEntry['description_api']) && !empty($logEntry['description_sql'])) {
                $datiRimossiInApi[] = $riga;
                echo "Risultato: Dato rimosso in API.<br>";
                $datiEliminatiInSql[$tablecode][] = [
                    'code_sql' => $riga['code_sql'],
                    'code_api' => $riga['code_api'],
                    'description_api' => $riga['description_api']
                ];
            } else {
                echo "Risultato: Impossibile determinare.<br>";
            }
        }
    } else {
        echo "Nessun dato mancante in API.<br>";
    }

    echo "<h4>Dati Incongruenti:</h4>";
    if (!empty($datiIncongruenti)) {
        foreach ($datiIncongruenti as $riga) {
            echo "Code SQL: {$riga['code_sql']}, Code API: {$riga['code_api']}<br>";
            echo "Description SQL: {$riga['description_sql']}, Description API: {$riga['description_api']}<br>";

            echo "Vecchio Log:<br>";
            print_r($riga['lastLogEntry']);
            echo "<br>Nuovo Log:<br>";
            print_r($riga['logEntry']);

            if ($riga['description_sql'] != $riga['description_api']) {
                if ($riga['logEntry']['description_sql'] != $riga['lastLogEntry']['description_sql'] && !(is_null($riga['lastLogEntry']['description_sql']) && !is_null($riga['logEntry']['description_sql']))) {
                    echo "Dato nuovo: SQL<br>";
                    $sqlDataNew[] = $riga;
                } elseif ($riga['logEntry']['description_api'] != $riga['lastLogEntry']['description_api'] && !(is_null($riga['lastLogEntry']['description_api']) && !is_null($riga['logEntry']['description_api']))) {
                    echo "Dato nuovo: API<br>";
                    $apiDataNew[] = $riga;
                    $datiDiscordantiNuoviInApi[] = $riga;
                } else {
                    echo "Impossibile determinare dato nuovo<br>";
                }
            }

            echo "Campi incongruenti:<br>";
            foreach ($riga['fields'] as $field => $result) {
                if ($result['status'] === 'incongruenza dati') {
                    echo "$field SQL: {$result['sql']}, $field API: {$result['api']}<br>";

                    if (isset($riga['logEntry']['fields'][$field]['sql']) && isset($riga['lastLogEntry']['fields'][$field]['sql']) && $riga['logEntry']['fields'][$field]['sql'] != $riga['lastLogEntry']['fields'][$field]['sql'] && !(is_null($riga['lastLogEntry']['fields'][$field]['sql']) && !is_null($riga['logEntry']['fields'][$field]['sql']))) {
                        echo "Dato nuovo per $field: SQL<br>";
                        $sqlDataNew[] = $riga;
                    } elseif (isset($riga['logEntry']['fields'][$field]['api']) && isset($riga['lastLogEntry']['fields'][$field]['api']) && $riga['logEntry']['fields'][$field]['api'] != $riga['lastLogEntry']['fields'][$field]['api'] && !(is_null($riga['lastLogEntry']['fields'][$field]['api']) && !is_null($riga['logEntry']['fields'][$field]['api']))) {
                        echo "Dato nuovo per $field: API<br>";
                        $apiDataNew[] = $riga;
                        $datiDiscordantiNuoviInApi[] = $riga;
                    } else {
                        echo "Impossibile determinare dato nuovo per $field<br>";
                    }
                }
            }
            echo "<br>";
        }
    } else {
        echo "Nessun dato incongruente.<br>";
    }

    echo "<h4>Dati nuovi per SQL:</h4>";
    print_r($sqlDataNew);

    echo "<h4>Dati nuovi per API:</h4>";
    print_r($apiDataNew);

    echo "<h4>Dati aggiunti in API:</h4>";
    print_r($datiAggiuntiInApi);

    echo "<h4>Dati aggiunti in SQL:</h4>";
    print_r($datiAggiuntiInSql);

    echo "<h4>Dati rimossi in API:</h4>";
    print_r($datiRimossiInApi);

    echo "<h4>Dati rimossi in SQL:</h4>";
    print_r($datiRimossiInSql);

    echo "<h4>Dati discordanti con dato nuovo in API:</h4>";
    print_r($datiDiscordantiNuoviInApi);

    foreach ($sqlDataNew as $data) {
        $dataToPush = [
            'code' => $data['code_sql'],
            'description' => $data['description_sql']
        ];

        foreach ($data['fields'] as $field => $result) {
            if (isset($result['sql'])) {
                $dataToPush[$field] = $result['sql'];
            }
        }

        foreach ($data as $key => $value) {
            if (!in_array($key, ['tblcode', 'code_sql', 'description_sql', 'dropped', 'fields', 'status', 'logEntry', 'lastLogEntry']) && !isset($dataToPush[$key])) {
                $dataToPush[$key] = $value;
            }
        }

        echo "DEBUG - Aggiungere a bulkData (sqlDataNew):\n";
        print_r($dataToPush);

        $bulkData[$tablecode][] = $dataToPush;
    }

    return [$sqlDataNew, $apiDataNew, $datiAggiuntiInApi, $datiAggiuntiInSql, $datiRimossiInApi, $datiRimossiInSql];
}
//==== Inserimento e Aggiornamento Dati in API================================================================================================
// Funzione per inviare i dati all'API in un'unica chiamata PUT
function inviaDatiInBulk($bulkData)
{
    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le chiamate API sono disabilitate.\n";
        return false;
    }

    echo "Tentativo di invio dati in bulk...\n";
    echo "Dati ricevuti per l'invio:\n";
    print_r($bulkData);

    if (empty($bulkData)) {
        echo "Nessun dato da inviare in bulk.\n";
        return;
    }

    $api_params = json_decode('{"access_token":"' . API_TOKEN . '"}');
    $headers = array(
        "Authorization: Bearer " . $api_params->access_token,
        "Content-Type: application/json"
    );

    $base_url = 'https://palladio-uat.link.mylectra.com/link/api/v1/load_tbl';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    foreach ($bulkData as $tblcode => $data) {
        $siteid = 'S01';
        $mode = 'sync';
        $check = 'skip';
        $chunks = array_chunk($data, 100);

        foreach ($chunks as $chunk) {
            $chunkToSend = [];
            foreach ($chunk as $item) {
                $itemToSend = [
                    'tblcode' => $tblcode,
                    'code' => $item['code'],
                    'description' => $item['description']
                ];
                if (isset($item['dropped'])) {
                    $itemToSend['dropped'] = filter_var($item['dropped'], FILTER_VALIDATE_BOOLEAN);
                }

                foreach ($item as $key => $value) {
                    if (!in_array($key, ['tblcode', 'code', 'description', 'dropped'])) {
                        $itemToSend[$key] = $value;
                    }
                }

                $chunkToSend[] = $itemToSend;
            }

            $jsonData = json_encode($chunkToSend);
            $url = "$base_url/$siteid/$mode/$check";
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

            $response = curl_exec($ch);
            global $apiCallCount;
            $apiCallCount++;

            echo "DEBUG - Endpoint API: $url\n";
            echo "DEBUG - Payload inviato:\n$jsonData\n";
            echo "DEBUG - Risposta dell'API:\n$response\n";

            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                curl_close($ch);
                die('Errore cURL: ' . $error_msg);
            }

            $data = json_decode($response, true);
            if (!is_array($data)) {
                die('Errore nella decodifica dei dati JSON');
            }
        }
    }

    curl_close($ch);
    return true;
}
//==== Funzioni per inviare dati Asset========
// Nuova funzione per inviare dati di SUP
function inviaDatiSUPInBulk($bulkDataSUP)
{
    global $tablecodesConfig;

    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le chiamate API sono disabilitate.<br>";
        return false;
    }

    echo "Tentativo di invio dati in bulk per SUP...<br>";
    echo "Dati ricevuti per l'invio:<br>";
    print_r($bulkDataSUP);
    echo "<br>";

    if (empty($bulkDataSUP)) {
        echo "Nessun dato da inviare per SUP.<br>";
        return;
    }

    // Filtriamo i dati per includere solo code e description
    $filteredBulkDataSUP = array_map(function ($item) {
        return [
            'code' => $item['code'],
            'description' => $item['description']
        ];
    }, $bulkDataSUP);

    // Rimuoviamo eventuali duplicati
    $filteredBulkDataSUP = array_unique($filteredBulkDataSUP, SORT_REGULAR);

    // Recuperiamo l'API token
    $api_params = json_decode('{"access_token":"' . API_TOKEN . '"}');
    $headers = [
        "Authorization: Bearer " . $api_params->access_token,
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: Mozilla/5.0"
    ];

    $endpoint = ASSET_API_URL  . $tablecodesConfig['SUP']['defid'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Usa PUT invece di POST
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($filteredBulkDataSUP));
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Aggiungi questa linea per il debug

    // Disabilita la verifica del certificato SSL per test
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo "DEBUG - URL Endpoint(SUP): $endpoint<br>";
    echo "DEBUG - Payload inviato: " . json_encode($filteredBulkDataSUP) . "<br>";
    echo "DEBUG - Intestazioni inviate: [Authorization: Bearer " . substr($api_params->access_token, 0, 10) . "..., Content-Type: application/json]<br>"; // Nascondi la API key nei log
    echo "DEBUG - Risposta dell'API: $response<br>";

    if ($httpCode != 200) {
        echo "Errore durante l'invio dei dati: $response<br>";
    }

    curl_close($ch);
}
// Funzione per inviare dati di MAT in bulk
function inviaDatiMATInBulk($bulkDataMAT)
{
    global $tablecodesConfig;

    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le chiamate API sono disabilitate.<br>";
        return false;
    }

    echo "Tentativo di invio dati in bulk per MAT...<br>";
    echo "Dati ricevuti per l'invio:<br>";
    print_r($bulkDataMAT);
    echo "<br>";

    if (empty($bulkDataMAT)) {
        echo "Nessun dato da inviare per MAT.<br>";
        return;
    }

    // Filtriamo i dati per includere solo code, description, uom, e altri campi specifici
    $filteredBulkDataMAT = array_map(function ($item) {
        return [
            'code' => $item['code'],
            'description' => $item['description'],
            'uom' => isset($item['uom']) ? substr($item['uom'], 0, 2) : '',
            'brand' => isset($item['brand']) ? $item['brand'] : '',
            'category' => isset($item['category']) ? substr($item['category'], 0, 2) : '',
            'descrizione2' => isset($item['descrizione2']) ? $item['descrizione2'] : '',
            'purchaseuom' => isset($item['purchaseuom']) ? substr($item['purchaseuom'], 0, 2) : ''
        ];
    }, $bulkDataMAT);

    // Rimuoviamo i record che non hanno un uom valido
    $filteredBulkDataMAT = array_filter($filteredBulkDataMAT, function ($item) {
        return !empty($item['uom']);
    });

    // Rimuoviamo eventuali duplicati
    $filteredBulkDataMAT = array_unique($filteredBulkDataMAT, SORT_REGULAR);

    // Recuperiamo l'API token
    $api_params = json_decode('{"access_token":"' . API_TOKEN . '"}');
    $headers = [
        "Authorization: Bearer " . $api_params->access_token,
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: Mozilla/5.0"
    ];

    $endpoint = ASSET_API_URL . $tablecodesConfig['MAT']['defid'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Usa PUT invece di POST
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($filteredBulkDataMAT));
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Aggiungi questa linea per il debug

    // Disabilita la verifica del certificato SSL per test
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo "DEBUG - URL Endpoint(MAT): $endpoint<br>";
    echo "DEBUG - Payload inviato: " . json_encode($filteredBulkDataMAT) . "<br>";
    echo "DEBUG - Intestazioni inviate: [Authorization: Bearer " . substr($api_params->access_token, 0, 10) . "..., Content-Type: application/json]<br>"; // Nascondi la API key nei log
    echo "DEBUG - Risposta dell'API: $response<br>";

    if ($httpCode != 200) {
        echo "Errore durante l'invio dei dati: $response<br>";
    }

    curl_close($ch);
}
// Funzione per inviare dati di MOD in bulk
function inviaDatiMODInBulk($bulkDataMOD)
{
    global $tablecodesConfig;

    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le chiamate API sono disabilitate.<br>";
        return false;
    }

    echo "Tentativo di invio dati in bulk per MOD...<br>";
    echo "Dati ricevuti per l'invio:<br>";
    print_r($bulkDataMOD);
    echo "<br>";

    if (empty($bulkDataMOD)) {
        echo "Nessun dato da inviare per MOD.<br>";
        return;
    }

    // Recuperiamo l'API token
    $api_params = json_decode('{"access_token":"' . API_TOKEN . '"}');
    $headers = [
        "Authorization: Bearer " . $api_params->access_token,
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: Mozilla/5.0"
    ];

    $endpoint = ASSET_API_URL . $tablecodesConfig['MOD']['defid'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); // Usa PUT invece di POST
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Aggiungi questa linea per il debug

    // Disabilita la verifica del certificato SSL per test
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    foreach ($bulkDataMOD as &$data) {
        // Conversione specifica per il campo 'brand'
        if (isset($data['brand'])) {
            $brand = $data['brand'];
            if (preg_match('/\((.*?)\)/', $brand, $matches)) {
                $data['brand'] = $matches[1];
            }
        }

        // Rimuovi campi non necessari dal payload
        unset($data['tablecode'], $data['code_api'], $data['description_api']);

        // Verifica la presenza dei campi chiave
        if (!isset($data['code']) || !isset($data['description'])) {
            echo "Errore: campo chiave mancante nel payload.<br>";
            continue;
        }

        // Converti tutti i valori in stringhe
        foreach ($data as $key => $value) {
            $data[$key] = (string)$value;
        }
    }

    $payload = json_encode($bulkDataMOD);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo "DEBUG - URL Endpoint(MOD): $endpoint<br>";
    echo "DEBUG - Payload inviato: " . $payload . "<br>";
    echo "DEBUG - Intestazioni inviate: [Authorization: Bearer " . substr($api_params->access_token, 0, 10) . "..., Content-Type: application/json]<br>"; // Nascondi la API key nei log
    echo "DEBUG - Risposta dell'API: $response<br>";

    if ($httpCode != 200) {
        echo "Errore durante l'invio dei dati: $response<br>";
    }

    curl_close($ch);
}
// === Inserimento Dati in SQL=============================================================================================================================================
// Funzione per verificare se una colonna esiste nella tabella
function colonna_esiste($tableName, $columnName)
{
    $serverName = NOME_SERVER;
    $connectionOptions = [
        "UID" => UID,
        "PWD" => PWD,
        "Database" => getEnvVariable('DB_TEMP')
    ];

    // Stabilisce una connessione con il server SQL Server
    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false) {
        die("Errore nella connessione al database: " . print_r(sqlsrv_errors(), true));
    }

    $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$tableName' AND LOWER(COLUMN_NAME) = LOWER('$columnName')";

    $stmt = sqlsrv_query($conn, $query);

    if ($stmt === false) {
        die("Errore nell'esecuzione della query: " . print_r(sqlsrv_errors(), true));
    }

    $exists = (sqlsrv_fetch_array($stmt) !== null);

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    return $exists;
}
//Funzione per creare tabella temporanea
function crea_tabella_temporanea($tablecode)
{
    global $tablecodesConfig;

    if (!isset($tablecodesConfig[$tablecode])) {
        die("Configurazione per tablecode $tablecode non trovata.");
    }

    $config = $tablecodesConfig[$tablecode];
    $originalTableName = $config['table'];
    $originalDatabaseName = $config['database'];

    // Nome del database temporaneo
    $tempDatabaseName = getEnvVariable('DB_TEMP');

    // Nome della tabella temporanea
    $tempTableName = $originalTableName . '_temp';

    // Formatta correttamente i nomi delle tabelle con parentesi quadre
    $originalTableNameEscaped = "[$originalDatabaseName].[dbo].[" . str_replace(['[', ']'], ['[[', ']]'], $originalTableName) . "]";
    $tempTableNameEscaped = "[$tempDatabaseName].[dbo].[" . str_replace(['[', ']'], ['[[', ']]'], $tempTableName) . "]";

    // Connessione al database temporaneo
    $tempConn = sqlsrv_connect(NOME_SERVER, array(
        "Database" => $tempDatabaseName,
        "Uid" => UID,
        "PWD" => PWD
    ));
    if ($tempConn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Verifica se la tabella temporanea esiste già e, in tal caso, eliminarla
    $checkTempTableQuery = "IF OBJECT_ID(N'$tempTableNameEscaped', 'U') IS NOT NULL DROP TABLE $tempTableNameEscaped";
    $dropTempResult = sqlsrv_query($tempConn, $checkTempTableQuery);
    if ($dropTempResult) {
        echo "La tabella temporanea $tempTableName esisteva ed è stata eliminata.\n";
    }

    // Debug: Stampa la query di creazione della tabella temporanea
    $createTempTableQuery = "SELECT TOP 1 * INTO $tempTableNameEscaped FROM $originalTableNameEscaped";
    echo "Query di creazione della tabella temporanea: $createTempTableQuery\n";

    // Clonare la nuova tabella temporanea dal database originale al database temporaneo
    $createTempResult = sqlsrv_query($tempConn, $createTempTableQuery);
    if ($createTempResult) {
        echo "La tabella temporanea $tempTableName è stata creata con successo clonando la struttura della tabella $originalTableName.\n";
    } else {
        echo "Errore durante la creazione della tabella temporanea $tempTableName.\n";
        die(print_r(sqlsrv_errors(), true));
    }

    // Chiudere la connessione
    sqlsrv_close($tempConn);
}
function genera_query_aggiornamento($tablecode, $apiData)
{
    global $tablecodesConfig;

    if (!isset($tablecodesConfig[$tablecode])) {
        die("Configurazione per tablecode $tablecode non trovata.");
    }

    $config = $tablecodesConfig[$tablecode];
    $originalTableName = $config['table'];
    $codeField = $config['code_mapping'] ?? 'Code'; // Utilizza la mappatura del campo code

    // Nome del database temporaneo
    $tempDatabaseName = getEnvVariable('DB_TEMP');

    // Nome della tabella temporanea
    $tempTableName = $originalTableName . '_temp';

    // Recuperare le colonne valide della tabella temporanea
    $validColumns = get_table_columns($tempDatabaseName, $tempTableName);

    // Generare la query di aggiornamento dinamica
    $columns = [];
    $values = [];

    // Mantenere traccia delle colonne già aggiunte per evitare duplicati
    $addedColumns = [];

    // Aggiungere il campo 'code' mappato correttamente
    if (!in_array($codeField, $addedColumns)) {
        $columns[] = "[$codeField] = ?";
        $values[] = $apiData['code'];
        $addedColumns[] = $codeField;
    }

    // Aggiungere 'Name' o 'Description' mappato correttamente per SUP
    if ($tablecode === 'SUP') {
        if (isset($apiData['description']) && !in_array('Name', $addedColumns)) {
            $columns[] = '[Name] = ?';
            $values[] = $apiData['description'];
            $addedColumns[] = 'Name';
        }
    } else {
        if (isset($apiData['description']) && !in_array('Description', $addedColumns)) {
            $columns[] = '[Description] = ?';
            $values[] = $apiData['description'];
            $addedColumns[] = 'Description';
        }
    }

    // Troncamento dei campi specificati
    $truncateFields = $config['truncate_fields'] ?? [];

    // Aggiungere i campi specifici definiti nella configurazione delle tabelle
    if (isset($config['field_mapping'])) {
        foreach ($config['field_mapping'] as $apiField => $sqlField) {
            if (isset($apiData[$apiField]) && in_array($sqlField, $validColumns) && !in_array($sqlField, $addedColumns)) {
                $value = $apiData[$apiField];

                // Troncamento dei campi definiti
                if (in_array($apiField, $truncateFields)) {
                    $value = substr($value, 0, 2);
                }

                // Specific handling for 'MOD' table brand field
                if ($tablecode === 'MOD' && $sqlField === 'PFBrand Code') {
                    preg_match('/\((.*?)\)/', $value, $matches);
                    $value = $matches[1] ?? $value;
                }

                // Handling for Product Group Code to get only the number
                if ($sqlField === 'Product Group Code') {
                    preg_match('/(\d+)/', $value, $matches);
                    $value = $matches[1] ?? $value;
                }

                // Specific handling for 'family' field in MOD
                if ($tablecode === 'MOD' && $sqlField === 'PFCollection') {
                    $value = preg_replace('/[^0-9]/', '', $value);
                }

                // Specific handling for 'prstatus' field
                if ($sqlField === 'PFItem Status') {
                    $value = strtok($value, ' ');
                }

                $columns[] = "[$sqlField] = ?";
                $values[] = $value;
                $addedColumns[] = $sqlField;
            }
        }
    }

    // Aggiungere le altre chiavi solo se sono presenti nelle colonne valide
    foreach ($apiData as $column => $value) {
        if (strtolower($column) != 'code' && strtolower($column) != 'description' && strtolower($column) != 'uom' && in_array($column, $validColumns) && !in_array($column, $addedColumns)) {
            $columns[] = "[$column] = ?";
            $values[] = $value;
            $addedColumns[] = $column;
        }
    }

    $updateColumns = implode(', ', $columns);

    // Log di debug
    echo "DEBUG - Valori per l'aggiornamento:\n";
    print_r($values);

    // Esegui l'aggiornamento del record senza condizione di confronto
    $updateQuery = "UPDATE [$tempDatabaseName].[dbo].[$tempTableName] SET $updateColumns";

    return vsprintf(str_replace('?', '%s', $updateQuery), array_map(function ($v) {
        return "'" . $v . "'";
    }, $values));
}
function genera_query_inserimento($tablecode)
{
    global $tablecodesConfig;

    if (!isset($tablecodesConfig[$tablecode])) {
        die("Configurazione per tablecode $tablecode non trovata.");
    }

    $config = $tablecodesConfig[$tablecode];
    $originalTableName = $config['table'];
    $originalDatabaseName = $config['database'];

    // Nome del database temporaneo
    $tempDatabaseName = getEnvVariable('DB_TEMP');

    // Nome della tabella temporanea
    $tempTableName = $originalTableName . '_temp';

    // Recuperare le colonne della tabella originale, escludendo timestamp
    $columns = get_table_columns($originalDatabaseName, $originalTableName);
    $columnsList = implode(", ", array_map(function ($col) {
        return "[$col]";
    }, $columns));

    $insertQuery = "INSERT INTO [$originalDatabaseName].[dbo].[$originalTableName] ($columnsList) SELECT $columnsList FROM [$tempDatabaseName].[dbo].[$tempTableName]";
    return $insertQuery;
}
function get_table_columns($database, $table)
{
    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => $database,
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table' AND DATA_TYPE != 'timestamp'";
    $stmt = sqlsrv_query($conn, $query);

    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $columns = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $columns[] = $row['COLUMN_NAME'];
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    return $columns;
}
// Aggiorna record temporaneo per inserimento
function esegui_query_aggiornamento($query)
{
    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le query di aggiornamento sono disabilitate.\n";
        return false;
    }

    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => getEnvVariable('DB_TEMP'),
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    // Log di debug
    echo "DEBUG - Esecuzione query di aggiornamento:\n$query\n";

    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
}
function esegui_query_inserimento($query, $database)
{
    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le query di aggiornamento sono disabilitate.\n";
        return false;
    }
    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => $database,
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $ignoreError = false;
        foreach ($errors as $error) {
            if ($error['code'] == 2627) { // Errore di violazione della chiave primaria
                $ignoreError = true;
                break;
            }
        }

        if ($ignoreError) {
            echo "Violazione della chiave primaria, record duplicato ignorato.\n";
        } else {
            die(print_r($errors, true));
        }
    } else {
        echo "Query di inserimento eseguita con successo.\n";
    }

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
}
function elimina_tabella_temporanea($tablecode)
{
    global $tablecodesConfig;

    if (!isset($tablecodesConfig[$tablecode])) {
        die("Configurazione per tablecode $tablecode non trovata.");
    }

    $config = $tablecodesConfig[$tablecode];
    $originalTableName = $config['table'];

    // Nome del database temporaneo
    $tempDatabaseName = getEnvVariable('DB_TEMP');

    // Nome della tabella temporanea
    $tempTableName = $originalTableName . '_temp';

    $dropQuery = "DROP TABLE [$tempDatabaseName].[dbo].[$tempTableName]";

    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => $tempDatabaseName,
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $stmt = sqlsrv_query($conn, $dropQuery);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
}
// Elimina dati eliminati in API in Sql=================================================================================================================
function genera_query_eliminazione($tablecode, $codes)
{
    global $tablecodesConfig;

    if (!isset($tablecodesConfig[$tablecode])) {
        die("Configurazione per tablecode $tablecode non trovata.");
    }

    $config = $tablecodesConfig[$tablecode];
    $originalTableName = $config['table'];
    $originalDatabaseName = $config['database'];
    $codeField = $config['code_mapping'] ?? 'Code'; // Utilizza la mappatura del campo code

    $tableNameEscaped = "[$originalDatabaseName].[dbo].[" . str_replace(['[', ']'], ['[[', ']]'], $originalTableName) . "]";

    $queries = [];

    foreach ($codes as $code) {
        $queries[] = "DELETE FROM $tableNameEscaped WHERE [$codeField] = '" . str_replace("'", "''", $code) . "'";
    }

    echo "Queries generate: ";
    print_r($queries);

    return $queries;
}
function esegui_query_eliminazione($query)
{
    if (BLOCCA_ESECUZIONE) {
        echo "Blocco esecuzione: le query di aggiornamento sono disabilitate.\n";
        return false;
    }
    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => getEnvVariable('DB_TEMP'),
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    echo "<h4>Esecuzione query di eliminazione:</h4>";
    echo "Query: $query<br>";

    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        echo "Errore nell'esecuzione della query:<br>";
        print_r(sqlsrv_errors());
    } else {
        echo "Query eseguita con successo.<br>";
    }

    if ($stmt) {
        sqlsrv_free_stmt($stmt);
    }
    sqlsrv_close($conn);
}
// === Aggiornamento Dati in SQL===============================================================================================================================
function genera_query_aggiornamento_per_dati_discordanti($datiDiscordantiNuoviInApi)
{
    global $tablecodesConfig;

    foreach ($datiDiscordantiNuoviInApi as $dato) {
        $tablecode = isset($dato['tablecode']) ? $dato['tablecode'] : '';
        $code = isset($dato['code_api']) ? $dato['code_api'] : '';
        $description = isset($dato['description_api']) ? $dato['description_api'] : '';
        $fields = isset($dato['fields']) ? $dato['fields'] : [];

        if (!isset($tablecodesConfig[$tablecode])) {
            echo "Configurazione per tablecode $tablecode non trovata.\n";
            continue;
        }

        $config = $tablecodesConfig[$tablecode];
        $originalTableName = $config['table'];
        $originalDatabaseName = $config['database'];
        $fieldMapping = isset($config['field_mapping']) ? $config['field_mapping'] : [];
        $truncateFields = $config['truncate_fields'] ?? [];

        // Costruzione della query di aggiornamento
        $updateQuery = "UPDATE [$originalDatabaseName].[dbo].[$originalTableName] SET [Description] = '$description'";

        foreach ($fields as $field => $value) {
            $fieldSql = isset($fieldMapping[$field]) ? $fieldMapping[$field] : $field;
            if (!empty($fieldSql)) {
                // Debugging output
                echo "Campo API: $field\n";
                echo "Campo SQL: $fieldSql\n";
                echo "Valore API: {$value['api']}\n";

                // Aggiungi troncamento del campo se necessario
                if (in_array($field, $truncateFields)) {
                    $value['api'] = substr($value['api'], 0, 2);
                }

                // Conversione specifica per 'family' in 'MOD'
                if ($tablecode === 'MOD' && $field === 'family') {
                    $value['api'] = preg_replace('/[^0-9]/', '', $value['api']);
                }

                // Conversione specifica per 'brand' in 'MOD'
                if ($tablecode === 'MOD' && $field === 'brand') {
                    if (preg_match('/\((.*?)\)/', $value['api'], $matches)) {
                        $value['api'] = $matches[1];
                    }
                }

                // Conversione specifica per 'Product Group Code'
                if ($fieldSql === 'Product Group Code') {
                    if (preg_match('/(\d+)/', $value['api'], $matches)) {
                        $value['api'] = $matches[1];
                    }
                }

                // Conversione specifica per 'prstatus'
                if ($fieldSql === 'PFItem Status') {
                    $value['api'] = strtok($value['api'], ' ');
                }

                $updateQuery .= ", [$fieldSql] = '{$value['api']}'";
            }
        }

        $updateQuery .= " WHERE [No_] = '$code';";

        echo "Query di aggiornamento:\n$updateQuery\n";
        // Esegui la query di aggiornamento
        esegui_query_aggiornamento($updateQuery);
    }
}
// === Eliminazione dati in API=======================================================================================================================
function inizializzaLogEliminati($logFile)
{
    if (!file_exists($logFile)) {
        file_put_contents($logFile, json_encode([]));
    }
}
function aggiornaLogEliminati($logFile, $datiEliminatiInSql)
{
    // Inizializza il file di log se non esiste
    if (!file_exists($logFile)) {
        file_put_contents($logFile, json_encode([]));
    }

    $logData = json_decode(file_get_contents($logFile), true);

    foreach ($datiEliminatiInSql as $tablecode => $records) {
        foreach ($records as $record) {
            $code = $record['code_api']; // Usa il codice API per il log

            // Aggiungi il record al log se non è già presente
            if (!isset($logData[$tablecode][$code])) {
                $logData[$tablecode][$code] = [
                    'code' => $code,
                    'description' => $record['description_api'] ?? 'No description'
                ];
            }
        }
    }

    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}
function sincronizzaLogConApi($logFile)
{
    global $tablecodesConfig;

    $logData = json_decode(file_get_contents($logFile), true);

    foreach ($logData as $tablecode => $records) {
        $apiDataJson = getApiData($tablecode);
        $apiData = json_decode($apiDataJson, true);

        $apiCodes = array_map(function ($item) {
            return strtolower(trim($item['code']));
        }, $apiData);

        foreach ($records as $code => $record) {
            if (!in_array(strtolower(trim($code)), $apiCodes)) {
                unset($logData[$tablecode][$code]);
            }
        }
    }

    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
}
