<?php
ini_set('max_execution_time', 600); // Aumenta il tempo massimo di esecuzione a 600 secondi

require_once __DIR__ . '/vendor/autoload.php';
require_once 'functions.php';
require_once 'config.php';


// Verifica se esiste una variabile di ambiente per bloccare l'esecuzione
$envBlockExecution = isset($_ENV['BLOCCA_ESECUZIONE']) && $_ENV['BLOCCA_ESECUZIONE'] === 'true';
echo "Valore della variabile di ambiente BLOCCA_ESECUZIONE: " . ($envBlockExecution ? 'true' : 'false') . "\n</br>";

// Verifica se è la prima esecuzione solo se la variabile di ambiente non è impostata
if ($envBlockExecution) {
    echo "Blocco esecuzione forzato da variabile di ambiente.\n</br>";
    $isFirstExecution = false;
    $blockExecution = true;
} else {
    $isFirstExecution = isFirstExecution();
    $blockExecution = $isFirstExecution;
}

// Set true per bloccare le chiamate API in PUT e l'esecuzione delle query (modalità Test)
define('BLOCCA_ESECUZIONE', $blockExecution);

// Log dello stato di BLOCCA_ESECUZIONE
if (BLOCCA_ESECUZIONE) {
    echo "BLOCCA_ESECUZIONE è attivo.\n";
} else {
    echo "BLOCCA_ESECUZIONE non è attivo.\n";
}

require_once 'get_tkn.php';
refreshToken(); // Aggiorna Token



// Set true per bloccare le chiamate API in PUT e l'esecuzione delle query (modalità Test)
// define('BLOCCA_ESECUZIONE', true);


// Copia il file di log corrente nel file di log precedente
if (!copy(LOG_FILE, LAST_LOG_FILE)) {
    echo "Errore durante la copia del log.<br>";
} else {
    echo "Log copiato correttamente.<br>";
}

// Elimina il file di log corrente se esiste
if (file_exists(LOG_FILE)) {
    unlink(LOG_FILE);
    echo "Log delle discrepanze eliminato.<br>";
} else {
    echo "Il file di log delle discrepanze non esiste.<br>";
}

// Inizializza le variabili se non sono già impostate
if (!isset($bulkData)) {
    $bulkData = [];
}
$datiMancantiPerSql = [];
$datiEliminatiInSql = [];
$datiDiscordantiNuoviInApi = [];

// Itera su ogni tablecode per elaborare i dati
foreach ($tablecodes as $tablecode) {
    // Recupera i dati dal database SQL
    $mssqlDataJson = getSqlData($tablecode);
    // Recupera i dati dall'API
    $apiDataJson = getApiData($tablecode);

    // Decodifica i dati JSON
    $mssqlData = json_decode($mssqlDataJson, true);
    $apiData = json_decode($apiDataJson, true);

    // Verifica se i dati sono validi
    if (!is_array($mssqlData) || !is_array($apiData)) {
        echo "Errore nella decodifica dei dati JSON per il tablecode $tablecode.<br>";
        continue;
    }

    // Confronta i dati tra SQL e API
    $risultati = confronta_dati($mssqlData, $apiData, $tablecode, $bulkData, $datiMancantiPerSql, $datiEliminatiInSql);

    // Funzione per vedere la lista completa di risultati (opzionale)
    stampa_risultati($risultati, $tablecode);

    // Debug dei risultati del confronto
    list($sqlDataNew, $apiDataNew, $datiAggiuntiInApi, $datiAggiuntiInSql, $datiRimossiInApi, $datiRimossiInSql) = debug_confronto_dati($risultati, $tablecode, $datiEliminatiInSql, $bulkData);

    // Aggiungi i dati nuovi per SQL a bulkData
    if (is_array($sqlDataNew)) {
        foreach ($sqlDataNew as $data) {
            $bulkData[$tablecode][] = [
                'code' => isset($data['code_sql']) ? $data['code_sql'] : '',
                'description' => isset($data['description_sql']) ? $data['description_sql'] : '',
            ];
        }
    }

    // Generazione e stampa delle query di eliminazione
    if (!empty($datiEliminatiInSql)) {
        foreach ($datiEliminatiInSql as $tablecode => $records) {
            $codes = [];
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['code_sql'])) {
                        $codes[] = $record['code_sql'];
                    }
                }
                $queries = genera_query_eliminazione($tablecode, $codes);
                foreach ($queries as $query) {
                    echo $query . "<br>";
                    // Esecuzione della query di eliminazione
                    if (!BLOCCA_ESECUZIONE) {
                        esegui_query_eliminazione($query);
                    }
                }
            }
        }
    }

    // Processa i dati aggiunti o rimossi
    if (!empty($datiRimossiInApi)) {
        foreach ($datiRimossiInApi as $dato) {
            echo "Dato rimosso in API:<br>";
            print_r($dato);
        }
    }

    if (!empty($datiRimossiInSql)) {
        foreach ($datiRimossiInSql as $dato) {
            echo "Dato rimosso in SQL:<br>";
            print_r($dato);
        }
    }

    if (!empty($datiAggiuntiInApi)) {
        foreach ($datiAggiuntiInApi as $dato) {
            echo "Dato aggiunto in API:<br>";
            print_r($dato);
        }
    }

    if (!empty($datiAggiuntiInSql)) {
        foreach ($datiAggiuntiInSql as $dato) {
            echo "Dato aggiunto in SQL:<br>";
            print_r($dato);
        }
    }

    // Generazione delle query di aggiornamento per i dati discordanti con dato nuovo in API
    genera_query_aggiornamento_per_dati_discordanti($apiDataNew);
}


// Debug: Stampa i dati mancanti da inviare all'API
echo "DEBUG - Dati mancanti da inviare all'API:<br>";
print_r($bulkData);

// Separazione dei dati per asset e semplici
$bulkDataAsset = [];
$bulkDataSemplici = [];

foreach ($bulkData as $tblcode => $data) {
    $config = $tablecodesConfig[$tblcode];
    if ($config['type'] === 'asset') {
        $bulkDataAsset[$tblcode] = $data;
    } else {
        $bulkDataSemplici[$tblcode] = $data;
    }
}

echo "DEBUG - Bulk per asset:<br>";
print_r($bulkDataAsset);
echo "<br>DEBUG - Bulk semplici:<br>";
print_r($bulkDataSemplici);
echo "<br>";

// Invio dei dati in bulk se presenti
if (!empty($bulkDataSemplici)) {
    echo "Tentativo di invio dati in bulk...<br>";
    inviaDatiInBulk($bulkDataSemplici);
    echo "Dati da inviare in bulk:<br>";
    print_r($bulkDataSemplici);
} else {
    echo "Nessun dato da inviare in bulk.<br>";
}

// Invio dei dati di asset in bulk se presenti
if (!empty($bulkDataAsset)) {
    foreach ($bulkDataAsset as $tblcode => $data) {
        if ($tblcode === 'MAT') {
            inviaDatiMATInBulk($data);
        } elseif ($tblcode === 'MOD') {
            inviaDatiMODInBulk($data);
        } else {
            inviaDatiSUPInBulk($data);
        }
    }
} else {
    echo "Nessun dato da inviare per asset.<br>";
}

global $apiCallCount;
echo "Numero totale di chiamate API effettuate: $apiCallCount<br>";

// Aggiorna i dati mancanti in SQL
foreach ($datiMancantiPerSql as $dato) {
    $tablecode = $dato['tblcode'];

    if (!isset($tablecodesConfig[$tablecode])) {
        echo "Configurazione per tablecode $tablecode non trovata.<br>";
        continue;
    }

    crea_tabella_temporanea($tablecode);

    $updateQuery = genera_query_aggiornamento($tablecode, $dato);
    echo "Query di aggiornamento:<br>$updateQuery<br>";
    esegui_query_aggiornamento($updateQuery, $tablecode);

    $insertQuery = genera_query_inserimento($tablecode);
    echo "Query di inserimento:<br>$insertQuery<br>";
    $database = $tablecodesConfig[$tablecode]['database'];
    esegui_query_inserimento($insertQuery, $database);

    elimina_tabella_temporanea($tablecode);
    echo "Tabella temporanea eliminata.<br>";
}

// Inizializza il file di log
$logFileEliminati = 'log_eliminati.json';
inizializzaLogEliminati($logFileEliminati);

// Aggiungi debug per verificare i dati eliminati in SQL
echo "DEBUG - Dati eliminati in SQL:<br>";
print_r($datiEliminatiInSql);

// Aggiorna il file di log con i nuovi record eliminati
aggiornaLogEliminati($logFileEliminati, $datiEliminatiInSql);

// Sincronizza il log dei dati eliminati con i dati dell'API
sincronizzaLogConApi($logFileEliminati);

// Controlla se l'esecuzione è bloccata
if (BLOCCA_ESECUZIONE) {
    echo "Blocco esecuzione attivo: tutte le chiamate API e le query SQL sono disabilitate.<br>";
}
