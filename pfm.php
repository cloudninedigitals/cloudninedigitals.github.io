<?php
session_start();

/*
 Simple PHP File Manager (LiteSpeed-friendly)
 Features:
  - Navigate directories (limited to BASE_DIR)
  - Edit file
  - Delete file
  - Rename file
  - Create new file / folder
  - Basic login + CSRF protection

 Usage:
  - Set $PASSWORD (plain text)
  - Set $BASE_DIR to restrict accessible area (recommended)
  - Put this file on server, open in browser, login.
*/

// ---------- Configuration ----------
$PASSWORD = 'ganti_password_kuat'; // Ganti segera
$BASE_DIR = realpath(__DIR__);    // Batasi akses ke direktori ini (direkomendasikan)
$MAX_UPLOAD_MB = 10;
// -----------------------------------

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$token);
}

function join_path($base, $path) {
    // Prevent directory traversal and ensure stays within base
    $target = realpath($base . DIRECTORY_SEPARATOR . $path);
    if ($target === false) return false;
    // Normalize base also
    $base_real = realpath($base);
    if ($base_real === false) return false;
    if (strpos($target, $base_real) !== 0) return false;
    return $target;
}

function relpath($base, $path) {
    $b = realpath($base);
    $p = realpath($path);
    if ($b === false || $p === false) return null;
    if (strpos($p, $b) !== 0) return null;
    $rel = substr($p, strlen($b));
    if ($rel === false || $rel === '') return '.';
    return ltrim(str_replace('\\','/',$rel), '/');
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---------- Authentication ----------
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pw = $_POST['password'] ?? '';
    if (hash_equals($pw, $GLOBALS['PASSWORD'])) {
        $_SESSION['logged'] = true;
        // regenerate session id
        session_regenerate_id(true);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $login_error = "Password salah.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (empty($_SESSION['logged'])) {
    // show login form
    ?>
    <!doctype html>
    <html>
    <head>
      <meta charset="utf-8">
      <title>FileManager - Login</title>
      <style>
        body{font-family:system-ui,Segoe UI,Roboto;display:flex;min-height:100vh;align-items:center;justify-content:center;background:#0f1720;color:#e6eef8}
        .card{background:#0b1220;padding:28px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.6);width:360px}
        input{width:100%;padding:10px;margin-top:8px;border-radius:6px;border:1px solid #233045;background:#07101a;color:#e6eef8}
        button{margin-top:12px;padding:10px;width:100%;border-radius:6px;border:0;background:#2563eb;color:white;font-weight:600}
        small{color:#9fb0d0}
      </style>
    </head>
    <body>
      <div class="card">
        <h2>File Manager</h2>
        <form method="post">
          <input type="hidden" name="action" value="login">
          <label>Password</label>
          <input type="password" name="password" required autofocus>
          <?php if(!empty($login_error)): ?><div style="color:#ffb4b4;margin-top:8px;"><?=h($login_error)?></div><?php endif; ?>
          <button type="submit">Login</button>
        </form>
        <p><small>Base dir: <?=h($BASE_DIR)?></small></p>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// ---------- After login: main logic ----------
$current_rel = $_GET['path'] ?? '.';
$cwd = join_path($BASE_DIR, $current_rel);
if ($cwd === false || !is_dir($cwd)) {
    $cwd = $BASE_DIR;
    $current_rel = '.';
}

// handle actions: save edit, delete, rename, create, upload
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $token = $_POST['csrf'] ?? '';
    if (!verify_csrf($token)) {
        $errors[] = "CSRF token invalid.";
    } else {
        if ($act === 'save') {
            $file_rel = $_POST['file'] ?? '';
            $file_path = join_path($BASE_DIR, $file_rel);
            $content = $_POST['content'] ?? '';
            if ($file_path === false || !is_file($file_path)) {
                $errors[] = "File tidak ditemukan atau akses ditolak.";
            } elseif (!is_writable($file_path)) {
                $errors[] = "File tidak dapat ditulis (permission denied).";
            } else {
                if (file_put_contents($file_path, $content) !== false) {
                    $messages[] = "File disimpan.";
                } else {
                    $errors[] = "Gagal menyimpan file.";
                }
            }
        } elseif ($act === 'delete') {
            $target_rel = $_POST['target'] ?? '';
            $target = join_path($BASE_DIR, $target_rel);
            if ($target === false) {
                $errors[] = "Akses ditolak.";
            } else {
                if (is_dir($target)) {
                    // try rmdir (only empty)
                    if (@rmdir($target)) $messages[] = "Folder dihapus.";
                    else $errors[] = "Gagal menghapus folder (mungkin tidak kosong).";
                } else {
                    if (@unlink($target)) $messages[] = "File dihapus.";
                    else $errors[] = "Gagal menghapus file.";
                }
            }
        } elseif ($act === 'rename') {
            $src_rel = $_POST['src'] ?? '';
            $dst_name = $_POST['dst_name'] ?? '';
            if ($dst_name === '') { $errors[] = "Nama tujuan kosong."; }
            else {
                $src = join_path($BASE_DIR, $src_rel);
                $dst_parent = dirname($src);
                $dst = realpath($dst_parent) . DIRECTORY_SEPARATOR . basename($dst_name);
                // ensure dst within base
                if (strpos(realpath($dst_parent), realpath($BASE_DIR)) !== 0) $errors[] = "Akses ditolak.";
                else {
                    if (@rename($src, $dst)) $messages[] = "Rename berhasil.";
                    else $errors[] = "Rename gagal.";
                }
            }
        } elseif ($act === 'newfile') {
            $name = $_POST['name'] ?? '';
            if ($name === '') $errors[] = "Nama file kosong.";
            else {
                $target = $cwd . DIRECTORY_SEPARATOR . basename($name);
                if (file_exists($target)) $errors[] = "File/folder sudah ada.";
                else {
                    if (@file_put_contents($target, "") !== false) $messages[] = "File dibuat.";
                    else $errors[] = "Gagal membuat file.";
                }
            }
        } elseif ($act === 'newfolder') {
            $name = $_POST['name'] ?? '';
            if ($name === '') $errors[] = "Nama folder kosong.";
            else {
                $target = $cwd . DIRECTORY_SEPARATOR . basename($name);
                if (file_exists($target)) $errors[] = "File/folder sudah ada.";
                else {
                    if (@mkdir($target, 0755, true)) $messages[] = "Folder dibuat.";
                    else $errors[] = "Gagal membuat folder.";
                }
            }
        } elseif ($act === 'upload') {
            if (!isset($_FILES['upload'])) $errors[] = "Tidak ada file diunggah.";
            else {
                $up = $_FILES['upload'];
                if ($up['error'] !== UPLOAD_ERR_OK) $errors[] = "Upload error code: " . $up['error'];
                else {
                    $sizeMB = $up['size'] / (1024*1024);
                    if ($sizeMB > $MAX_UPLOAD_MB) $errors[] = "Ukuran file melebihi batas {$MAX_UPLOAD_MB}MB.";
                    else {
                        $dest = $cwd . DIRECTORY_SEPARATOR . basename($up['name']);
                        if (@move_uploaded_file($up['tmp_name'], $dest)) $messages[] = "Upload berhasil.";
                        else $errors[] = "Gagal memindahkan file.";
                    }
                }
            }
        }
    }
    // refresh path after action
    $current_rel = $_GET['path'] ?? $current_rel;
    $cwd = join_path($BASE_DIR, $current_rel) ?: $BASE_DIR;
}

// ---------- UI ----------
$items = scandir($cwd);
$parent_rel = relpath($BASE_DIR, dirname($cwd));
if ($parent_rel === null) $parent_rel = '.';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>PHP File Manager</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{--bg:#0b1220;--card:#071127;--muted:#9fb0d0;--accent:#2563eb;--danger:#ff6b6b;color-scheme:dark}
    body{font-family:Inter,Segoe UI,Roboto,system-ui;margin:0;background:var(--bg);color:#e6eef8}
    header{padding:14px 20px;background:linear-gradient(90deg,#071027,#08142a);display:flex;align-items:center;justify-content:space-between}
    .wrap{max-width:1100px;margin:20px auto;padding:18px;background:var(--card);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.6)}
    a {color:var(--accent);text-decoration:none}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.03);text-align:left}
    .small{font-size:13px;color:var(--muted)}
    .actions button{margin-right:6px}
    .btn{padding:8px 10px;border-radius:8px;border:0;background:rgba(255,255,255,.04);color:#e6eef8;cursor:pointer}
    .btn.danger{background:var(--danger)}
    .panel{padding:12px;border-radius:10px;background:linear-gradient(180deg, rgba(255,255,255,0.02), transparent);margin-bottom:12px}
    input[type=text], textarea {width:100%;padding:8px;border-radius:8px;background:#03111a;border:1px solid rgba(255,255,255,.04);color:#e6eef8}
    textarea{min-height:300px;font-family:monospace}
    form.inline{display:inline}
    .msg{padding:8px;border-radius:8px;margin-bottom:8px}
    .msg.ok{background:rgba(34,197,94,0.12);color:#b7f5c9}
    .msg.err{background:rgba(255,99,99,0.12);color:#ffd1d1}
    .topbar {display:flex;gap:8px;align-items:center}
    .file-meta{font-size:13px;color:var(--muted)}
    .leftcol{width:55%}
    .rightcol{width:40%;margin-left:5%}
    @media(max-width:900px){.leftcol,.rightcol{width:100%;margin-left:0}}
  </style>
  <script>
    function confirmDelete(name) {
      if (!confirm("Hapus "+name+" ? Tindakan ini tidak dapat dibatalkan.")) return;
      document.getElementById('del-target').value = name;
      document.getElementById('form-delete').submit();
    }
    function openEdit(path) {
      // navigate to edit view via query
      window.location = '?path=' + encodeURIComponent(path) + '&edit=' + encodeURIComponent(path);
    }
    function openRename(path) {
      let name = prompt("Nama baru untuk: " + path, path.split('/').pop());
      if (name) {
        let f = document.createElement('form'); f.method='post';
        f.innerHTML = '<input type="hidden" name="csrf" value="<?=h(csrf_token())?>">'
                    + '<input type="hidden" name="act" value="rename">'
                    + '<input type="hidden" name="src" value="'+path+'">'
                    + '<input type="hidden" name="dst_name" value="'+name+'">';
        document.body.appendChild(f); f.submit();
      }
    }
  </script>
</head>
<body>
  <header>
    <div>
      <strong>PHP File Manager</strong>
      <span class="small"> — Base: <?=h($BASE_DIR)?></span>
    </div>
    <div class="topbar">
      <a class="small" href="?path=<?=urlencode($current_rel)?>">Refresh</a>
      <a class="small" href="?logout=1">Logout</a>
    </div>
  </header>

  <div class="wrap">
    <?php foreach($messages as $m): ?><div class="msg ok"><?=h($m)?></div><?php endforeach; ?>
    <?php foreach($errors as $e): ?><div class="msg err"><?=h($e)?></div><?php endforeach; ?>

    <div style="display:flex;flex-wrap:wrap;gap:18px">
      <div class="panel leftcol">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <strong>Directory:</strong> <?=h(relpath($BASE_DIR, $cwd))?>
            <div class="file-meta">Path: <?=h($cwd)?></div>
          </div>
          <div>
            <form method="get" style="display:inline">
              <input type="hidden" name="path" value="<?=h($current_rel)?>">
              <button class="btn" type="submit">Refresh</button>
            </form>
          </div>
        </div>

        <table>
          <thead>
            <tr><th>Name</th><th>Type</th><th class="small">Size / Date</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php
              // parent link
              if (realpath($cwd) !== realpath($BASE_DIR)) {
                  $up = dirname($cwd);
                  $up_rel = relpath($BASE_DIR, $up) ?: '.';
                  echo '<tr><td><a href="?path='.urlencode($up_rel).'">.. (Parent)</a></td><td>dir</td><td></td><td></td></tr>';
              }
              foreach($items as $it) {
                if ($it === '.' || $it === '..') continue;
                $full = $cwd . DIRECTORY_SEPARATOR . $it;
                $rel = relpath($BASE_DIR, $full);
                if ($rel === null) continue;
                $isdir = is_dir($full);
                $type = $isdir ? 'dir' : pathinfo($it, PATHINFO_EXTENSION) ?: 'file';
                $size = $isdir ? '-' : @filesize($full);
                $sizeText = $isdir ? '-' : number_format($size).' bytes';
                $date = date('Y-m-d H:i', filemtime($full));
                echo '<tr>';
                echo '<td>';
                if ($isdir) {
                    echo '<a href="?path='.urlencode($rel).'">'.h($it).'</a>';
                } else {
                    echo '<a href="?path='.urlencode($current_rel).'&view='.urlencode($rel).'">'.h($it).'</a>';
                }
                echo '</td>';
                echo '<td class="small">'.h($type).'</td>';
                echo '<td class="small">'.h($sizeText).' • '.h($date).'</td>';
                echo '<td class="actions">';
                if (!$isdir) {
                    echo '<button class="btn" onclick="openEdit(\''.h($rel).'\')">Edit</button>';
                    echo '<a class="btn" href="?download='.urlencode($rel).'">Download</a>';
                }
                echo '<button class="btn" onclick="openRename(\''.h($rel).'\')">Rename</button>';
                echo '<button class="btn danger" onclick="confirmDelete(\''.h($rel).'\')">Delete</button>';
                echo '</td>';
                echo '</tr>';
              }
            ?>
          </tbody>
        </table>

        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="act" value="newfile">
            <input type="text" name="name" placeholder="newfile.txt">
            <button class="btn" type="submit">New File</button>
          </form>

          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="act" value="newfolder">
            <input type="text" name="name" placeholder="new-folder">
            <button class="btn" type="submit">New Folder</button>
          </form>

          <form method="post" enctype="multipart/form-data" class="inline">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="act" value="upload">
            <input type="file" name="upload" style="display:inline">
            <button class="btn" type="submit">Upload</button>
          </form>
        </div>

      </div>

      <div class="panel rightcol">
        <?php
          // Download handler
          if (!empty($_GET['download'])) {
              $dl = join_path($BASE_DIR, $_GET['download']);
              if ($dl && is_file($dl)) {
                  header('Content-Description: File Transfer');
                  header('Content-Type: application/octet-stream');
                  header('Content-Disposition: attachment; filename="'.basename($dl).'"');
                  header('Content-Length: ' . filesize($dl));
                  readfile($dl);
                  exit;
              } else {
                  echo '<div class="msg err">File tidak ditemukan untuk diunduh.</div>';
              }
          }

          // edit view
          if (!empty($_GET['edit'])) {
              $file_rel = $_GET['edit'];
              $file_path = join_path($BASE_DIR, $file_rel);
              if ($file_path && is_file($file_path) && is_readable($file_path)) {
                  $content = file_get_contents($file_path);
                  echo '<h3>Edit: '.h($file_rel).'</h3>';
                  echo '<form method="post">';
                  echo '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';
                  echo '<input type="hidden" name="act" value="save">';
                  echo '<input type="hidden" name="file" value="'.h($file_rel).'">';
                  echo '<textarea name="content">'.h($content).'</textarea>';
                  echo '<div style="margin-top:8px"><button class="btn" type="submit">Save</button> <a class="btn" href="?path='.urlencode($current_rel).'">Back</a></div>';
                  echo '</form>';
              } else {
                  echo '<div class="msg err">File tidak bisa dibuka untuk edit.</div>';
              }
          } else if (!empty($_GET['view'])) {
              $file_rel = $_GET['view'];
              $file_path = join_path($BASE_DIR, $file_rel);
              if ($file_path && is_file($file_path) && is_readable($file_path)) {
                  $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                  echo '<h3>View: '.h($file_rel).'</h3>';
                  $txtTypes = ['txt','log','php','js','css','html','md','json','xml','env'];
                  if (in_array($ext, $txtTypes)) {
                      $content = file_get_contents($file_path);
                      echo '<pre style="white-space:pre-wrap;word-wrap:break-word;background:#03111a;padding:12px;border-radius:8px;">'.h($content).'</pre>';
                      echo '<div style="margin-top:8px"><button class="btn" onclick="openEdit(\''.h($file_rel).'\')">Edit</button></div>';
                  } else {
                      echo '<div class="small">Binary or preview not available. <a href="?download='.urlencode($file_rel).'">Download</a></div>';
                  }
              } else {
                  echo '<div class="msg err">File tidak ditemukan.</div>';
              }
          } else {
              // default info panel
              echo '<h3>Info</h3>';
              echo '<p class="small">Total items: '.count($items).'</p>';
              echo '<p class="small">Use the actions to Edit / Rename / Delete. Be careful.</p>';
          }
        ?>
      </div>
    </div>

    <!-- hidden forms -->
    <form id="form-delete" method="post" style="display:none">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="act" value="delete">
      <input id="del-target" type="hidden" name="target" value="">
    </form>

  </div>
</body>
</html>