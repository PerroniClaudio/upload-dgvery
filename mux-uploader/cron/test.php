<?php

include '../../vendor/autoload.php';
$config = parse_ini_file('../../.env');

// Authentication Setup
$config = MuxPhp\Configuration::getDefaultConfiguration()
    ->setUsername($config['MUX_TOKEN_ID'])
    ->setPassword($config['MUX_TOKEN_SECRET']);

// API Client Initialization
$assetsApi = new MuxPhp\Api\AssetsApi(
    new GuzzleHttp\Client(),
    $config
);

try {
    // Create Asset Request
    $input = new MuxPhp\Models\InputSettings(["url" => "https://uploaddev.dgvery.com/mux-uploader/mux-uploads/videotest.mp4"]);
    $createAssetRequest = new MuxPhp\Models\CreateAssetRequest(["input" => $input, "playback_policy" => [MuxPhp\Models\PlaybackPolicy::_PUBLIC]]);

    // Ingest
    $result = $assetsApi->createAsset($createAssetRequest);

    $asssetid = $result->getData()->getPlaybackIds()[0]->getId();

    // Print URL
    print "Playback URL: https://stream.mux.com/" . $result->getData()->getPlaybackIds()[0]->getId() . ".m3u8\n";
} catch (Exception $e) {
    print_r($e);
    exit("err_con");
}
