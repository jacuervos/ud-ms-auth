<?php

$configPath = __DIR__ . '/../stories.config.json';
$reportDir = __DIR__ . '/../report';
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

$summary = [
    'generatedAt' => gmdate('c'),
    'stories' => [],
];

foreach ($stories as $story) {
    $id = $story['id'];
    $title = $story['title'];
    $filter = $story['filter'];

    $storyDir = $storiesDir . '/' . $id;
    mkdir($storyDir, 0777, true);

    $junitPath = $storyDir . '/junit.xml';
    $testdoxPath = $storyDir . '/tests.html';

    $cmd = sprintf(
        'php artisan test --filter %s --testdox-html %s --log-junit %s',
        escapeshellarg($filter),
        escapeshellarg($testdoxPath),
        escapeshellarg($junitPath)
    );

    echo "Running story {$id}...\n";
    passthru($cmd, $exitCode);

    if ($exitCode !== 0) {
        exit($exitCode);
    }

    $stats = [
        'tests' => 0,
        'failures' => 0,
        'errors' => 0,
        'skipped' => 0,
        'time' => 0.0,
    ];

    $xml = @simplexml_load_file($junitPath);
    if ($xml !== false && isset($xml->testsuite)) {
        foreach ($xml->testsuite as $suite) {
            $attrs = $suite->attributes();
            $stats['tests'] += (int)($attrs['tests'] ?? 0);
            $stats['failures'] += (int)($attrs['failures'] ?? 0);
            $stats['errors'] += (int)($attrs['errors'] ?? 0);
            $stats['skipped'] += (int)($attrs['skipped'] ?? 0);
            $stats['time'] += (float)($attrs['time'] ?? 0);
        }
    }

    $summary['stories'][] = [
        'id' => $id,
        'title' => $title,
        'filter' => $filter,
        'tests' => $stats['tests'],
        'passed' => max(0, $stats['tests'] - $stats['failures'] - $stats['errors'] - $stats['skipped']),
        'failures' => $stats['failures'],
        'errors' => $stats['errors'],
        'skipped' => $stats['skipped'],
        'time' => round($stats['time'], 2),
        'testsPath' => 'stories/' . $id . '/tests.html',
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

$cards = '';
foreach ($summary['stories'] as $story) {
    $needsAttention = ($story['failures'] + $story['errors']) > 0;
    $chipClass = $needsAttention ? 'warning' : 'good';
    $chipText = $needsAttention ? 'Needs attention' : 'Healthy';

    $cards .= sprintf(
        '<article class="card"><h3>%s</h3><p class="meta">ID: %s</p><p class="meta">Filter: %s</p><div class="stats"><span>%d/%d passed</span><span>%d failed</span><span>%d errors</span></div><div class="row"><span class="chip %s">%s</span><a class="btn" href="%s">Open testdox</a></div></article>',
        htmlspecialchars($story['title'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($story['id'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($story['filter'], ENT_QUOTES, 'UTF-8'),
        $story['passed'],
        $story['tests'],
        $story['failures'],
        $story['errors'],
        $chipClass,
        $chipText,
        htmlspecialchars($story['testsPath'], ENT_QUOTES, 'UTF-8')
    );
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ud-ms-auth Story Report</title>
  <style>
    :root { --g:#2e7d32; --g2:#4caf50; --text:#1f2937; --muted:#6b7280; }
    body { margin:0; font-family: Nunito, Arial, sans-serif; color:var(--text); background:linear-gradient(180deg,#f8fff8 0,#eef8ef 100%); }
    .wrap { max-width:1100px; margin:0 auto; padding:28px 16px 40px; }
    .hero, .card, .metric { background:#fff; border:1px solid #d9edd9; border-radius:16px; box-shadow:0 10px 28px rgba(46,125,50,.1); }
    .hero { padding:20px; }
    .grid { margin-top:14px; display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
    .metric { padding:12px; }
    .metric b { font-size:1.25rem; }
    .meta { color:var(--muted); margin:0; }
    .stories { margin-top:14px; display:grid; gap:12px; }
    .card { padding:14px; display:grid; gap:8px; }
    .card h3 { margin:0; }
    .stats { display:flex; gap:12px; flex-wrap:wrap; font-weight:700; }
    .row { display:flex; justify-content:space-between; align-items:center; }
    .chip { padding:6px 10px; border-radius:999px; font-weight:800; font-size:.84rem; }
    .good { background:rgba(76,175,80,.16); color:#1e7e34; }
    .warning { background:rgba(255,152,0,.16); color:#b76a00; }
    .btn { text-decoration:none; color:white; background:linear-gradient(135deg,var(--g),var(--g2)); padding:8px 12px; border-radius:10px; font-weight:800; }
    @media (max-width: 800px) { .grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 520px) { .grid { grid-template-columns:1fr; } .row { flex-direction:column; align-items:flex-start; gap:8px; } }
  </style>
</head>
<body>
  <div class="wrap">
    <section class="hero">
      <h1>ud-ms-auth - Story Report</h1>
      <p class="meta">Generated at {$summary['generatedAt']}</p>
      <div class="grid">
        <div class="metric"><small>Total tests</small><br><b>{$totalTests}</b></div>
        <div class="metric"><small>Passed</small><br><b>{$totalPassed}</b></div>
        <div class="metric"><small>Failed + errors</small><br><b>{$totalFailures}+{$totalErrors}</b></div>
        <div class="metric"><small>Pass rate</small><br><b>{$passRate}%</b></div>
      </div>
    </section>
    <section class="stories">{$cards}</section>
  </div>
</body>
</html>
HTML;

file_put_contents($reportDir . '/index.html', $html);

echo "Generated backend story report in report/\n";
