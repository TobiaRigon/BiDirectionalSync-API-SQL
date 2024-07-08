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

// Test della creazione della tabella temporanea
$tablecode = 'UM'; // Sostituisci con un altro tablecode appropriato
createTemporaryTable($tablecode);

// Recupera i dati API mancanti in SQL (esempio di utilizzo di trova_dati_mancanti_in_sql)
$apiDataArray = getApiDataExample($tablecode); // Sostituisci con la tua funzione reale

// Aggiorna la tabella temporanea con ogni record API
foreach ($apiDataArray as $apiData) {
    updateTemporaryTable($tablecode, $apiData);
}
