<?php
require_once '../config.php';
// require_once '../functions.php';

// Funzione per verificare l'esistenza delle variabili di ambiente
function getEnvVariable($key)
{
    if (!isset($_ENV[$key])) {
        die("Errore: La variabile di ambiente '{$key}' non è definita nel file .env.");
    }
    return $_ENV[$key];
}

function genera_query_aggiornamento($tablecode, $apiData)
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

    // Generare la query di aggiornamento dinamica
    $columns = [];
    $values = [];
    foreach ($apiData as $column => $value) {
        $columns[] = '[' . $column . '] = ?';
        $values[] = $value;
    }
    $updateColumns = implode(', ', $columns);

    // Assicurarsi di aggiornare solo il record specifico
    // Usare il nome del database completo nella clausola WHERE
    $updateQuery = "UPDATE [$tempDatabaseName].[dbo].[$tempTableName] SET $updateColumns WHERE [Code] = (SELECT TOP 1 [Code] FROM [$tempDatabaseName].[dbo].[$tempTableName])";

    // Aggiungi il valore del codice alla fine dei valori
    $values[] = $apiData['code'];

    // Sostituisci i segnaposto con i valori
    foreach ($values as $key => $value) {
        $values[$key] = "'" . $value . "'";
    }

    $updateQuery = str_replace('?', '%s', $updateQuery);
    $fullQuery = vsprintf($updateQuery, $values);

    return $fullQuery;
}

// Funzione di test per il debug
function test_genera_query_aggiornamento()
{
    // Simulazione di dati API mancanti in SQL
    $tablecode = 'UM';
    $apiData = [
        'code' => '003',
        'description' => 'Desc3',

    ];

    // Generare la query di aggiornamento
    $fullQuery = genera_query_aggiornamento($tablecode, $apiData);

    // Stampa la query completa
    echo "Query di aggiornamento generata:\n$fullQuery\n";
}

// Esegui il test
test_genera_query_aggiornamento();
