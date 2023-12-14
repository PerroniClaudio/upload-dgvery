<?php 

$file_path = "./mux-uploads/".$_POST['filename'];
$data = explode( ';base64,', $_POST['binary_data'] );
$data = base64_decode( $data[1] );
file_put_contents( $file_path, $data, FILE_APPEND );

file_put_contents("log.txt", $_POST['filename'] . " -- ", FILE_APPEND);