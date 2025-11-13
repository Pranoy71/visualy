<?php
session_start();

$diffResult = null;
$stats = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'compare') {
    $textA = $_POST['a_text'] ?? '';
    $textB = $_POST['b_text'] ?? '';
    
    $textA = str_replace(["\r\n", "\r"], "\n", $textA);
    $textB = str_replace(["\r\n", "\r"], "\n", $textB);
    
    $diffResult = computeDiff($textA, $textB);
    $stats = $diffResult['stats'];
}

function computeDiff($a, $b) {
    $linesA = explode("\n", $a);
    $linesB = explode("\n", $b);
    
    $ops = diffLines($linesA, $linesB);
    
    $highlightedB = renderHighlightedText($ops);
    
    $adds = 0;
    $dels = 0;
    $mods = 0;
    
    foreach ($ops as $op) {
        if ($op['type'] === 'add') $adds++;
        if ($op['type'] === 'del') $dels++;
        if ($op['type'] === 'mod') $mods++;
    }
    
    $totalLines = max(count($linesA), count($linesB));
    $pct = $totalLines > 0 ? round((($adds + $dels) / $totalLines) * 100, 1) : 0;
    
    $wordsA = str_word_count($a);
    $wordsB = str_word_count($b);
    $wordDiff = abs($wordsA - $wordsB);
    
    return [
        'highlightedB' => $highlightedB,
        'stats' => [
            'adds' => $adds,
            'dels' => $dels,
            'mods' => $mods,
            'pct' => $pct,
            'linesA' => count($linesA),
            'linesB' => count($linesB),
            'wordsA' => $wordsA,
            'wordsB' => $wordsB,
            'wordDiff' => $wordDiff,
            'charsA' => strlen($a),
            'charsB' => strlen($b)
        ]
    ];
}

function diffLines($linesA, $linesB) {
    $n = count($linesA);
    $m = count($linesB);
    
    $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    
    for ($i = 1; $i <= $n; $i++) {
        for ($j = 1; $j <= $m; $j++) {
            if ($linesA[$i - 1] === $linesB[$j - 1]) {
                $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
            } else {
                $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
            }
        }
    }
    
    $ops = [];
    $i = $n;
    $j = $m;
    
    while ($i > 0 || $j > 0) {
        if ($i > 0 && $j > 0 && $linesA[$i - 1] === $linesB[$j - 1]) {
            array_unshift($ops, ['type' => 'keep', 'line' => $linesB[$j - 1]]);
            $i--;
            $j--;
        } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
            array_unshift($ops, ['type' => 'add', 'line' => $linesB[$j - 1]]);
            $j--;
        } elseif ($i > 0) {
            array_unshift($ops, ['type' => 'del', 'line' => $linesA[$i - 1]]);
            $i--;
        }
    }
    
    $refined = [];
    for ($k = 0; $k < count($ops); $k++) {
        if (isset($ops[$k + 1]) && 
            $ops[$k]['type'] === 'del' && 
            $ops[$k + 1]['type'] === 'add' &&
            levenshtein(substr($ops[$k]['line'], 0, 255), substr($ops[$k + 1]['line'], 0, 255)) < 100) {
            
            $refined[] = [
                'type' => 'mod',
                'old' => $ops[$k]['line'],
                'new' => $ops[$k + 1]['line']
            ];
            $k++;
        } else {
            $refined[] = $ops[$k];
        }
    }
    
    return $refined;
}

function renderHighlightedText($ops) {
    $html = '';
    $lineNum = 1;
    foreach ($ops as $op) {
        switch ($op['type']) {
            case 'keep':
                $html .= '<div class="line"><span class="line-num">' . $lineNum . '</span><span class="line-content">' . htmlspecialchars($op['line']) . '</span></div>';
                $lineNum++;
                break;
            case 'add':
                $html .= '<div class="line line-added"><span class="line-num">+' . $lineNum . '</span><span class="line-content"><span class="highlight-add">' . htmlspecialchars($op['line']) . '</span></span></div>';
                $lineNum++;
                break;
            case 'mod':
                $wordDiff = diffWords($op['old'], $op['new']);
                $html .= '<div class="line line-modified"><span class="line-num">~' . $lineNum . '</span><span class="line-content">' . $wordDiff . '</span></div>';
                $lineNum++;
                break;
        }
    }
    return $html;
}

function diffWords($old, $new) {
    $wordsOld = preg_split('/(\s+)/', $old, -1, PREG_SPLIT_DELIM_CAPTURE);
    $wordsNew = preg_split('/(\s+)/', $new, -1, PREG_SPLIT_DELIM_CAPTURE);
    
    $n = count($wordsOld);
    $m = count($wordsNew);
    $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    
    for ($i = 1; $i <= $n; $i++) {
        for ($j = 1; $j <= $m; $j++) {
            if ($wordsOld[$i - 1] === $wordsNew[$j - 1]) {
                $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
            } else {
                $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
            }
        }
    }
    
    $i = $n;
    $j = $m;
    $result = '';
    
    while ($i > 0 || $j > 0) {
        if ($i > 0 && $j > 0 && $wordsOld[$i - 1] === $wordsNew[$j - 1]) {
            $result = htmlspecialchars($wordsOld[$i - 1]) . $result;
            $i--;
            $j--;
        } elseif ($j > 0 && ($i === 0 || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
            $result = '<span class="word-added">' . htmlspecialchars($wordsNew[$j - 1]) . '</span>' . $result;
            $j--;
        } elseif ($i > 0) {
            $i--;
        }
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualy - Advanced Text Comparison Tool</title>
    <link rel="stylesheet" href="public/css/app.css">
</head>
<body>
    <div class="bg-animation"></div>
    <div class="bg-pattern"></div>
    
    <header class="header-animated">
        <div class="header-pattern-overlay"></div>
        <div class="header-content">
            <div class="header-icon">📝</div>
            <h1>Visualy</h1>
            <p>Advanced text comparison with real-time highlighting</p>
        </div>
    </header>
    
    <main class="container">
        <form method="POST" class="compare-form">
            <input type="hidden" name="action" value="compare">
            
            <div class="form-section">
                <h2>Compare Your Texts</h2>
                <p class="form-desc">Paste two versions of text to see what changed</p>
                <div class="textarea-grid">
                    <div class="textarea-wrapper">
                        <label for="a_text">
                            <span class="label-icon">📄</span>
                            <span>Text A (Original)</span>
                        </label>
                        <textarea id="a_text" name="a_text" rows="15" placeholder="Paste your original text here...&#10;&#10;• Code snippets&#10;• Documents&#10;• Configuration files&#10;• Any text content"><?= htmlspecialchars($_POST['a_text'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="textarea-wrapper">
                        <label for="b_text">
                            <span class="label-icon">📝</span>
                            <span>Text B (Modified)</span>
                        </label>
                        <textarea id="b_text" name="b_text" rows="15" placeholder="Paste your modified text here...&#10;&#10;Changes will be highlighted&#10;in the result below"><?= htmlspecialchars($_POST['b_text'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="controls">
                <div class="controls-left">
                    <label class="toggle">
                        <input type="checkbox" id="monospace-toggle"> 
                        <span class="toggle-label">🔤 Monospace View</span>
                    </label>
                    <p class="toggle-info">Perfect for code, logs, and data files</p>
                </div>
                <div class="controls-right">
                    <button type="submit" class="btn-primary">
                        <span class="btn-icon">🔍</span>
                        Compare Texts
                    </button>
                    <button type="button" class="btn-reset" onclick="resetForm()">
                        <span class="btn-icon">🔄</span>
                        Reset
                    </button>
                </div>
            </div>
        </form>
        
        <?php if ($diffResult): ?>
        <section class="results">
            <div class="stats-container">
                <h2>📊 Comparison Overview</h2>
                
                <div class="stats-grid">
                    <div class="stat-card stat-card-add">
                        <div class="stat-icon">➕</div>
                        <div class="stat-content">
                            <span class="stat-label">Added Lines</span>
                            <span class="stat-value"><?= $stats['adds'] ?></span>
                        </div>
                        <div class="stat-bar">
                            <div class="stat-bar-fill" style="width: <?= min(100, $stats['adds'] * 10) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-del">
                        <div class="stat-icon">➖</div>
                        <div class="stat-content">
                            <span class="stat-label">Deleted Lines</span>
                            <span class="stat-value"><?= $stats['dels'] ?></span>
                        </div>
                        <div class="stat-bar">
                            <div class="stat-bar-fill" style="width: <?= min(100, $stats['dels'] * 10) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-mod">
                        <div class="stat-icon">♻️</div>
                        <div class="stat-content">
                            <span class="stat-label">Modified Lines</span>
                            <span class="stat-value"><?= $stats['mods'] ?></span>
                        </div>
                        <div class="stat-bar">
                            <div class="stat-bar-fill" style="width: <?= min(100, $stats['mods'] * 10) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-pct">
                        <div class="stat-icon">📈</div>
                        <div class="stat-content">
                            <span class="stat-label">Difference</span>
                            <span class="stat-value"><?= $stats['pct'] ?>%</span>
                        </div>
                        <div class="stat-bar">
                            <div class="stat-bar-fill" style="width: <?= $stats['pct'] ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="detailed-stats">
                    <div class="stat-detail">
                        <span class="detail-label">Lines</span>
                        <span class="detail-value"><?= $stats['linesA'] ?> → <?= $stats['linesB'] ?></span>
                    </div>
                    <div class="stat-detail">
                        <span class="detail-label">Words</span>
                        <span class="detail-value"><?= $stats['wordsA'] ?> → <?= $stats['wordsB'] ?> (Δ <?= $stats['wordDiff'] ?>)</span>
                    </div>
                    <div class="stat-detail">
                        <span class="detail-label">Characters</span>
                        <span class="detail-value"><?= $stats['charsA'] ?> → <?= $stats['charsB'] ?></span>
                    </div>
                </div>
            </div>
            
            <div class="result-section">
                <div class="result-header">
                    <h2>✅ Text B (With Highlighted Changes)</h2>
                    <p class="result-info">Green highlights show additions and modifications</p>
                </div>
                
                <div class="text-output-wrapper">
                    <div class="text-output" id="text-output">
                        <?= $diffResult['highlightedB'] ?>
                    </div>
                </div>
                
                <div class="output-actions">
                    <button type="button" class="btn-secondary" onclick="copyToClipboard()">
                        <span class="btn-icon">📋</span>
                        Copy Result
                    </button>
                    <button type="button" class="btn-secondary" onclick="downloadAsFile()">
                        <span class="btn-icon">💾</span>
                        Download Text
                    </button>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <section class="info-section">
            <h2>💡 How to Use</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-icon">1️⃣</div>
                    <h3>Paste Texts</h3>
                    <p>Enter your original and modified text in the boxes above</p>
                </div>
                <div class="info-card">
                    <div class="info-icon">2️⃣</div>
                    <h3>Click Compare</h3>
                    <p>See changes highlighted instantly in real-time</p>
                </div>
                <div class="info-card">
                    <div class="info-icon">3️⃣</div>
                    <h3>View Results</h3>
                    <p>Get detailed stats and highlighted differences</p>
                </div>
                <div class="info-card">
                    <div class="info-icon">4️⃣</div>
                    <h3>Export</h3>
                    <p>Copy or download your comparison results</p>
                </div>
            </div>
        </section>
    </main>
    
    <footer class="footer-animated">
        <div class="footer-content">
            <p>💡 <strong>Pro Tip:</strong> Use Monospace View for code and technical documents</p>
            
        </div>
    </footer>
    
    <script src="public/js/app.js"></script>
</body>
</html>
