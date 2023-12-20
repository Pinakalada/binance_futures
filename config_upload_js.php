<?php

// print "test_1";
// sleep(5);
// print "test_2";

// echo "test_1";
// sleep(5);
// echo "test_2";

// die;

$result = [
    'type' => 'error',
    'msg' => ''
];

// $result = json_encode($result);
// echo $result;
// die;

// if (isset($_POST['config_upload'])) {
    if (isset($_FILES['config_file']) && $_FILES['config_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['config_file']['tmp_name'];
        $fileName = $_FILES['config_file']['name'];
        $fileSize = $_FILES['config_file']['size'];
        $fileType = $_FILES['config_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // массив разрешенных расширений и проверка
        $allowedfileExtensions = array('xlsx');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = 'config' . '.' . $fileExtension;

            $uploadFileDir = './';
            $dest_path = $uploadFileDir . $newFileName;
            if(move_uploaded_file($fileTmpPath, $dest_path))
            {
                require_once('./v4.php');
            }
            else
            {
                $result['msg'] = 'Ошибка загрузки конфига.';
                // echo 'Ошибка загрузки конфига.';
            }
        }else{
            $result['msg'] = 'Расширение файла не верно.';
            // echo "Расширение файла не верно.";
        }
    // }
}else{
    $result['msg'] = 'Ошибка передачи файла.';
}

$result = json_encode($result);
echo $result;