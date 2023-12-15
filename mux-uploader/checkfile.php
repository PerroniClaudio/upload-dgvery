<?php

require '../vendor/autoload.php';
include '../config.php';

$file_name = $_POST["filename"];
$file = "/var/www/html/uploads/" . $file_name;


$video_id = "";
$table_update = "video_library";

$errors = array(
    'ffmpeg' => "true",
    'risoluzione' => "false",
    'aspratio' => "false",
    'durata' => "false",
    'dimensione' => "false",
    'id' => "",
    'video_id' => ''
);

//delete_directory("/var/www/html/uploads/");

switch ($_POST['type']) {
    case 'modulo-temp':
        $module = $db->query("SELECT title FROM temp_courses_modules WHERE cmoid = {$_POST['cmoid']}")->fetch();
        $video_id = createVideo($module['title']);
        $update_module = "UPDATE temp_courses_modules SET video_id = ? WHERE cmoid = ?";
        $statement = $db->prepare($update_module);
        $statement->execute(array(
            $video_id,
            $_POST['cmoid']
        ));
        break;
    case 'modulo-def':
        $module = $db->query("SELECT title FROM courses_modules WHERE cmoid = {$_POST['cmoid']}")->fetch();
        $video_id = createVideo($module['title']);
        $update_module = "UPDATE courses_modules SET video_id = ? WHERE cmoid = ?";
        $statement = $db->prepare($update_module);
        $statement->execute(array(
            $video_id,
            $_POST['cmoid']
        ));
        break;
    case 'live':
        $module = $db->query("SELECT title FROM live_streams WHERE id = {$_POST['live_id']}")->fetch();
        $video_id = createVideo($module['title']);
        $update_module = "UPDATE live_streams SET video_id = ? WHERE id = ?";
        $statement = $db->prepare($update_module);
        $statement->execute(array(
            $video_id,
            $_POST['live_id']
        ));
        break;
    case 'articolo-video':
        $cntnoq = $db->quote($_POST['cntno']);
        $cnt = $db->query("SELECT title FROM contents WHERE cntno = $cntnoq")->fetch();
        $video_id = createVideo($cnt['title']);
        $update_cnt = "UPDATE contents SET video_id = ? WHERE cntno = ?";
        $statement = $db->prepare($update_cnt);
        $statement->execute(array(
            $video_id,
            $_POST['cntno']
        ));
        break;
    case 'video':
        $video_id = $_POST['video_id'];
        break;
}

$sql = "INSERT INTO transcoding (from_table,from_id,high_performance,state) VALUES (?, ?, ?, ?)";
$statement = $db->prepare($sql);
$statement->execute(array($table_update, $video_id, 0, 'uploaded_on_server'));
$upload_id = $db->lastInsertId();
$errors['id'] = $upload_id;
$errors['video_id'] = $video_id;

$sql = "INSERT INTO transcoding_timestamps (state,transcoding_id) VALUES (?,?) ";
$statement = $db->prepare($sql);
$statement->execute(array('uploaded_on_server', $upload_id));

if (file_exists($file)) {

    chmod($file, 0775);

    try {

        $ffprobe = FFMpeg\FFProbe::create();
        $ffmpeg = FFMpeg\FFMpeg::create();



        $streams = $ffprobe->streams($file);

        $dimension = $streams->videos()->first()->getDimensions();
        $aspratio = $dimension->getRatio()->getValue();
        $height = $dimension->getHeight();
        $width = $dimension->getWidth();

        //Risoluzione  a 4k

        if (($height > 2164) && ($height > 3844)) {
            $errors['risoluzione'] = "false";
        } else {
            $errors['risoluzione'] = "true";
        }


        //Aspect ratio in 16:9

        if (($height < 2164) && ($height > 478) && ($width < 3844) && ($width > 848)) {
            if (round($aspratio, 2)  == 1.78) {
                $errors['aspratio'] = "true";
            } else {
                $errors['aspratio'] = "false";
            }
        } else {
            $errors['aspratio'] = "false";
        }

        $res = $dimension->getWidth() . "x" . $dimension->getHeight();

        //Durata minore di 1 ora

        $duration = round($ffprobe->streams($file)->videos()->first()->get('duration'));

        if ($duration > 36000) {
            $errors['durata'] = "false";
        } else {
            $errors['durata'] = "true";
        }

        //File fino a 10GB

        $size = filesize($file);

        if ($size > 10737418240) {
            $errors['dimensione'] = "false";
        } else {
            $errors['dimensione'] = "true";
        }

        $check = true;
        foreach ($errors as $element) {
            if ($element == "false") {
                $check = false;
            }
        }

        $sql = "UPDATE transcoding SET  
                    duration = ?,
                    resolution = ?,
                    file_size = ?,
                    state = ?
                WHERE id = ? ";
        $statement = $db->prepare($sql);
        $statement->execute(array($duration, $res, $size, 'errors_checked', $upload_id));

        if ($check) {

            if ($streams->audios()->count() == 0) {
                $sql = "UPDATE transcoding SET  
                state = ?
                WHERE id = ? ";
                $statement = $db->prepare($sql);
                $statement->execute(array('no_audio', $upload_id));
                $errors['no_audio'] = "true";
                unlink($file);

                echo json_encode($errors);

                exit();
            }

            $ts = explode("_", $file_name);
            $pictures = array("/var/www/html/mux-uploader/mux-uploads/{$ts[0]}/picture1.jpg", "/var/www/html/mux-uploader/mux-uploads/{$ts[0]}/picture2.jpg", "/var/www/html/mux-uploader/mux-uploads/{$ts[0]}/picture3.jpg", "/var/www/html/mux-uploader/mux-uploads/{$ts[0]}/picture4.jpg");
            $pictures2 = array();

            mkdir("/var/www/html/mux-uploader/mux-uploads/{$ts[0]}/");

            try {
                $video = $ffmpeg->open($file);
                $durationUnit = $duration / 4;

                for ($i = 0; $i <= 3; $i++) {

                    $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(($durationUnit * $i) + 1))->save($pictures[$i], false, false);
                    $pictures2[] = "data:image/jpeg;base64," . base64_encode(file_get_contents($pictures[$i]));
                    //unlink($pictures[$i]);
                }
            } catch (Exception $e) {
                $sql = "UPDATE transcoding SET  
                state = ?
                WHERE id = ? ";
                $statement = $db->prepare($sql);
                $statement->execute(array('video_corrupt', $upload_id));
                $errors['video_corrupt'] = "true";
                $errors['motivation'] = $e->getMessage();
                unlink($file);



                echo json_encode($errors);

                exit();
            }



            $sql = "UPDATE $table_update SET duration = ?, size = ?, resolution = ?, picture1 = ?, picture2 = ?, picture3 = ?, picture4 = ? WHERE id = ?";
            $statement = $db->prepare($sql);
            $statement->execute(array(
                $duration,
                $size,
                $res,
                $pictures2[0],
                $pictures2[1],
                $pictures2[2],
                $pictures2[3],
                $video_id
            ));

            switch ($_POST['type']) {
                case 'modulo-temp':
                    $sql = "UPDATE temp_courses_modules SET duration = ? WHERE cmoid = ?";
                    $statement = $db->prepare($sql);
                    $statement->execute(array(
                        $duration,
                        $_POST['cmoid']
                    ));
                    break;
                case 'modulo-def':
                    $sql = "UPDATE courses_modules SET duration = ? WHERE cmoid = ?";
                    $statement = $db->prepare($sql);
                    $statement->execute(array(
                        $duration,
                        $_POST['cmoid']
                    ));
                    break;
                default:
                    $video = "ok";
                    break;
            }


            $fn = $_POST['origin'] . "/{$video_id}/" . $file_name;

            // $result = $bucket->upload( 
            //     fopen($file,'r'),
            //     ['name' => $_POST['origin']."/{$video_id}/".$file_name]
            // );

            $sql = "UPDATE transcoding SET  
                    state = ?,
                    file_name = ?
                WHERE id = ? ";
            $statement = $db->prepare($sql);
            $statement->execute(array('ready_for_mux', $file_name, $upload_id));

            $sql = "INSERT INTO transcoding_timestamps (state,transcoding_id) VALUES (?,?) ";
            $statement = $db->prepare($sql);
            $statement->execute(array('ready_for_mux', $upload_id));

            //unlink($file);

            echo json_encode($errors);
        } else {
            $sql = "UPDATE transcoding SET  
                    state = ?,
                    errors = ?
                WHERE id = ? ";
            $statement = $db->prepare($sql);
            $statement->execute(array('errors_failed', "errore_parametri", $upload_id));
            unlink($file);

            echo json_encode($errors);
        }
    } catch (Exception $e) {
        $errors["ffmpeg"] = "false";

        $sql = "UPDATE transcoding SET state = ?, errors = ? WHERE id = ? ";
        $statement = $db->prepare($sql);
        $statement->execute(array('errors_failed', $e->getMessage(), $upload_id));
        unlink($file);


        echo json_encode($errors);
    }
} else {
    $errors['message'] = "Il video non è arrivato";
    echo json_encode($errors);
}



function createVideo($title) {
    global $db;

    $check_code = true;

    while ($check_code) {
        $bucket_code = generateRandomString(6, '0123456789');
        $bucket_code_quoted = $db->quote($bucket_code);

        $check_code_query = $db->query("SELECT * FROM video_library WHERE bucket_code = $bucket_code_quoted");

        if ($check_code_query->rowCount() == 0) {
            $check_code = false;
            $code = $bucket_code;
        }
    }

    $sql = "INSERT INTO video_library (title,bucket_code) VALUES (?,?)";
    $statement = $db->prepare($sql);
    $statement->execute(array(
        $title,
        $code
    ));

    return $db->lastInsertId();
}

function delete_directory($dirname) {

    global $file;

    $dir = $dirname;
    $di = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($ri as $file_del) {
        if ($file_del != $file) {
            $file_del->isDir() ?  rmdir($file_del) :
                unlink($file_del);
        }
    }
    return true;
}
