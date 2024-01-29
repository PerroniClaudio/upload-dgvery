<?php


include '/var/www/html/vendor/autoload.php';
$config = parse_ini_file('/var/www/html/.env');

try {
    $dbmainconn = new PDO("mysql:host=" . $config['HOST'] . ";dbname=" . $config['MAIN_DB'] . ";charset=utf8", $config['CRON_USER'], $config['CRON_PASSWORD']);
    $dbmainconn->exec("SET time_zone='Europe/Rome';");
    $dbmainconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    print_r($e);
    exit("err_con");
}

$sql = "UPDATE crons SET last_run_at = CURRENT_TIMESTAMP WHERE cron_name = :cron_name";
$statement = $dbmainconn->prepare($sql);
$statement->execute(array("cron_name" => "uploadmux"));

$muxConfig = MuxPhp\Configuration::getDefaultConfiguration()
    ->setUsername($config['MUX_TOKEN_ID'])
    ->setPassword($config['MUX_TOKEN_SECRET']);

// API Client Initialization
$assetsApi = new MuxPhp\Api\AssetsApi(
    new GuzzleHttp\Client(),
    $muxConfig
);

$db_exceptions = ["el_integys_com", "elearning_main", "DGVERY_BASE"];
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

                // Crea una Asset Request
                $input = new MuxPhp\Models\InputSettings(["url" => "https://uploaddev.dgvery.com/mux-uploader/mux-uploads/" . $video["file_name"]]);
                $createAssetRequest = new MuxPhp\Models\CreateAssetRequest(["input" => $input, "playback_policy" => [MuxPhp\Models\PlaybackPolicy::_PUBLIC]]);
                $assetResult = $assetsApi->createAsset($createAssetRequest);

                $asssetid = $assetResult->getData()->getPlaybackIds()[0]->getId();

                $sql_update_video = "UPDATE video_library SET playback_id = :mux_asset_id WHERE id = :id";
                $statement_update_video = $db->prepare($sql_update_video);
                $statement_update_video->execute(array(
                    "mux_asset_id" => $asssetid,
                    "id" => $video["from_id"]
                ));

                $sql_update_transcoding = "UPDATE transcoding SET state = :state WHERE id = :id";
                $statement_update_transcoding = $db->prepare($sql_update_transcoding);
                $statement_update_transcoding->execute(array(
                    "state" => "transcoding_finished",
                    "id" => $video["id"]
                ));
                
                $sql = "INSERT INTO transcoding_timestamps (state,transcoding_id) VALUES (?,?) ";
                $statement = $db->prepare($sql);
                $statement->execute(array('transcoding_finished', $video["id"]));
              
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            echo "\n\n";
        }
    }
}
