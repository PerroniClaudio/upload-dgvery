<?php 

define("HOST","35.240.94.79");
define("MAIN_DB","elearning_main");
define("MAIN_USER","ad_elearning");
define("MAIN_PASSWORD","i8JskcDLgMOgQUQU");

try {
    $dbmainconn = new PDO("mysql:host=". HOST . ";dbname=" . MAIN_DB . ";charset=utf8", MAIN_USER, MAIN_PASSWORD);
    $dbmainconn->exec("SET time_zone='Europe/Rome';");
    $dbmainconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    print_r($e);
    exit("err_con");
}


$response = [
    'id' => '',
    'errors' => 0,
    'error_type' => '',
    'error_message' => ''
];

try {


    $sql_update_internal = "SELECT * FROM sslcert_system ORDER BY rdate DESC";
    $statement_update_internal = $dbmainconn->prepare($sql_update_internal);
    $statement_update_internal->execute(array());

    if($statement_update_internal->rowCount() > 0) {

        $newcert = $statement_update_internal->fetch();

        $public = fopen("/var/www/sslcert/uploaddev.dgvery.com/public.crt", 'w');
        fwrite($public, $newcert['public']);
        fclose($public);

        $intermediate = fopen("/var/www/sslcert/uploaddev.dgvery.com/intermediate.crt", 'w');
        fwrite($intermediate, $newcert['intermediate']);
        fclose($intermediate);

        $private = fopen("/var/www/sslcert/uploaddev.dgvery.com/private.key", 'w');
        fwrite($private, $newcert['private']);
        fclose($private);

        echo json_encode($response);
        exec("sudo systemctl restart apache2");

    }

} catch(Exception $e) {
    $response['error_type'] = "generic";
    $response['error_message'] = $e->getMessage();
    $response['errors'] = 1;

    echo json_encode($response);

    exit();
}

