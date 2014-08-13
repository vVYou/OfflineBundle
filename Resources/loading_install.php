<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation</title>
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap-theme.min.css">
    <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
</head>
<body style="padding-top:40px; background-color:#F7F7F9">
<div class="container">
    <div class="row">
        <div class="col-md-offset-4 col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Offline Install</h3>
                </div>
                <div class="panel-body text-center">
                    <p>Installation de la plateforme</p>
                    <?php
                        $ds = DIRECTORY_SEPARATOR;
                        echo '<img src="bundles'.$ds.'clarolineoffline'.$ds.'images'.$ds.'loading.gif" width="200" height="200" > ';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $.ajax("<?php 
        echo($_SERVER['SCRIPT_NAME'].'/../offline_install.php');?>")
    .done(function () {
        window.location.href = "<?php 
            echo($_SERVER['SCRIPT_NAME'].'/../app_offline.php');?>";
    })
    .error(function (){
        alert('error');
    })
    
</script>
</body>
</html>
<?php
    // $url = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '/../offline_install.php';
    // header("Location: http://{$url}");
?>
    