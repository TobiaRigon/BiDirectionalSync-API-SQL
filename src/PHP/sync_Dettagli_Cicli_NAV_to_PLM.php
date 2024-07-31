<?php
ini_set('max_execution_time', 0);  // Aumenta il tempo massimo di esecuzione a 0 secondi(infinito)
ini_set('memory_limit', '1024M'); // Aumenta il limite di memoria a 1024 MB

require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'get_tkn.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'functions_diba.php';
require_once 'functions_Dettagli_Cicli_NAV_to_PLM.php';

// Cambia numero di batch
$numeroBatch = 4000;

// Definizione delle costanti
define('SITE_ID', 'S01');
$siteid = SITE_ID;
define('TABLE_CODE', 'MOD');
$defid = SITE_ID . TABLE_CODE;
define('MAX_BATCH_SIZE_BYTES', ((51.26 / $numeroBatch) * 1024 * 1024));
// $docidURL = ASSET_API_URL;

// Carica le variabili d'ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

refreshToken(); // Aggiorna Token

$baseApiUrl = BASE_API_URL;
$token = API_TOKEN; // Recupera il token d'ambiente

// Configurazione della connessione al database
$serverName = NOME_SERVER;
$database = 'PP_2017_TST';
$username = UID;
$password = PWD;

// $codesS01MAT = getCodesFromEndpoint("S01MAT", $token);
$codesS01MOD = getCodesFromEndpoint("S01MOD", $token);

// Recupera i dati dal database
$dati = recuperaDatiDalDB($serverName, $database, $username, $password);

// Verifica se ci sono dati da preparare
if (!empty($dati)) {
    // Prepara i dati nel formato richiesto
    $datiPreparati = preparaDati($dati);

    // Verifica se ci sono dati da inviare
    if (!empty($datiPreparati)) {
        // Invia i dati all'API in batch con retry
        $stampa = json_encode($datiPreparati);
        print_r($stampa);
        //  inviaDatiInBatchConRetry($datiPreparati, $baseApiUrl, $token, SITE_ID, $defid, 5); // Max retries 5
    } else {
        echo "Nessun dato da inviare dopo il filtraggio\n";
    }
} else {
    echo "Nessun dato da preparare\n";
}
