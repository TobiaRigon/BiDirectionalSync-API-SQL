<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Utilizza __DIR__ per risalire alla cartella principale
require_once __DIR__ . '/../functions.php'; // Assicurati che il percorso sia corretto in base alla tua struttura
require_once __DIR__ . '/../config.php';

use Dotenv\Dotenv;

// Carica il file .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Assicurati che le costanti siano definite
if (!defined('NOME_SERVER')) {
    define('NOME_SERVER', getEnvVariable('NOME_SERVER'));
}
if (!defined('UID')) {
    define('UID', getEnvVariable('UID'));
}
if (!defined('PWD')) {
    define('PWD', getEnvVariable('PWD'));
}

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

    // Query per creare la tabella temporanea
    $createQuery = "SELECT TOP 0 * INTO [$tempDatabaseName].[dbo].[$tempTableName] FROM [$originalDatabaseName].[dbo].[$originalTableName]";

    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => $tempDatabaseName,
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $stmt = sqlsrv_query($conn, $createQuery);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
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

function esegui_query_aggiornamento($query)
{
    $conn = sqlsrv_connect(NOME_SERVER, [
        "Database" => getEnvVariable('DB_TEMP'),
        "UID" => UID,
        "PWD" => PWD
    ]);

    if ($conn === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
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

function esegui_query_inserimento($query, $database)
{
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
        die(print_r(sqlsrv_errors(), true));
    }

    sqlsrv_free_stmt($stmt);
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

// =========================================
function test_completo()
{
    global $tablecodesConfig; // Assicurati che sia globale

    $tablecode = 'UM';
    $apiData = [
        'code' => 'TST1',
        'description' => 'Desc1',
    ];

    // 1. Creare la tabella temporanea
    crea_tabella_temporanea($tablecode);
    echo "Tabella temporanea creata.\n";

    // 2. Generare e eseguire la query di aggiornamento
    $updateQuery = genera_query_aggiornamento($tablecode, $apiData);
    echo "Query di aggiornamento:\n$updateQuery\n"; // Stampa la query per il debug
    esegui_query_aggiornamento($updateQuery);

    // 3. Generare e eseguire la query di inserimento
    $insertQuery = genera_query_inserimento($tablecode);
    echo "Query di inserimento:\n$insertQuery\n"; // Stampa la query per il debug
    $database = $tablecodesConfig[$tablecode]['database'];
    esegui_query_inserimento($insertQuery, $database);

    // 4. Eliminare la tabella temporanea
    elimina_tabella_temporanea($tablecode);
    echo "Tabella temporanea eliminata.\n";
}

// Esegui il test completo
test_completo();
