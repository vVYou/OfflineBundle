<?php
    ini_set('max_execution_time', 0);
    echo("Je me lance<br/>");
    $ds = DIRECTORY_SEPARATOR;
    $command = 'php '.__DIR__.$ds.'..'.$ds.'app'.$ds.'console claroline:install';
    exec($command);
    var_dump($command);
    echo "termine<br/>";
    //UPDATE isInstalled
    file_put_contents( '..'.$ds.'app'.$ds.'config'.$ds.'is_installed.php' , '<?php  return true;');
    $url = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '/../app_offline.php';
    header("Location: http://{$url}");
?>
    