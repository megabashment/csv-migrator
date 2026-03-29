<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /login.html');
    exit;
}

// Config
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_PREVIEW_ROWS', 100);

// Create upload dir if needed
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

$error   = '';
$success = '';
$preview = null;

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $result = handleUpload($_FILES['csv_file']);
    if ($result['success']) {
        $preview = $result['preview'];
        $success = 'Datei erfolgreich eingelesen.';
    } else {
        $error = $result['error'];
    }
}

function handleUpload(array $file): array {
    // Basic checks
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload-Fehler: Code ' . $file['error']];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Datei zu groß (max. 5 MB).'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        return ['success' => false, 'error' => 'Nur CSV/TXT-Dateien erlaubt.'];
    }

    // Save temporarily
    $tmpName = UPLOAD_DIR . uniqid('csv_', true) . '.csv';
    if (!move_uploaded_file($file['tmp_name'], $tmpName)) {
        return ['success' => false, 'error' => 'Datei konnte nicht gespeichert werden.'];
    }

    // Parse
    $preview = parseCSV($tmpName, $file['name']);

    // Clean up immediately – we don't store user data
    unlink($tmpName);

    return ['success' => true, 'preview' => $preview];
}

function parseCSV(string $path, string $originalName): array {
    // Detect delimiter
    $sample = file_get_contents($path, false, null, 0, 4096);
    $delimiter = detectDelimiter($sample);

    // Detect encoding & convert to UTF-8
    $encoding = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = file_get_contents($path);
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        file_put_contents($path, $content);
    }

    $rows    = [];
    $headers = [];
    $rowCount = 0;

    if (($fh = fopen($path, 'r')) !== false) {
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if ($rowCount === 0) {
                $headers = array_map('trim', $row);
            } else {
                if ($rowCount <= MAX_PREVIEW_ROWS) {
                    $rows[] = $row;
                }
            }
            $rowCount++;
        }
        fclose($fh);
    }

    $totalRows = $rowCount - 1; // minus header

    // Detect column types
    $colTypes = detectColumnTypes($rows, count($headers));

    // Basic stats per column
    $colStats = buildColStats($rows, $headers, $colTypes);

    return [
        'filename'   => htmlspecialchars($originalName),
        'delimiter'  => $delimiter,
        'encoding'   => $encoding ?: 'UTF-8',
        'headers'    => $headers,
        'rows'       => $rows,
        'total_rows' => $totalRows,
        'col_types'  => $colTypes,
        'col_stats'  => $colStats,
        'truncated'  => $totalRows > MAX_PREVIEW_ROWS,
    ];
}

function detectDelimiter(string $sample): string {
    $delimiters = [',', ';', "\t", '|'];
    $counts     = [];
    foreach ($delimiters as $d) {
        $counts[$d] = substr_count(strtok($sample, "\n"), $d);
    }
    arsort($counts);
    return array_key_first($counts) ?: ',';
}

function detectColumnTypes(array $rows, int $colCount): array {
    $types = array_fill(0, $colCount, 'text');
    $samples = min(count($rows), 20);

    for ($col = 0; $col < $colCount; $col++) {
        $intCount    = 0;
        $floatCount  = 0;
        $dateCount   = 0;
        $emptyCount  = 0;
        $total       = 0;

        for ($r = 0; $r < $samples; $r++) {
            $val = trim($rows[$r][$col] ?? '');
            if ($val === '') { $emptyCount++; continue; }
            $total++;

            if (preg_match('/^\d+$/', $val)) $intCount++;
            elseif (preg_match('/^\d+[.,]\d+$/', $val)) $floatCount++;
            elseif (preg_match('/^\d{1,4}[-\.\/]\d{1,2}[-\.\/]\d{1,4}$/', $val)) $dateCount++;
        }

        if ($total === 0) continue;
        if ($dateCount / $total > 0.6)              $types[$col] = 'date';
        elseif (($intCount + $floatCount) / $total > 0.8) {
            $types[$col] = $floatCount > 0 ? 'decimal' : 'integer';
        }
    }
    return $types;
}

function buildColStats(array $rows, array $headers, array $types): array {
    $stats = [];
    $colCount = count($headers);

    for ($col = 0; $col < $colCount; $col++) {
        $values  = array_filter(array_column($rows, $col), fn($v) => trim($v) !== '');
        $empty   = count($rows) - count($values);
        $unique  = count(array_unique($values));
        $numeric = array_filter($values, fn($v) => is_numeric(str_replace(',', '.', $v)));

        $stat = [
            'filled'  => count($values),
            'empty'   => $empty,
            'unique'  => $unique,
        ];

        if (count($numeric) > 0) {
            $nums = array_map(fn($v) => (float) str_replace(',', '.', $v), $numeric);
            $stat['min'] = round(min($nums), 2);
            $stat['max'] = round(max($nums), 2);
        }

        $stats[] = $stat;
    }
    return $stats;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CSV Migrator — Portfolio Demo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --bg: #0e0e10;
    --surface: #18181c;
    --surface2: #222228;
    --border: #2e2e38;
    --accent: #6aff8e;
    --accent2: #5b6bff;
    --warn: #ffb347;
    --text: #e8e8f0;
    --muted: #7a7a90;
    --radius: 8px;
    --font-head: 'Syne', sans-serif;
    --font-mono: 'Space Mono', monospace;
}

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--font-head);
    min-height: 100vh;
    padding: 2rem 1rem 4rem;
}

/* HEADER */
.header {
    max-width: 900px;
    margin: 0 auto 2.5rem;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
}
.logo-mark {
    width: 36px; height: 36px;
    background: var(--accent);
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
}
.logo-mark svg { width: 20px; height: 20px; }
.logo-text { font-size: 18px; font-weight: 700; letter-spacing: -0.5px; }
.logo-text span { color: var(--accent); }
.tagline { font-size: 12px; color: var(--muted); font-family: var(--font-mono); }

/* MAIN CONTAINER */
.container { max-width: 900px; margin: 0 auto; }

/* UPLOAD ZONE */
.upload-card {
    background: var(--surface);
    border: 1.5px dashed var(--border);
    border-radius: 12px;
    padding: 2.5rem;
    text-align: center;
    transition: border-color 0.2s;
    cursor: pointer;
    position: relative;
}
.upload-card.dragover { border-color: var(--accent); background: #1a2a1e; }
.upload-icon { margin-bottom: 1rem; }
.upload-icon svg { width: 48px; height: 48px; stroke: var(--muted); }
.upload-title { font-size: 20px; font-weight: 700; margin-bottom: 0.5rem; }
.upload-sub { font-size: 13px; color: var(--muted); font-family: var(--font-mono); margin-bottom: 1.5rem; }
.upload-sub span { color: var(--accent); }

.btn-upload {
    background: var(--accent);
    color: #0e0e10;
    border: none;
    padding: 10px 24px;
    border-radius: var(--radius);
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: 0.5px;
    transition: opacity 0.15s, transform 0.1s;
}
.btn-upload:hover { opacity: 0.85; transform: translateY(-1px); }
.btn-upload:active { transform: scale(0.98); }
.file-input { display: none; }

/* ALERTS */
.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    font-size: 13px;
    font-family: var(--font-mono);
    margin-bottom: 1.5rem;
}
.alert-error { background: #2a1414; border: 1px solid #5a2020; color: #ff7070; }
.alert-success { background: #142a1a; border: 1px solid #206030; color: var(--accent); }

/* PREVIEW SECTION */
.preview-wrap { margin-top: 2rem; animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.section-label {
    font-size: 11px;
    font-family: var(--font-mono);
    color: var(--muted);
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 0.75rem;
}

/* FILE INFO BAR */
.file-info {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 1rem 1.25rem;
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}
.info-item { display: flex; flex-direction: column; gap: 2px; }
.info-key { font-size: 10px; font-family: var(--font-mono); color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
.info-val { font-size: 14px; font-weight: 600; }
.info-val.accent { color: var(--accent); }

/* COLUMN CARDS */
.col-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 1.5rem;
}
.col-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0.9rem 1rem;
    transition: border-color 0.15s;
}
.col-card:hover { border-color: var(--accent2); }
.col-name {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.col-type {
    display: inline-block;
    font-size: 10px;
    font-family: var(--font-mono);
    padding: 2px 7px;
    border-radius: 4px;
    margin-bottom: 8px;
}
.type-text    { background: #1e1e35; color: var(--accent2); }
.type-integer { background: #1a2a1a; color: var(--accent); }
.type-decimal { background: #1a2a1a; color: #8fffb0; }
.type-date    { background: #2a2014; color: var(--warn); }
.col-stat { font-size: 11px; color: var(--muted); font-family: var(--font-mono); }
.fill-bar { height: 3px; background: var(--border); border-radius: 99px; margin-top: 6px; overflow: hidden; }
.fill-bar-inner { height: 100%; border-radius: 99px; background: var(--accent); transition: width 0.4s ease; }

/* TABLE */
.table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: auto;
    max-height: 420px;
}
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead { position: sticky; top: 0; z-index: 2; }
thead tr { background: var(--surface2); }
th {
    padding: 10px 14px;
    text-align: left;
    font-family: var(--font-mono);
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
    letter-spacing: 0.5px;
    white-space: nowrap;
    border-bottom: 1px solid var(--border);
}
th .th-type { font-size: 9px; color: var(--accent2); display: block; font-weight: 400; }
td {
    padding: 8px 14px;
    border-bottom: 1px solid var(--border);
    font-family: var(--font-mono);
    color: var(--text);
    white-space: nowrap;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}
tbody tr:hover td { background: var(--surface2); }
tbody tr:last-child td { border-bottom: none; }
td.empty { color: var(--muted); font-style: italic; font-size: 11px; }
.row-num td:first-child { color: var(--muted); font-size: 11px; }

/* TRUNCATED NOTE */
.truncated-note {
    text-align: center;
    padding: 12px;
    font-family: var(--font-mono);
    font-size: 11px;
    color: var(--warn);
    background: var(--surface2);
    border-top: 1px solid var(--border);
}

/* RESET LINK */
.reset-row {
    text-align: center;
    margin-top: 2rem;
}
.btn-reset {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 8px 20px;
    border-radius: var(--radius);
    font-family: var(--font-mono);
    font-size: 12px;
    cursor: pointer;
    transition: border-color 0.15s, color 0.15s;
}
.btn-reset:hover { border-color: var(--accent); color: var(--accent); }

/* FOOTER */
.footer {
    max-width: 900px;
    margin: 3rem auto 0;
    text-align: center;
    font-size: 11px;
    font-family: var(--font-mono);
    color: var(--muted);
}
.footer a { color: var(--accent2); text-decoration: none; }

/* RESPONSIVE */
@media (max-width: 600px) {
    .upload-card { padding: 1.5rem 1rem; }
    .file-info { gap: 1rem; }
    .col-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
}
</style>
</head>
<body>

<header class="header">
  <div class="logo">
    <div class="logo-mark">
      <svg viewBox="0 0 20 20" fill="none" stroke="#0e0e10" stroke-width="2" stroke-linecap="round">
        <path d="M3 6h14M3 10h14M3 14h8"/>
        <circle cx="16" cy="14" r="3"/>
        <path d="M14.5 14H17"/>
      </svg>
    </div>
    <div>
      <div class="logo-text">CSV<span>Migrator</span></div>
      <div class="tagline">// portfolio demo v1.0</div>
    </div>
  </div>
</header>

<main class="container">

  <?php if ($error): ?>
  <div class="alert alert-error">⚠ <?= $error ?></div>
  <?php endif; ?>

  <?php if (!$preview): ?>
  <!-- UPLOAD FORM -->
  <form method="POST" enctype="multipart/form-data" id="upload-form">
    <div class="upload-card" id="drop-zone">
      <div class="upload-icon">
        <svg viewBox="0 0 48 48" fill="none" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="6" y="8" width="36" height="32" rx="4"/>
          <path d="M16 20h16M16 26h10"/>
          <path d="M30 32l4-4 4 4M34 28v8"/>
        </svg>
      </div>
      <div class="upload-title">CSV-Datei hochladen</div>
      <div class="upload-sub">Drag &amp; Drop oder klicken — <span>CSV, TXT</span> bis 5 MB</div>
      <button type="button" class="btn-upload" onclick="document.getElementById('csv-input').click()">
        Datei auswählen
      </button>
      <input type="file" name="csv_file" id="csv-input" class="file-input" accept=".csv,.txt" onchange="this.form.submit()">
    </div>
  </form>

  <?php else: ?>
  <!-- PREVIEW -->
  <?php $p = $preview; ?>

  <?php if ($success): ?>
  <div class="alert alert-success">✓ <?= $success ?></div>
  <?php endif; ?>

  <div class="preview-wrap">
    <!-- FILE INFO -->
    <div class="section-label">Datei-Info</div>
    <div class="file-info">
      <div class="info-item">
        <span class="info-key">Dateiname</span>
        <span class="info-val"><?= $p['filename'] ?></span>
      </div>
      <div class="info-item">
        <span class="info-key">Zeilen</span>
        <span class="info-val accent"><?= number_format($p['total_rows']) ?></span>
      </div>
      <div class="info-item">
        <span class="info-key">Spalten</span>
        <span class="info-val accent"><?= count($p['headers']) ?></span>
      </div>
      <div class="info-item">
        <span class="info-key">Trennzeichen</span>
        <span class="info-val"><?= $p['delimiter'] === "\t" ? 'TAB' : htmlspecialchars($p['delimiter']) ?></span>
      </div>
      <div class="info-item">
        <span class="info-key">Encoding</span>
        <span class="info-val"><?= htmlspecialchars($p['encoding']) ?></span>
      </div>
    </div>

    <!-- COLUMN ANALYSIS -->
    <div class="section-label">Spalten-Analyse</div>
    <div class="col-grid">
      <?php foreach ($p['headers'] as $i => $h):
        $type  = $p['col_types'][$i] ?? 'text';
        $stat  = $p['col_stats'][$i] ?? [];
        $total = ($stat['filled'] ?? 0) + ($stat['empty'] ?? 0);
        $pct   = $total > 0 ? round(($stat['filled'] ?? 0) / $total * 100) : 0;
      ?>
      <div class="col-card">
        <div class="col-name" title="<?= htmlspecialchars($h) ?>"><?= htmlspecialchars($h) ?></div>
        <span class="col-type type-<?= $type ?>"><?= $type ?></span>
        <div class="col-stat"><?= $stat['unique'] ?? '?' ?> unique · <?= $pct ?>% filled</div>
        <?php if (isset($stat['min'])): ?>
        <div class="col-stat"><?= $stat['min'] ?> – <?= $stat['max'] ?></div>
        <?php endif; ?>
        <div class="fill-bar"><div class="fill-bar-inner" style="width:<?= $pct ?>%"></div></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- DATA TABLE -->
    <div class="section-label">Daten-Vorschau
      (<?= min(count($p['rows']), MAX_PREVIEW_ROWS) ?> von <?= $p['total_rows'] ?> Zeilen)
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <?php foreach ($p['headers'] as $i => $h): ?>
            <th><?= htmlspecialchars($h) ?><span class="th-type"><?= $p['col_types'][$i] ?></span></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($p['rows'] as $rowIdx => $row): ?>
          <tr>
            <td style="color:var(--muted);font-size:11px"><?= $rowIdx + 1 ?></td>
            <?php foreach ($p['headers'] as $i => $h):
              $val = $row[$i] ?? '';
            ?>
            <td class="<?= trim($val) === '' ? 'empty' : '' ?>"><?= trim($val) === '' ? 'NULL' : htmlspecialchars($val) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($p['truncated']): ?>
      <div class="truncated-note">
        ⚠ Vorschau zeigt <?= MAX_PREVIEW_ROWS ?> von <?= $p['total_rows'] ?> Zeilen
      </div>
      <?php endif; ?>
    </div>

    <div class="reset-row">
      <a href="index.php"><button class="btn-reset">↑ Neue Datei hochladen</button></a>
    </div>
  </div>
  <?php endif; ?>

</main>

<footer class="footer">
  CSV Migrator · Portfolio Demo · <a href="https://github.com/megabashment/csv-migrator" target="_blank">GitHub</a>
  · Keine Daten werden gespeichert
</footer>

<script>
// Drag & Drop
const zone = document.getElementById('drop-zone');
const input = document.getElementById('csv-input');

if (zone && input) {
  zone.addEventListener('dragover', e => {
    e.preventDefault();
    zone.classList.add('dragover');
  });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) {
      const dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      input.form.submit();
    }
  });
}
</script>
</body>
</html>
