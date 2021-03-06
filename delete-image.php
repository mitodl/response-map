<?php
require_once('configuration.php');

session_start();
setcookie(session_name(), session_id(), time() + 1800);

if (empty($_SESSION['authenticated'])) {
    echo 'Error: You do not have permission to visit this page.';
    die();
}

if (!empty($_POST['image'])) {
    $filename = explode('/', $_POST['image']);
    $filename = end($filename);
    $success = false;

    // make sure the 'filename' does not cause rerouting before proceeding
    if (strstr($filename, '..')) {
        http_response_code(400);
        echo 'Invalid file name is given.';
        die();
    }

    $response_query = mysqli_stmt_init($conn);
    mysqli_stmt_prepare($response_query, 'SELECT user_id FROM response WHERE id=? and resource_id=? LIMIT 1');
    mysqli_stmt_bind_param($response_query, 'ii', $_POST['id'], $_SESSION['resource']['id']);
    mysqli_stmt_execute($response_query);
    mysqli_stmt_bind_result($response_query, $user_id);
    mysqli_stmt_fetch($response_query);
    mysqli_stmt_close($response_query);

    if ($_SESSION['user']['id'] != $user_id) {
        mysqli_close($conn);
        echo 'You do not have permission to delete the image.';
        die();
    }

    if (!empty($_POST['id'])) {
        $query = "UPDATE response SET image_url=NULL, thumbnail_url=NULL WHERE id=?";
        $update_response_query = mysqli_stmt_init($conn);
        mysqli_stmt_prepare($update_response_query, $query);
        mysqli_stmt_bind_param($update_response_query, 'i', $_POST['id']);
        $success = mysqli_stmt_execute($update_response_query);
        mysqli_stmt_close($update_response_query);
    } else {
        $success = true;
    }

    mysqli_close($conn);

    if ($success) {
        if (file_exists(__DIR__. '/files/' . $filename) && file_exists(__DIR__. '/files/thumbnail/' . $filename)) {
            $success = unlink(__DIR__. '/files/' . $filename) && unlink(__DIR__. '/files/thumbnail/' . $filename);
        }
    }

    echo json_encode(array('result' => $success, 'file' => $filename));
} else {
    mysqli_close($conn);
    http_response_code(400);
    echo 'Error: Image cannot be deleted.';
}