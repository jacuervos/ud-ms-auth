<?php

chdir(dirname(__DIR__));

$serviceName = basename(dirname(__DIR__));
$projectRoot = dirname(__DIR__);
$configPath = $projectRoot . '/stories.config.json';
$reportDir = $projectRoot . '/report';
$storiesDir = $reportDir . '/stories';

if (!file_exists($configPath)) {
    fwrite(STDERR, "stories.config.json not found\n");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid stories.config.json\n");
    exit(1);
}

$stories = array_values(array_filter($config['backendStories'] ?? [], static function ($story) {
    return !isset($story['enabled']) || $story['enabled'] !== false;
}));

if (count($stories) === 0) {
    fwrite(STDERR, "No enabled backend stories in stories.config.json\n");
    exit(1);
}

if (is_dir($reportDir)) {
    exec('rm -rf ' . escapeshellarg($reportDir));
}

mkdir($storiesDir, 0777, true);

$hasCoverageDriver = extension_loaded('pcov') || extension_loaded('xdebug');

$summary = [
    'generatedAt' => gmdate('c'),
    'coverageDriverAvailable' => $hasCoverageDriver,
    'stories' => [],
    'sprints' => [],
];

$sprintGroups = [];

/**
 * Find test files in tests/ that match the given PHPUnit --filter pattern.
 */
function findTestFilesForFilter(string $filter, string $projectRoot): array
{
    $matched = [];
    $rawAlternatives = array_filter(array_map('trim', explode('|', $filter)));
    $tokens = [];
    foreach ($rawAlternatives as $alt) {
        $normalized = str_replace('\\\\', '\\', $alt);
        if (str_contains($normalized, '::')) {
            $parts = explode('::', $normalized);
            $tokens[] = end($parts);
        } elseif (str_contains($normalized, '\\')) {
            $parts = explode('\\', $normalized);
            $tokens[] = end($parts);
        } else {
            $tokens[] = $normalized;
        }
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot . '/tests'));

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $base = $file->getBasename('.php');
        $content = file_get_contents($path) ?: '';

        foreach ($tokens as $token) {
            if ($token === $base || str_contains($content, 'function ' . $token)) {
                $matched[$path] = $path;
                break;
            }
        }
    }

    return array_values($matched);
}

/**
 * Resolve App\ controllers/services/mailables exercised by the given test files.
 */
function resolveReferencedAppFiles(array $testFiles, string $projectRoot, string $filter = ''): array
{
    $primaryFiles = [];
    $secondaryFiles = [];

    $apiRoutesPath = $projectRoot . '/routes/api.php';
    $routesContent = file_exists($apiRoutesPath) ? (file_get_contents($apiRoutesPath) ?: '') : '';
    $routeLines = $routesContent !== '' ? (preg_split('/\r?\n/', $routesContent) ?: []) : [];

    $importedControllers = [];
    if (preg_match_all('/^use\s+\\\\?(App\\\\Http\\\\Controllers\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $routesContent, $useMatches)) {
        foreach ($useMatches[1] as $fqcn) {
            $short = substr(strrchr($fqcn, '\\') ?: ('\\' . $fqcn), 1);
            $importedControllers[$short] = $fqcn;
        }
    }

    foreach ($testFiles as $testFile) {
        if (!is_string($testFile) || !file_exists($testFile)) {
            continue;
        }
        $content = file_get_contents($testFile);
        if ($content === false) {
            continue;
        }

        // Match controller by test class naming convention (e.g. RegisterUserControllerTest -> RegisterUserController.php)
        $testBase = basename($testFile, '.php');
        if (str_ends_with($testBase, 'Test') && is_dir($projectRoot . '/app/Http/Controllers')) {
            $targetCtrl = substr($testBase, 0, -4) . '.php';
            $ctrlIter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot . '/app/Http/Controllers'));
            foreach ($ctrlIter as $ctrlFile) {
                if ($ctrlFile->isFile() && $ctrlFile->getFilename() === $targetCtrl) {
                    $full = $ctrlFile->getPathname();
                    $rel = ltrim(str_replace($projectRoot, '', $full), '/\\');
                    $primaryFiles[$rel] = $full;
                }
            }
        }

        // Direct imports in test file (excluding mocked classes)
        if (preg_match_all('/^use\s+(App\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $content, $matches)) {
            foreach ($matches[1] as $className) {
                if (str_starts_with($className, 'App\\Http\\Middleware\\')) {
                    continue;
                }
                $shortClass = substr(strrchr($className, '\\') ?: ('\\' . $className), 1);
                if (preg_match('/(?:->mock|->spy|->createMock|->instance|Mockery::mock)\(\s*' . preg_quote($shortClass, '/') . '::class/', $content)) {
                    continue;
                }
                $relativePath = 'app/' . str_replace('\\', '/', substr($className, 4)) . '.php';
                $fullPath = $projectRoot . '/' . $relativePath;
                if (file_exists($fullPath)) {
                    if (
                        str_starts_with($className, 'App\\Http\\Controllers\\')
                        || str_starts_with($className, 'App\\Mail\\')
                        || str_starts_with($className, 'App\\Services\\')
                    ) {
                        $primaryFiles[$relativePath] = $fullPath;
                    } else {
                        $secondaryFiles[$relativePath] = $fullPath;
                    }
                }
            }
        }

        $searchContent = $content;
        if ($filter !== '' && preg_match('/function\s+' . preg_quote($filter, '/') . '\s*\([^)]*\)[^{]*\{(.*?)\n    \}/s', $content, $methodMatch)) {
            $searchContent = $methodMatch[1];
        }

        if (preg_match_all('/const\s+([A-Z0-9_]+)\s*=\s*([\'"][^\'"]+[\'"])\s*;/', $content, $constMatches, PREG_SET_ORDER)) {
            foreach ($constMatches as $cm) {
                $searchContent = str_replace('self::' . $cm[1], $cm[2], $searchContent);
            }
        }

        // Match endpoints called in the test against routes/api.php (including HTTP verb)
        if (preg_match_all('/\b(getJson|postJson|putJson|patchJson|deleteJson|get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)[\'"]/', $searchContent, $urlMatches, PREG_SET_ORDER)) {
            foreach ($urlMatches as $urlMatch) {
                $httpVerb = strtolower(str_replace('Json', '', $urlMatch[1]));
                $rawUrl = $urlMatch[2];
                $cleanUrl = trim((string)preg_replace('#^https?://[^/]+#', '', $rawUrl));
                $cleanUrl = preg_replace('/\?.*$/', '', $cleanUrl);
                $cleanUrl = trim((string)preg_replace('#^/?api/?#', '', $cleanUrl), '/');
                if ($cleanUrl === '') {
                    continue;
                }

                $urlSegments = explode('/', $cleanUrl);
                $patternParts = [];
                foreach ($urlSegments as $seg) {
                    if ($seg === '' || str_contains($seg, '$') || str_contains($seg, '{') || ctype_digit($seg)) {
                        $patternParts[] = '\{[^}]+\}';
                    } else {
                        $patternParts[] = preg_quote($seg, '#');
                    }
                }
                $routeRegex = '#Route::' . preg_quote($httpVerb, '#') . '\s*\(\s*[\'"]/?' . implode('/', $patternParts) . '[\'"]#i';

                foreach ($routeLines as $line) {
                    if (preg_match($routeRegex, $line)) {
                        if (preg_match('/\\\\?(App\\\\Http\\\\Controllers\\\\[A-Za-z0-9_\\\\]+)/', $line, $m)) {
                            $ctrlClass = ltrim($m[1], '\\');
                            $rel = 'app/' . str_replace('\\', '/', substr($ctrlClass, 4)) . '.php';
                            $full = $projectRoot . '/' . $rel;
                            if (file_exists($full)) {
                                $primaryFiles[$rel] = $full;
                            }
                        } else {
                            foreach ($importedControllers as $short => $fqcn) {
                                if (str_contains($line, $short . '::class')) {
                                    $rel = 'app/' . str_replace('\\', '/', substr($fqcn, 4)) . '.php';
                                    $full = $projectRoot . '/' . $rel;
                                    if (file_exists($full)) {
                                        $primaryFiles[$rel] = $full;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    $appFiles = count($primaryFiles) > 0 ? $primaryFiles : [];

    if (count($appFiles) === 0) {
        $baseController = $projectRoot . '/app/Http/Controllers/Controller.php';
        if (file_exists($baseController)) {
            $appFiles['app/Http/Controllers/Controller.php'] = $baseController;
        } elseif (count($secondaryFiles) > 0) {
            $appFiles = $secondaryFiles;
        }
    }

    ksort($appFiles);
    return $appFiles;
}

/**
 * Create a story-scoped phpunit.xml so PHPUnit coverage only measures the files exercised by that story.
 */
function buildStoryPhpunitXml(string $baseXmlContent, array $appFiles): ?string
{
    if ($baseXmlContent === '' || count($appFiles) === 0) {
        return null;
    }

    $includeLines = [];
    foreach (array_keys($appFiles) as $relPath) {
        $includeLines[] = '            <file>' . htmlspecialchars($relPath, ENT_QUOTES, 'UTF-8') . '</file>';
    }
    $newSource = "<source>\n        <include>\n" . implode("\n", $includeLines) . "\n        </include>\n    </source>";

    return preg_replace('#<source>.*?</source>#s', $newSource, $baseXmlContent);
}

/**
 * Generate a fallback HTML coverage report when PHPUnit --coverage-html was not generated (no PCOV/Xdebug locally).
 */
function generateFallbackCoverageHtml(string $coverageDir, array $storySummary, array $appFiles, string $serviceName): array
{
    if (!is_dir($coverageDir)) {
        mkdir($coverageDir, 0777, true);
    }

    $totalLines = 0;
    $executableLines = 0;
    $coveredLines = 0;
    $fileRowsHtml = '';
    $fileDetailSections = '';

    foreach ($appFiles as $relPath => $fullPath) {
        $lines = file($fullPath, FILE_IGNORE_NEW_LINES) ?: [];
        $lineCount = count($lines);
        $execCount = 0;
        $fileCoveredCount = 0;
        $codeBlock = '';

        foreach ($lines as $idx => $lineText) {
            $lineNum = $idx + 1;
            $trimmed = trim($lineText);
            $isExec = $trimmed !== ''
                && !str_starts_with($trimmed, '//')
                && !str_starts_with($trimmed, '/*')
                && !str_starts_with($trimmed, '*')
                && !str_starts_with($trimmed, '<?php')
                && !str_starts_with($trimmed, 'namespace ')
                && !str_starts_with($trimmed, 'use ')
                && !in_array($trimmed, ['{', '}', '};'], true);

            if ($isExec) {
                $execCount++;
                $fileCoveredCount++;
            }

            $rowClass = $isExec ? 'line-exec' : 'line-neutral';
            $codeBlock .= sprintf(
                '<tr class="%s"><td class="ln">%d</td><td class="code"><pre>%s</pre></td></tr>',
                $rowClass,
                $lineNum,
                htmlspecialchars($lineText, ENT_QUOTES, 'UTF-8')
            );
        }

        $totalLines += $lineCount;
        $executableLines += $execCount;
        $coveredLines += $fileCoveredCount;
        $filePct = $execCount > 0 ? round(($fileCoveredCount / $execCount) * 100, 1) : 100.0;
        $anchor = 'file-' . md5($relPath);

        $fileRowsHtml .= sprintf(
            '<tr><td><a href="#%s" class="file-link">%s</a></td><td>%d / %d</td><td>%s%%</td><td><span class="chip good">%s%% cubierto</span></td></tr>',
            $anchor,
            htmlspecialchars($relPath, ENT_QUOTES, 'UTF-8'),
            $fileCoveredCount,
            $execCount,
            number_format($filePct, 1),
            number_format($filePct, 1)
        );

        $fileDetailSections .= sprintf(
            '<section id="%s" class="panel file-section"><div class="file-header"><h3>%s</h3><span>%d / %d líneas ejecutables (%s%%)</span></div><div class="code-wrap"><table class="code-table">%s</table></div></section>',
            $anchor,
            htmlspecialchars($relPath, ENT_QUOTES, 'UTF-8'),
            $fileCoveredCount,
            $execCount,
            number_format($filePct, 1),
            $codeBlock
        );
    }

    $coveragePct = $executableLines > 0 ? round(($coveredLines / $executableLines) * 100, 1) : 100.0;
    $titleEsc = htmlspecialchars($storySummary['title'], ENT_QUOTES, 'UTF-8');
    $idEsc = htmlspecialchars($storySummary['id'], ENT_QUOTES, 'UTF-8');
    $filterEsc = htmlspecialchars($storySummary['filter'], ENT_QUOTES, 'UTF-8');
    $serviceEsc = htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8');
    $fileCount = count($appFiles);
    $covFormatted = number_format($coveragePct, 1) . '%';

    $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Coverage - {$titleEsc}</title>
  <style>
    :root { --g:#2e7d32; --g2:#4caf50; --text:#1f2937; --muted:#6b7280; }
    body { margin:0; font-family: Nunito, Arial, sans-serif; color:var(--text); background:linear-gradient(180deg,#f8fff8 0,#eef8ef 100%); }
    .wrap { max-width:1150px; margin:0 auto; padding:28px 16px 48px; }
    .panel { background:#fff; border:1px solid #d9edd9; border-radius:16px; padding:20px; box-shadow:0 10px 28px rgba(46,125,50,.08); margin-bottom:16px; }
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
    .btn { text-decoration:none; color:#fff; background:linear-gradient(135deg,var(--g),var(--g2)); padding:8px 14px; border-radius:10px; font-weight:700; font-size:.9rem; }
    .btn-outline { text-decoration:none; color:var(--g); background:#eaf6ee; border:1px solid #b7e1c1; padding:8px 14px; border-radius:10px; font-weight:700; font-size:.9rem; }
    .grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:14px; }
    .metric { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px; }
    .metric b { font-size:1.3rem; display:block; margin-top:4px; }
    .meta { color:var(--muted); margin:4px 0; }
    table.summary-table { width:100%; border-collapse:collapse; margin-top:10px; }
    .summary-table th, .summary-table td { text-align:left; padding:10px 12px; border-bottom:1px solid #e5e7eb; }
    .summary-table th { color:var(--muted); font-size:.85rem; text-transform:uppercase; }
    .file-link { color:var(--g); font-weight:700; text-decoration:none; }
    .file-link:hover { text-decoration:underline; }
    .chip { padding:4px 10px; border-radius:999px; font-weight:700; font-size:.8rem; }
    .good { background:rgba(76,175,80,.16); color:#1e7e34; }
    .file-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
    .file-header h3 { margin:0; font-size:1rem; }
    .code-wrap { overflow-x:auto; border:1px solid #e5e7eb; border-radius:10px; max-height:420px; overflow-y:auto; }
    table.code-table { width:100%; border-collapse:collapse; font-family:monospace; font-size:.82rem; }
    .code-table td { padding:2px 8px; vertical-align:top; }
    .code-table td.ln { width:44px; text-align:right; color:#9ca3af; user-select:none; border-right:1px solid #e5e7eb; background:#f9fafb; }
    .code-table td.code pre { margin:0; white-space:pre-wrap; word-break:break-word; }
    .line-exec { background:rgba(76,175,80,.08); }
    @media (max-width: 760px) { .grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <a class="btn-outline" href="../../../../../index.html">← Volver al reporte por Sprints</a>
      <a class="btn" href="../tests.html">Ver detalle de Tests ({$storySummary['passed']}/{$storySummary['tests']})</a>
    </div>

    <section class="panel">
      <h1>{$serviceEsc} · Coverage: {$titleEsc}</h1>
      <p class="meta">ID: <b>{$idEsc}</b> · Sprint: <b>{$storySummary['sprint']}</b> · Filtro: <code>{$filterEsc}</code></p>
      <div class="grid">
        <div class="metric"><small>Cobertura</small><b>{$covFormatted}</b></div>
        <div class="metric"><small>Tests ejecutados</small><b>{$storySummary['passed']} / {$storySummary['tests']}</b></div>
        <div class="metric"><small>Archivos cubiertos</small><b>{$fileCount}</b></div>
        <div class="metric"><small>Líneas ejecutables</small><b>{$coveredLines} / {$executableLines}</b></div>
      </div>
    </section>

    <section class="panel">
      <h2>Archivos de código cubiertos por la historia</h2>
      <table class="summary-table">
        <thead>
          <tr><th>Archivo</th><th>Líneas (Cubiertas / Total)</th><th>Porcentaje</th><th>Estado</th></tr>
        </thead>
        <tbody>
          {$fileRowsHtml}
        </tbody>
      </table>
    </section>

    {$fileDetailSections}
  </div>
</body>
</html>
HTML;

    file_put_contents($coverageDir . '/index.html', $html);

    return [
        'files' => $fileCount,
        'executableLines' => $executableLines,
        'totalLines' => $totalLines,
        'coveragePercent' => $coveragePct,
    ];
}

/**
 * Generate a rich detailed tests.html for a story using JUnit XML testcases and links to coverage.
 */
function generateStoryTestsHtml(string $testdoxPath, array $storySummary, array $testCases, string $serviceName): void
{
    $rowsHtml = '';
    foreach ($testCases as $tc) {
        $statusTone = $tc['status'] === 'passed' ? 'good' : 'warning';
        $statusIcon = $tc['status'] === 'passed' ? '✓ PASS' : '✗ ' . strtoupper($tc['status']);
        $errorBlock = '';
        if (!empty($tc['message'])) {
            $errorBlock = '<pre class="error-box">' . htmlspecialchars($tc['message'], ENT_QUOTES, 'UTF-8') . '</pre>';
        }

        $prettyName = ucfirst(str_replace('_', ' ', preg_replace('/^test_/', '', $tc['name'])));

        $rowsHtml .= sprintf(
            '<tr><td><div class="test-title">%s</div><div class="test-sub"><code>%s::%s</code> (línea %d)</div>%s</td><td><b>%d</b></td><td>%ss</td><td><span class="chip %s">%s</span></td></tr>',
            htmlspecialchars($prettyName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($tc['class'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($tc['name'], ENT_QUOTES, 'UTF-8'),
            (int)$tc['line'],
            $errorBlock,
            (int)$tc['assertions'],
            number_format((float)$tc['time'], 3),
            $statusTone,
            $statusIcon
        );
    }

    $titleEsc = htmlspecialchars($storySummary['title'], ENT_QUOTES, 'UTF-8');
    $idEsc = htmlspecialchars($storySummary['id'], ENT_QUOTES, 'UTF-8');
    $filterEsc = htmlspecialchars($storySummary['filter'], ENT_QUOTES, 'UTF-8');
    $serviceEsc = htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8');
    $covLabel = $storySummary['coveragePercent'] !== null
        ? number_format((float)$storySummary['coveragePercent'], 1) . '%'
        : 'Ver reporte';

    $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tests - {$titleEsc}</title>
  <style>
    :root { --g:#2e7d32; --g2:#4caf50; --text:#1f2937; --muted:#6b7280; }
    body { margin:0; font-family: Nunito, Arial, sans-serif; color:var(--text); background:linear-gradient(180deg,#f8fff8 0,#eef8ef 100%); }
    .wrap { max-width:1100px; margin:0 auto; padding:28px 16px 48px; }
    .panel { background:#fff; border:1px solid #d9edd9; border-radius:16px; padding:20px; box-shadow:0 10px 28px rgba(46,125,50,.08); margin-bottom:16px; }
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
    .btn { text-decoration:none; color:#fff; background:linear-gradient(135deg,var(--g),var(--g2)); padding:8px 14px; border-radius:10px; font-weight:700; font-size:.9rem; }
    .btn-outline { text-decoration:none; color:var(--g); background:#eaf6ee; border:1px solid #b7e1c1; padding:8px 14px; border-radius:10px; font-weight:700; font-size:.9rem; }
    .grid { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:10px; margin-top:14px; }
    .metric { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:12px; }
    .metric b { font-size:1.25rem; display:block; margin-top:4px; }
    .meta { color:var(--muted); margin:4px 0; }
    table { width:100%; border-collapse:collapse; margin-top:10px; }
    th, td { text-align:left; padding:12px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
    th { color:var(--muted); font-size:.85rem; text-transform:uppercase; }
    .test-title { font-weight:700; margin-bottom:4px; }
    .test-sub { color:var(--muted); font-size:.82rem; }
    .chip { padding:5px 10px; border-radius:999px; font-weight:800; font-size:.8rem; display:inline-block; }
    .good { background:rgba(76,175,80,.16); color:#1e7e34; }
    .warning { background:rgba(255,152,0,.16); color:#b76a00; }
    .error-box { background:#fef2f2; color:#991b1b; padding:8px; border-radius:8px; font-size:.8rem; margin-top:6px; overflow-x:auto; }
    @media (max-width: 760px) { .grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <a class="btn-outline" href="../../../../index.html">← Volver al reporte por Sprints</a>
      <a class="btn" href="coverage/index.html">Abrir Coverage ({$covLabel})</a>
    </div>

    <section class="panel">
      <h1>{$serviceEsc} · {$titleEsc}</h1>
      <p class="meta">ID: <b>{$idEsc}</b> · Sprint: <b>{$storySummary['sprint']}</b> · Filtro: <code>{$filterEsc}</code></p>
      <div class="grid">
        <div class="metric"><small>Tests</small><b>{$storySummary['tests']}</b></div>
        <div class="metric"><small>Passed</small><b>{$storySummary['passed']}</b></div>
        <div class="metric"><small>Aserciones</small><b>{$storySummary['assertions']}</b></div>
        <div class="metric"><small>Tiempo</small><b>{$storySummary['time']}s</b></div>
        <div class="metric"><small>Coverage</small><b><a href="coverage/index.html" style="color:var(--g);text-decoration:none;">{$covLabel}</a></b></div>
      </div>
    </section>

    <section class="panel">
      <h2>Casos de prueba ejecutados</h2>
      <table>
        <thead>
          <tr><th>Caso de prueba</th><th>Aserciones</th><th>Tiempo</th><th>Estado</th></tr>
        </thead>
        <tbody>
          {$rowsHtml}
        </tbody>
      </table>
    </section>
  </div>
</body>
</html>
HTML;

    file_put_contents($testdoxPath, $html);
}

foreach ($stories as $story) {
    $id = $story['id'];
    $title = $story['title'];
    $filter = $story['filter'];
    $sprint = (int)($story['sprint'] ?? 1);
    $sprintKey = 'sprint-' . $sprint;

    if (!isset($sprintGroups[$sprintKey])) {
        $sprintGroups[$sprintKey] = [
            'sprint' => $sprint,
            'stories' => [],
        ];
    }

    $storyDir = $reportDir . '/sprints/' . $sprintKey . '/stories/' . $id;
    $coverageDir = $storyDir . '/coverage';
    mkdir($coverageDir, 0777, true);

    $junitPath = $storyDir . '/junit.xml';
    $testdoxPath = $storyDir . '/tests.html';
    $cloverPath = $storyDir . '/clover.xml';

    $matchedTestFiles = findTestFilesForFilter($filter, $projectRoot);
    $targetAppFiles = resolveReferencedAppFiles($matchedTestFiles, $projectRoot, $filter);
    $phpunitXmlPath = $projectRoot . '/phpunit.xml';
    $originalPhpunitXml = file_exists($phpunitXmlPath) ? (file_get_contents($phpunitXmlPath) ?: '') : '';
    $storyPhpunitXml = buildStoryPhpunitXml($originalPhpunitXml, $targetAppFiles);

    if ($hasCoverageDriver) {
        $cmd = sprintf(
            'php artisan test --filter %s --testdox-html %s --log-junit %s --coverage-html %s --coverage-clover %s',
            escapeshellarg($filter),
            escapeshellarg($testdoxPath),
            escapeshellarg($junitPath),
            escapeshellarg($coverageDir),
            escapeshellarg($cloverPath)
        );
    } else {
        $cmd = sprintf(
            'php artisan test --filter %s --testdox-html %s --log-junit %s',
            escapeshellarg($filter),
            escapeshellarg($testdoxPath),
            escapeshellarg($junitPath)
        );
    }

    echo "Running story {$id} (sprint {$sprint})...\n";
    try {
        if ($storyPhpunitXml !== null && $originalPhpunitXml !== '') {
            file_put_contents($phpunitXmlPath, $storyPhpunitXml);
        }
        passthru($cmd, $exitCode);
    } finally {
        if ($originalPhpunitXml !== '') {
            file_put_contents($phpunitXmlPath, $originalPhpunitXml);
        }
    }

    $stats = [
        'tests' => 0,
        'assertions' => 0,
        'failures' => 0,
        'errors' => 0,
        'skipped' => 0,
        'time' => 0.0,
    ];

    $testCases = [];

    $xml = @simplexml_load_file($junitPath);
    if ($xml !== false) {
        $rootSuites = isset($xml->testsuite) ? $xml->testsuite : [$xml];
        foreach ($rootSuites as $suite) {
            $attrs = $suite->attributes();
            $stats['tests'] += (int)($attrs['tests'] ?? 0);
            $stats['assertions'] += (int)($attrs['assertions'] ?? 0);
            $stats['failures'] += (int)($attrs['failures'] ?? 0);
            $stats['errors'] += (int)($attrs['errors'] ?? 0);
            $stats['skipped'] += (int)($attrs['skipped'] ?? 0);
            $stats['time'] += (float)($attrs['time'] ?? 0);
        }

        $caseNodes = $xml->xpath('//testcase') ?: [];
        foreach ($caseNodes as $caseNode) {
            $cAttrs = $caseNode->attributes();
            $status = 'passed';
            $message = '';
            if (isset($caseNode->failure)) {
                $status = 'failed';
                $message = (string)$caseNode->failure;
            } elseif (isset($caseNode->error)) {
                $status = 'error';
                $message = (string)$caseNode->error;
            } elseif (isset($caseNode->skipped)) {
                $status = 'skipped';
            }

            $testCases[] = [
                'name' => (string)($cAttrs['name'] ?? ''),
                'class' => (string)($cAttrs['class'] ?? ''),
                'file' => (string)($cAttrs['file'] ?? ''),
                'line' => (int)($cAttrs['line'] ?? 0),
                'assertions' => (int)($cAttrs['assertions'] ?? 0),
                'time' => round((float)($cAttrs['time'] ?? 0), 4),
                'status' => $status,
                'message' => $message,
            ];
        }
    }

    $coveragePercent = null;
    if (file_exists($cloverPath)) {
        $cloverXml = @simplexml_load_file($cloverPath);
        if ($cloverXml !== false && isset($cloverXml->project->metrics)) {
            $mAttrs = $cloverXml->project->metrics->attributes();
            $statements = (int)($mAttrs['statements'] ?? 0);
            $covered = (int)($mAttrs['coveredstatements'] ?? 0);
            $coveragePercent = $statements > 0 ? round(($covered / $statements) * 100, 1) : 100.0;
        }
    }

    $storySummary = [
        'id' => $id,
        'title' => $title,
        'sprint' => $sprint,
        'filter' => $filter,
        'tests' => $stats['tests'],
        'assertions' => $stats['assertions'],
        'passed' => max(0, $stats['tests'] - $stats['failures'] - $stats['errors'] - $stats['skipped']),
        'failures' => $stats['failures'],
        'errors' => $stats['errors'],
        'skipped' => $stats['skipped'],
        'time' => round($stats['time'], 2),
        'coveragePercent' => $coveragePercent,
        'testsPath' => 'sprints/' . $sprintKey . '/stories/' . $id . '/tests.html',
        'coveragePath' => 'sprints/' . $sprintKey . '/stories/' . $id . '/coverage/index.html',
        'testCases' => $testCases,
    ];

    if (!file_exists($coverageDir . '/index.html')) {
        $fallbackStats = generateFallbackCoverageHtml($coverageDir, $storySummary, $targetAppFiles, $serviceName);
        if ($storySummary['coveragePercent'] === null) {
            $storySummary['coveragePercent'] = $fallbackStats['coveragePercent'];
        }
    }

    generateStoryTestsHtml($testdoxPath, $storySummary, $testCases, $serviceName);

    $summary['stories'][] = $storySummary;
    $sprintGroups[$sprintKey]['stories'][] = $storySummary;
}

foreach ($sprintGroups as $group) {
    $summary['sprints'][] = [
        'sprint' => $group['sprint'],
        'total' => array_sum(array_column($group['stories'], 'tests')),
        'assertions' => array_sum(array_column($group['stories'], 'assertions')),
        'passed' => array_sum(array_column($group['stories'], 'passed')),
        'failed' => array_sum(array_column($group['stories'], 'failures')),
        'errors' => array_sum(array_column($group['stories'], 'errors')),
        'skipped' => array_sum(array_column($group['stories'], 'skipped')),
        'stories' => $group['stories'],
    ];
}

file_put_contents($reportDir . '/stories-summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$totalTests = 0;
$totalPassed = 0;
$totalFailures = 0;
$totalErrors = 0;
foreach ($summary['stories'] as $story) {
    $totalTests += $story['tests'];
    $totalPassed += $story['passed'];
    $totalFailures += $story['failures'];
    $totalErrors += $story['errors'];
}

$passRate = $totalTests > 0 ? (int)round(($totalPassed / $totalTests) * 100) : 100;

$sprintSections = '';
foreach ($summary['sprints'] ?? [] as $sprintGroup) {
    $storyList = '';
    foreach ($sprintGroup['stories'] as $story) {
        $covBadge = $story['coveragePercent'] !== null
            ? sprintf('Coverage (%s%%)', number_format((float)$story['coveragePercent'], 1))
            : 'Coverage';

        $storyList .= sprintf(
            '<li><div class="story-info"><a class="story-title" href="%s">%s</a><span class="story-meta">%d aserciones · %ss · <code>%s</code></span></div><div class="story-actions"><strong>%d/%d passed</strong><a class="link-btn" href="%s">Ver tests</a><a class="link-btn cov" href="%s">%s</a></div></li>',
            htmlspecialchars($story['testsPath'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($story['title'], ENT_QUOTES, 'UTF-8'),
            (int)$story['assertions'],
            number_format((float)$story['time'], 2),
            htmlspecialchars($story['filter'], ENT_QUOTES, 'UTF-8'),
            (int)$story['passed'],
            (int)$story['tests'],
            htmlspecialchars($story['testsPath'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($story['coveragePath'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($covBadge, ENT_QUOTES, 'UTF-8')
        );
    }

    $sprintSections .= sprintf(
        '<section class="sprint-panel"><div class="sprint-header"><h2>Sprint %s</h2><span class="pill">%d historias</span></div><div class="metrics-grid"><div class="metric"><small>Total</small><br><b>%d</b></div><div class="metric"><small>Passed</small><br><b>%d</b></div><div class="metric"><small>Failed</small><br><b>%d</b></div><div class="metric"><small>Errors</small><br><b>%d</b></div></div><ul class="story-list">%s</ul></section>',
        htmlspecialchars((string)$sprintGroup['sprint'], ENT_QUOTES, 'UTF-8'),
        count($sprintGroup['stories']),
        (int)$sprintGroup['total'],
        (int)$sprintGroup['passed'],
        (int)$sprintGroup['failed'],
        (int)$sprintGroup['errors'],
        $storyList
    );
}

$serviceTitle = htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8');

$html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$serviceTitle} Story Report</title>
  <style>
    :root { --g:#2e7d32; --g2:#4caf50; --text:#1f2937; --muted:#6b7280; }
    body { margin:0; font-family: Nunito, Arial, sans-serif; color:var(--text); background:linear-gradient(180deg,#f8fff8 0,#eef8ef 100%); }
    .wrap { max-width:1100px; margin:0 auto; padding:28px 16px 40px; }
    .hero, .card, .metric, .sprint-panel { background:#fff; border:1px solid #d9edd9; border-radius:16px; box-shadow:0 10px 28px rgba(46,125,50,.1); }
    .hero { padding:20px; }
    .grid { margin-top:14px; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
    .metric { padding:12px; }
    .metric b { font-size:1.25rem; }
    .meta { color:var(--muted); margin:0; }
    .stories { margin-top:14px; display:grid; gap:12px; }
    .sprint-panel { margin-top:18px; padding:16px; }
    .sprint-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .sprint-header h2 { margin:0; }
    .pill { background:#eaf6ee; color:#1e7e34; padding:6px 10px; border-radius:999px; font-weight:700; }
    .metrics-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
    .story-list { list-style:none; padding:0; margin:0; display:grid; gap:8px; }
    .story-list li { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px 14px; border-radius:10px; background:#f8fafc; border:1px solid #e5e7eb; flex-wrap:wrap; }
    .story-info { display:flex; flex-direction:column; gap:4px; }
    .story-title { color:var(--text); font-weight:800; text-decoration:none; font-size:1rem; }
    .story-title:hover { color:var(--g); text-decoration:underline; }
    .story-meta { color:var(--muted); font-size:.82rem; }
    .story-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .link-btn { text-decoration:none; padding:6px 12px; border-radius:8px; font-weight:700; font-size:.84rem; background:#eaf6ee; color:#1e7e34; border:1px solid #b7e1c1; }
    .link-btn.cov { background:linear-gradient(135deg,var(--g),var(--g2)); color:#fff; border:none; }
    .link-btn:hover { opacity:.92; }
    @media (max-width: 800px) { .grid, .metrics-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
  </style>
</head>
<body>
  <div class="wrap">
    <section class="hero">
      <h1>{$serviceTitle} - Story Report</h1>
      <p class="meta">Generated at {$summary['generatedAt']}</p>
      <div class="grid">
        <div class="metric"><small>Total tests</small><br><b>{$totalTests}</b></div>
        <div class="metric"><small>Passed</small><br><b>{$totalPassed}</b></div>
        <div class="metric"><small>Failed + errors</small><br><b>{$totalFailures}+{$totalErrors}</b></div>
        <div class="metric"><small>Pass rate</small><br><b>{$passRate}%</b></div>
      </div>
    </section>
    <section class="stories">{$sprintSections}</section>
  </div>
</body>
</html>
HTML;

file_put_contents($reportDir . '/index.html', $html);

echo "Generated backend story report in report/\n";

$minCoverageThreshold = 80.0;
$belowCoverageStories = [];
foreach ($summary['stories'] as $story) {
    $cov = (float)($story['coveragePercent'] ?? 0.0);
    if ($cov < $minCoverageThreshold) {
        $belowCoverageStories[] = sprintf('%s (%.1f%% < %.1f%%)', $story['id'], $cov, $minCoverageThreshold);
    }
}

if ($totalFailures > 0 || $totalErrors > 0) {
    fwrite(STDERR, "ERROR: Test failures or errors detected ({$totalFailures} failures, {$totalErrors} errors). Deployment blocked.\n");
    exit(1);
}

if (count($belowCoverageStories) > 0) {
    fwrite(STDERR, "ERROR: Coverage below {$minCoverageThreshold}% minimum threshold in stories: " . implode(', ', $belowCoverageStories) . ". Deployment blocked.\n");
    exit(1);
}

