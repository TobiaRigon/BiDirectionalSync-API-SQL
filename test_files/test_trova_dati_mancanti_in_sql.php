<?php

require_once '../functions.php';

// Funzione di test per il debug
function test_trova_dati_mancanti_in_sql()
{
    // Simulazione di dati SQL
    $sqlData = [
        ['code' => '001', 'description' => 'Descrizione 1'],
        ['code' => '002', 'description' => 'Descrizione 2']
    ];

    // Simulazione di dati API
    $apiData = [
        ['code' => '002', 'description' => 'Descrizione 2'],
        ['code' => '003', 'description' => 'Descrizione 3']
    ];

    // Crea una mappa dei dati SQL
    $sqlDataMap = crea_mappa_dati($sqlData);

    // Trova i dati API mancanti in SQL
    $datiMancantiInSql = trova_dati_mancanti_in_sql($sqlDataMap, $apiData);

    // Stampa i dati mancanti in SQL
    echo "Dati mancanti in SQL:\n";
    print_r($datiMancantiInSql);
}

// Esegui il test
test_trova_dati_mancanti_in_sql();
