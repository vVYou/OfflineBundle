<?php
    ini_set('max_execution_time', 0);
    $ds = DIRECTORY_SEPARATOR;
    $command = 'php '.__DIR__.$ds.'..'.$ds.'app'.$ds.'console claroline:install';
    exec($command);
    file_put_contents( '..'.$ds.'app'.$ds.'config'.$ds.'is_installed.php' , '<?php  return true;');
?>
    