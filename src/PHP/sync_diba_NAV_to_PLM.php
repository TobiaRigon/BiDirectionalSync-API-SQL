<?php
ini_set('max_execution_time', 600); // Aumenta il tempo massimo di esecuzione a 600 secondi


require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'get_tkn.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'functions_diba.php';
require_once 'functions_Diba_NAV_to_PLM.php';

// Definizione delle costanti
define('SITE_ID', 'S01');
define('TABLE_CODE', 'MOD');
$defid = SITE_ID . TABLE_CODE;

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

// Recupera tutti i codici
$codici = getCodeToUpdate();
echo "Codici estratti \n</br>";

// Recupera i dati dal database
$dati = recuperaDatiDalDB($serverName, $database, $username, $password);

// Filtra e correggi i dati
$datiCorretti = filtraECorreggiDati($dati);

// Verifica se ci sono dati da preparare
if (!empty($datiCorretti)) {
    // Prepara i dati nel formato richiesto
    $datiPreparati = preparaDati($datiCorretti);

    // Filtra i dati in base ai codici
    $datiFiltrati = filtraDatiPerCodici($datiPreparati, $codici);

    // Verifica se ci sono dati da inviare
    if (!empty($datiFiltrati)) {
        // Invia i dati all'API in batch con retry

        inviaDatiInBatchConRetry($datiFiltrati, $baseApiUrl, $token, SITE_ID, $defid, 50, 5); // Batch size 50, max retries 5
    } else {
        echo "Nessun dato da inviare dopo il filtraggio\n";
    }
} else {
    echo "Nessun dato da preparare\n";
}
