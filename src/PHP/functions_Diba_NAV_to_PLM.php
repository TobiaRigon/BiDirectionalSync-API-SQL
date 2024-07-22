<?php

function getAllDocIdsPage($baseUrl, $token, $skipArray)
{
    $headers = array(
        "Authorization: Bearer " . $token,
        "Content-Type: application/json"
    );

    $urlArray = [];
    foreach ($skipArray as $skip) {
        $datas = array(
            'limit' => PER_PAGE,
            'skip' => $skip
        );
        $query_string = http_build_query($datas);
        $urlArray[] = $baseUrl . "?$query_string";
    }

    $multiCurl = [];
    $result = [];
    $mh = curl_multi_init();

    foreach ($urlArray as $i => $url) {
        $multiCurl[$i] = curl_init();
        curl_setopt($multiCurl[$i], CURLOPT_URL, $url);
        curl_setopt($multiCurl[$i], CURLOPT_HTTPHEADER, $headers);
        curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($mh, $multiCurl[$i]);
    }

    $index = null;
    do {
        curl_multi_exec($mh, $index);
        curl_multi_select($mh);
    } while ($index > 0);

    foreach ($multiCurl as $i => $ch) {
        $response = curl_multi_getcontent($ch);
        $data = json_decode($response, true);

        if (!is_array($data)) {
            echo "Errore nella risposta JSON\n";
            print_r($data); // Debugging extra
            continue;
        }

        // Stampa l'URL per il debug
        echo "DEBUG - Endpoint API:(getAllDocIdsPage): $url\n";

        foreach ($data as $item) {
            if (isset($item['docid'])) {
                $result[] = $item['docid']; // Estrai il docid
            }
        }
        curl_multi_remove_handle($mh, $ch);
    }

    curl_multi_close($mh);

    return $result;
}

// Funzione per ottenere tutti i docid utilizzando la paginazione
function getAllDocIds($baseUrl, $token)
{
    $allDocIds = [];
    $skip = 0;
    $batchSize = 10; // Numero di richieste da eseguire in parallelo

    while (true) {
        $skipArray = range($skip, $skip + ($batchSize - 1) * PER_PAGE, PER_PAGE);
        $docIds = getAllDocIdsPage($baseUrl, $token, $skipArray);

        $allDocIds = array_merge($allDocIds, $docIds);

        if (count($docIds) < PER_PAGE * $batchSize) {
            break;
        }

        $skip += PER_PAGE * $batchSize;

        // Aggiungi un intervallo di tempo per evitare il superamento del tempo massimo di esecuzione
        sleep(1);
    }

    return $allDocIds;
}
