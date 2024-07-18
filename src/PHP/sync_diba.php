<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'get_tkn.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'functions_diba.php';

define('SITE_ID', 'S01');
define('TABLE_CODE', 'MOD');

refreshToken(); // Aggiorna Token

// Recupera tutti i codici
$codici = getCodeToUpdate();
echo "Codici estratti: \n";
print_r($codici);

// Recupera tutti i dati da S01MOD
$allData = getAllDataFromS01MOD();
// echo "Dati recuperati da S01MOD: \n";
// print_r($allData);

// Recupera i docid per i codici specificati
$docIds = getDocIds($allData, $codici);
echo "Doc IDs estratti: \n";
print_r($docIds);
