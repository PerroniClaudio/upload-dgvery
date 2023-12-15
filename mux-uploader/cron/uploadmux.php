<?php

$config = parse_ini_file('../../.env');

try {
    $dbmainconn = new PDO("mysql:host=" . $config['HOST'] . ";dbname=" . $config['MAIN_DB'] . ";charset=utf8", $config['CRON_USER'], $config['CRON_PASSWORD']);
    $dbmainconn->exec("SET time_zone='Europe/Rome';");
    $dbmainconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    print_r($e);
    exit("err_con");
}

$mux_upload_url = json_decode(generateUploadUrl($config['MUX_TOKEN_ID'], $config['MUX_TOKEN_SECRET']));;

exit();

$db_exceptions = ["el_integys_com", "elearning_main", "test_el_dgvery_com", "DGVERY_BASE"];
$qr = $dbmainconn->query('SHOW DATABASES');

foreach ($qr->fetchAll() as $result) {

    if ($result[0] != "information_schema" && $result[0] != "performance_schema" && $result[0] != "sys" && $result[0] != "mysql" && !in_array($result[0], $db_exceptions)) {

        try {
            echo "[DB NAME : " . $result[0] . "]\n";
            $db = new PDO("mysql:host={$config['HOST']};dbname=$result[0]", $config['CRON_USER'], $config['CRON_PASSWORD']);

            $sql_get_files = "SELECT * FROM transcoding WHERE state = :state";
            $statement_get_files = $db->prepare($sql_get_files);
            $statement_get_files->execute(array(
                "state" => "ready_for_mux"
            ));

            while ($video = $statement_get_files->fetch()) {

                // Percorso completo del file video
                $videoFilePath = realpath("../mux-uploads/" . $videoFileName);

                // Inizializza cURL
                $ch = curl_init($mux_upload_url->data->url);

                // Imposta le opzioni della richiesta cURL
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_PUT, true);
                curl_setopt($ch, CURLOPT_INFILE, fopen($videoFilePath, 'r'));
                curl_setopt($ch, CURLOPT_INFILESIZE, filesize($videoFilePath));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                // Esegui la richiesta cURL e ottieni la risposta
                $response = curl_exec($ch);

                // Verifica se c'è un errore nella richiesta
                if (curl_errno($ch)) {
                    echo 'Errore cURL: ' . curl_error($ch);
                }

                // Chiudi la risorsa cURL
                curl_close($ch);

                // Stampa la risposta
                $response_data = json_decode($response);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            echo "\n\n";
        }
    }
}

function generateUploadUrl($muxTokenId,  $muxTokenSecret): string {


    // URL dell'API Mux
    $url = 'https://api.mux.com/video/v1/uploads';

    // Dati da inviare nel corpo della richiesta
    $data = array(
        'cors_origin' => '*',
        'new_asset_settings' => array(
            'playback_policy' => ['public']
        )
    );

    // Inizializza cURL
    $ch = curl_init($url);

    // Imposta le opzioni della richiesta cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
    ));
    curl_setopt($ch, CURLOPT_USERPWD, $muxTokenId . ':' . $muxTokenSecret);

    // Esegui la richiesta cURL e ottieni la risposta
    $response = curl_exec($ch);

    // Verifica se c'è un errore nella richiesta
    if (curl_errno($ch)) {
        echo 'Errore cURL: ' . curl_error($ch);
    }

    // Chiudi la risorsa cURL
    curl_close($ch);

    // Stampa la risposta
    return $response;
}
