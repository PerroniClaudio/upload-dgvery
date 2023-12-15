<?php

$config = parse_ini_file('./.env');

function generateRandomString($length = 10, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {

    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

try {
    $dbmainconn = new PDO("mysql:host=" . $config['HOST'] . ";dbname=" . $config['MAIN_DB'] . ";charset=utf8", $config['MAIN_USER'], $config['MAIN_PASSWORD']);
    $dbmainconn->exec("SET time_zone='Europe/Rome';");
    $dbmainconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    print_r($e);
    exit("err_con");
}

$domain = $dbmainconn->quote($_POST['origin']);
$row = $dbmainconn->query("SELECT * FROM domains WHERE name = $domain")->fetch();
$config = json_decode($row["config"], true);

$DB_HOST = "35.240.94.79";
$DB_USER = $config["USER"];
$DB_PASS = $config["PASSWORD"];
$DB_NAME = $config["DB"];
$url_client = $config["URL_BASE"];

try {
    $timezone = "Europe/Rome";
    $db = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME", $DB_USER, $DB_PASS, array(PDO::ATTR_PERSISTENT => true));
    date_default_timezone_set('Europe/Rome');
    $time_offset = date('P', time()); // +00:00
    $db->query("SET time_zone='$time_offset';");
    // set the PDO error mode to exception
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed 1: " . $e->getMessage() . "\n";
    print_r($config);
}
