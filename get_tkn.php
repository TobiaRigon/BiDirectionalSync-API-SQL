<?php

require_once 'functions.php';

require_once __DIR__ . '/vendor/autoload.php';



use Dotenv\Dotenv;



// Carica il file .env

$dotenv = Dotenv::createImmutable(__DIR__);

$dotenv->load();




// ===Definisce le costanti per Sql===

define('NOME_SERVER', getEnvVariable('NOME_SERVER'));

define('DB_TKN', getEnvVariable('DB_TKN'));

define('UID', getEnvVariable('UID'));

define('PWD', getEnvVariable('PWD'));



// Funzione per recuperare il token dal database

function getNewTokenFromDatabase()

{

    $serverName = NOME_SERVER;

    $connectionOptions = array(

        "Database" => DB_TKN,

        "Uid" => UID,

        "PWD" => PWD

    );



    // Connessione al database

    $conn = sqlsrv_connect($serverName, $connectionOptions);

    if ($conn === false) {

        echo "Errore nella connessione al database:\n";

        die(print_r(sqlsrv_errors(), true));
    }



    // Esegui la query per recuperare il token

    $sql = "SELECT TOP 1 [Note], [ActName], [ActDateStart], [ActDateEnd], [LastUpdate]

            FROM [dbo].[pUpdateAct]

            WHERE [ActName] = 'Kubix_API'

            ORDER BY [LastUpdate] DESC";



    $stmt = sqlsrv_query($conn, $sql);

    if ($stmt === false) {

        echo "Errore nell'esecuzione della query:\n";

        die(print_r(sqlsrv_errors(), true));
    }




    // Recupera i risultati

    $token = null;

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {



        if (trim($row['Note']) != '') {

            $token = $row['Note'];
        } else {

            echo "Nota trovata ma vuota o con solo spazi.\n";
        }
    } else {

        echo "Nessun risultato trovato.\n";
    }



    sqlsrv_free_stmt($stmt);

    sqlsrv_close($conn);



    return $token;
}



// Funzione per aggiornare il file .env

function updateEnvFile($token)

{

    $envFilePath = __DIR__ . '/.env';

    $envContent = file_get_contents($envFilePath);

    $newContent = preg_replace('/API_TOKEN=.*/', "API_TOKEN=$token", $envContent);

    file_put_contents($envFilePath, $newContent);
}



// Recupera il nuovo token dal database e aggiorna il file .env

function refreshToken()

{

    $newToken = getNewTokenFromDatabase();

    if ($newToken) {

        updateEnvFile($newToken);

        echo "Token aggiornato con successo nel file .env.\n </br>";
    } else {

        echo "Errore nell'ottenimento del nuovo token.\n";
    }
}
