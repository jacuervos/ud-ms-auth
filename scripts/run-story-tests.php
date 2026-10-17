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
    'sprints' => [],
];

$sprintGroups = [];

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
    mkdir($storyDir, 0777, true);

    $junitPath = $storyDir . '/junit.xml';
    $testdoxPath = $storyDir . '/tests.html';

    $cmd = sprintf(
        'php artisan test --filter %s --testdox-html %s --log-junit %s',
        escapeshellarg($filter),
        escapeshellarg($testdoxPath),
        escapeshellarg($junitPath)
    );

    echo "Running story {$id} (sprint {$sprint})...\n";
    passthru($cmd, $exitCode);

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

    $storySummary = [
        'id' => $id,
        'title' => $title,
        'sprint' => $sprint,
        'filter' => $filter,
        'tests' => $stats['tests'],
        'passed' => max(0, $stats['tests'] - $stats['failures'] - $stats['errors'] - $stats['skipped']),
        'failures' => $stats['failures'],
        'errors' => $stats['errors'],
        'skipped' => $stats['skipped'],
        'time' => round($stats['time'], 2),
        'testsPath' => 'sprints/' . $sprintKey . '/stories/' . $id . '/tests.html',
    ];

    $summary['stories'][] = $storySummary;
    $sprintGroups[$sprintKey]['stories'][] = $storySummary;
}

foreach ($sprintGroups as $group) {
    $summary['sprints'][] = [
        'sprint' => $group['sprint'],
        'total' => array_sum(array_column($group['stories'], 'tests')),
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
        $storyList .= sprintf(
            '<li><span>%s</span><strong>%d/%d</strong></li>',
            htmlspecialchars($story['title'], ENT_QUOTES, 'UTF-8'),
            (int)$story['passed'],
            (int)$story['tests']
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
    .hero, .card, .metric, .sprint-panel { background:#fff; border:1px solid #d9edd9; border-radius:16px; box-shadow:0 10px 28px rgba(46,125,50,.1); }
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
    .sprint-panel { margin-top:18px; padding:16px; }
    .sprint-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .sprint-header h2 { margin:0; }
    .pill { background:#eaf6ee; color:#1e7e34; padding:6px 10px; border-radius:999px; font-weight:700; }
    .metrics-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:12px; }
    .story-list { list-style:none; padding:0; margin:0; display:grid; gap:8px; }
    .story-list li { display:flex; justify-content:space-between; gap:12px; padding:10px 12px; border-radius:8px; background:#f8fafc; }
    @media (max-width: 800px) { .grid, .metrics-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 520px) { .row { flex-direction:column; align-items:flex-start; gap:8px; } }
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
    <section class="stories">{$sprintSections}</section>
  </div>
</body>
</html>
HTML;

file_put_contents($reportDir . '/index.html', $html);

echo "Generated backend story report in report/\n";
