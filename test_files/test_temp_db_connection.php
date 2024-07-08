<?php

require_once __DIR__ . '../vendor/autoload.php';
require_once '../functions.php';

use Dotenv\Dotenv;

// Carica il file .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Test della connessione al database temporaneo
$conn = connectToTemporaryDatabase();
if ($conn) {
    echo "Connessione al database temporaneo stabilita con successo.\n";
    sqlsrv_close($conn);
} else {
    echo "Errore durante la connessione al database temporaneo.\n";
}
