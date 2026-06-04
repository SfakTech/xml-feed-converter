<?php
session_start();
require 'src/mapping.php';
require 'src/functions.php';

// Handle file download
if (isset($_GET['download'])) {
    $token = $_GET['download'];
    $file  = $_SESSION['downloads'][$token] ?? null;
    $name  = $_SESSION['download_names'][$token] ?? 'converted.xml';
    if ($file && file_exists($file)) {
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        unlink($file);
        unset($_SESSION['downloads'][$token], $_SESSION['download_names'][$token]);
        exit;
    }
    header('Location: index.php');
    exit;
}

$activeTab = 'convert';
$result    = null;
$error     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $activeTab = in_array($action, ['analyze', 'save_mapping']) ? 'analyze' : 'convert';

    if ($action === 'save_mapping') {
        $updates = array_filter($_POST['field_mapping'] ?? []);
        $saved   = 0;
        foreach ($updates as $field => $standardField) {
            if (isset($mapping[$standardField]) && !in_array($field, $mapping[$standardField])) {
                $mapping[$standardField][] = $field;
                $saved++;
            }
        }
        if ($saved > 0) {
            if (saveMappingFile($mapping)) {
                $result = ['type' => 'save_mapping', 'count' => $saved];
            } else {
                $error = 'Could not write to mapping.php. Check file permissions.';
            }
        }
    } elseif (!isset($_FILES['xmlfile']) || $_FILES['xmlfile']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid XML file.';
    } elseif (strtolower(pathinfo($_FILES['xmlfile']['name'], PATHINFO_EXTENSION)) !== 'xml') {
        $error = 'Only .xml files are supported.';
    } else {
        $tmpInput = $_FILES['xmlfile']['tmp_name'];
        $origName = pathinfo($_FILES['xmlfile']['name'], PATHINFO_FILENAME);

        try {
            if ($action === 'convert') {
                $outputPath = sys_get_temp_dir() . '/' . uniqid('xmlconv_') . '.xml';
                $count      = convertXml($tmpInput, $outputPath, $mapping);
                $token      = bin2hex(random_bytes(8));
                $_SESSION['downloads'][$token]      = $outputPath;
                $_SESSION['download_names'][$token] = $origName . '_converted.xml';
                $result = ['type' => 'convert', 'count' => $count, 'token' => $token, 'filename' => $origName . '_converted.xml'];
            } elseif ($action === 'analyze') {
                $analysis = analyzeXml($tmpInput, $mapping);
                $result   = ['type' => 'analyze', 'data' => $analysis, 'filename' => $_FILES['xmlfile']['name']];
            }
        } catch (Exception $e) {
            $error = 'Error processing file: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XML Feed Converter</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="header">
    <h1>XML Feed Converter</h1>
    <p>Normalize product XML feeds from any supplier into a unified format</p>
</div>

<div class="container">

    <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn <?= $activeTab === 'convert' ? 'active' : '' ?>" onclick="switchTab('convert', this)">Convert</button>
        <button class="tab-btn <?= $activeTab === 'analyze' ? 'active' : '' ?>" onclick="switchTab('analyze', this)">Analyze Mapping</button>
    </div>

    <div class="card">

        <!-- Convert Tab -->
        <div class="tab-pane <?= $activeTab === 'convert' ? 'active' : '' ?>" id="tab-convert">
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-zone">
                    <div class="upload-icon">📄</div>
                    <p>Upload a supplier XML feed to convert it to the standard format</p>
                    <input type="file" name="xmlfile" accept=".xml" class="file-input" required>
                    <button type="submit" name="action" value="convert" class="btn btn-primary">Convert</button>
                </div>
            </form>

            <?php if ($result && $result['type'] === 'convert'): ?>
            <div class="result-box">
                <div class="alert alert-success">Conversion complete!</div>
                <div class="stats">
                    <div class="stat green">
                        <div class="num"><?= number_format($result['count']) ?></div>
                        <div class="label">Products converted</div>
                    </div>
                </div>
                <a href="?download=<?= htmlspecialchars($result['token']) ?>" class="btn btn-success">
                    &#8595; Download <?= htmlspecialchars($result['filename']) ?>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Analyze Tab -->
        <div class="tab-pane <?= $activeTab === 'analyze' ? 'active' : '' ?>" id="tab-analyze">
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-zone">
                    <div class="upload-icon">🔍</div>
                    <p>Upload a supplier XML feed to see which fields map to the standard format and which don't</p>
                    <input type="file" name="xmlfile" accept=".xml" class="file-input" required>
                    <button type="submit" name="action" value="analyze" class="btn btn-primary">Analyze</button>
                </div>
            </form>

            <?php if ($result && $result['type'] === 'save_mapping'): ?>
            <div class="alert alert-success">
                <?= $result['count'] ?> field<?= $result['count'] > 1 ? 's' : '' ?> added to mapping.php successfully!
            </div>
            <?php endif; ?>

            <?php if ($result && $result['type'] === 'analyze'):
                $data           = $result['data'];
                $matchedCount   = count($data['matched']);
                $unmatchedCount = count($data['unmatched']);
            ?>
            <div class="result-box">
                <div class="stats">
                    <div class="stat">
                        <div class="num"><?= $data['total'] ?></div>
                        <div class="label">Fields found</div>
                    </div>
                    <div class="stat green">
                        <div class="num"><?= $matchedCount ?></div>
                        <div class="label">Matched</div>
                    </div>
                    <div class="stat amber">
                        <div class="num"><?= $unmatchedCount ?></div>
                        <div class="label">Unmatched</div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_mapping">
                    <table>
                        <thead>
                            <tr>
                                <th>XML Field</th>
                                <th>Status</th>
                                <th>Maps to / Add to</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['matched'] as $field => $standard): ?>
                            <tr>
                                <td><span class="mono"><?= htmlspecialchars($field) ?></span></td>
                                <td><span class="badge badge-green">Matched</span></td>
                                <td><span class="badge badge-blue"><?= htmlspecialchars($standard) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php foreach ($data['unmatched'] as $field => $info): ?>
                            <tr>
                                <td><span class="mono"><?= htmlspecialchars($field) ?></span></td>
                                <td><span class="badge badge-amber">Unmatched</span></td>
                                <td>
                                    <select name="field_mapping[<?= htmlspecialchars($field) ?>]" class="field-select">
                                        <option value="">— skip —</option>
                                        <?php foreach (array_keys($mapping) as $stdField): ?>
                                        <option value="<?= $stdField ?>" <?= $info['suggestion'] === $stdField ? 'selected' : '' ?>>
                                            <?= $stdField ?><?= $info['suggestion'] === $stdField ? ' (' . $info['confidence'] . '%)' : '' ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($unmatchedCount > 0): ?>
                    <button type="submit" class="btn btn-primary" style="margin-top:20px;">
                        Save to mapping.php
                    </button>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
