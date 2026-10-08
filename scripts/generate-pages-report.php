<?php

if ($argc < 4) {
    fwrite(STDERR, "Usage: php generate-pages-report.php <junit.xml> <tests.html> <output-dir>\n");
    exit(1);
}

[$script, $junitPath, $testsHtmlPath, $outputDir] = $argv;

if (!file_exists($junitPath)) {
    fwrite(STDERR, "JUnit file not found: {$junitPath}\n");
    exit(1);
}

if (!file_exists($testsHtmlPath)) {
    fwrite(STDERR, "Test report file not found: {$testsHtmlPath}\n");
    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

$xml = simplexml_load_file($junitPath);
if ($xml === false) {
    fwrite(STDERR, "Unable to parse JUnit XML.\n");
    exit(1);
}

$stats = [
    'tests' => 0,
    'failures' => 0,
    'errors' => 0,
    'skipped' => 0,
    'time' => 0.0,
];

$suites = [];

$collect = function (SimpleXMLElement $suite) use (&$collect, &$stats, &$suites): void {
    $attributes = $suite->attributes();
    $suiteTests = (int)($attributes['tests'] ?? 0);
    $suiteFailures = (int)($attributes['failures'] ?? 0);
    $suiteErrors = (int)($attributes['errors'] ?? 0);
    $suiteSkipped = (int)($attributes['skipped'] ?? 0);
    $suiteTime = (float)($attributes['time'] ?? 0);

    $stats['tests'] += $suiteTests;
    $stats['failures'] += $suiteFailures;
    $stats['errors'] += $suiteErrors;
    $stats['skipped'] += $suiteSkipped;
    $stats['time'] += $suiteTime;

    $name = trim((string)($attributes['name'] ?? 'Suite'));
    if ($name !== '') {
        $suites[] = [
            'name' => $name,
            'tests' => $suiteTests,
            'failures' => $suiteFailures,
            'errors' => $suiteErrors,
            'skipped' => $suiteSkipped,
            'time' => $suiteTime,
        ];
    }

    if (isset($suite->testsuite)) {
        foreach ($suite->testsuite as $childSuite) {
            $collect($childSuite);
        }
    }
};

if (isset($xml->testsuite)) {
    foreach ($xml->testsuite as $suite) {
        $collect($suite);
    }
}

$passed = max(0, $stats['tests'] - $stats['failures'] - $stats['errors'] - $stats['skipped']);
$successRate = $stats['tests'] > 0 ? (int) round(($passed / $stats['tests']) * 100) : 100;
$statusLabel = ($stats['failures'] === 0 && $stats['errors'] === 0) ? 'All green' : 'Needs attention';
$statusTone = ($stats['failures'] === 0 && $stats['errors'] === 0) ? 'good' : 'warning';
$lastUpdated = gmdate('Y-m-d H:i:s') . ' UTC';

$suiteCards = '';
foreach ($suites as $suite) {
    $summary = [];
    if ($suite['tests'] > 0) {
        $summary[] = $suite['tests'] . ' tests';
    }
    if ($suite['failures'] > 0) {
        $summary[] = $suite['failures'] . ' failed';
    }
    if ($suite['errors'] > 0) {
        $summary[] = $suite['errors'] . ' errors';
    }
    if ($suite['skipped'] > 0) {
        $summary[] = $suite['skipped'] . ' skipped';
    }

    $suiteCards .= sprintf(
        '<article class="suite-card"><div><p class="suite-name">%s</p><p class="suite-meta">%s</p></div><div class="suite-time">%ss</div></article>',
        htmlspecialchars($suite['name'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars(implode(' · ', $summary) ?: 'No metrics', ENT_QUOTES, 'UTF-8'),
        number_format($suite['time'], 2)
    );
}

$testsHtmlName = basename($testsHtmlPath);
$successBarWidth = $stats['tests'] > 0 ? number_format(($passed / $stats['tests']) * 100, 2) : '100';

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ud-ms-auth Report</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #2E7D32;
      --primary-dark: #1B5E20;
      --secondary: #E8F5E8;
      --accent: #4CAF50;
      --text: #1F2937;
      --muted: #6B7280;
      --surface: rgba(255, 255, 255, 0.88);
      --surface-strong: #FFFFFF;
      --border: rgba(46, 125, 50, 0.16);
      --shadow: 0 24px 70px rgba(27, 94, 32, 0.14);
      --radius-xl: 28px;
      --radius-lg: 22px;
      --radius-md: 16px;
    }

    * { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; }
    body {
      font-family: 'Nunito', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(46, 125, 50, 0.18), transparent 28%),
        radial-gradient(circle at top right, rgba(76, 175, 80, 0.16), transparent 22%),
        linear-gradient(180deg, #F7FFF8 0%, #F2FAF3 45%, #EEF7EF 100%);
    }

    .wrap {
      max-width: 1180px;
      margin: 0 auto;
      padding: 32px 20px 56px;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 22px;
      gap: 16px;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .brand-mark {
      width: 54px;
      height: 54px;
      border-radius: 18px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      box-shadow: 0 16px 28px rgba(46, 125, 50, 0.28);
      position: relative;
      overflow: hidden;
    }

    .brand-mark::after {
      content: '';
      position: absolute;
      inset: 10px;
      border-radius: 12px;
      border: 2px solid rgba(255, 255, 255, 0.55);
    }

    .brand-copy h1 {
      font-size: 1.05rem;
      margin: 0;
      font-weight: 900;
      letter-spacing: 0.2px;
    }

    .brand-copy p {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 0.95rem;
    }

    .pill {
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.7);
      border: 1px solid var(--border);
      color: var(--primary-dark);
      font-weight: 800;
      font-size: 0.9rem;
      box-shadow: 0 10px 22px rgba(27, 94, 32, 0.08);
      white-space: nowrap;
    }

    .hero {
      display: grid;
      grid-template-columns: 1.25fr 0.95fr;
      gap: 22px;
      margin-bottom: 22px;
    }

    .panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
    }

    .hero-main {
      padding: 30px;
      position: relative;
      overflow: hidden;
      min-height: 320px;
    }

    .hero-main::before {
      content: '';
      position: absolute;
      right: -70px;
      top: -70px;
      width: 220px;
      height: 220px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(76,175,80,0.16), rgba(76,175,80,0));
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 8px 14px;
      border-radius: 999px;
      background: rgba(232, 245, 232, 0.95);
      color: var(--primary-dark);
      font-size: 0.84rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .hero-main h2 {
      margin: 16px 0 12px;
      font-size: clamp(2rem, 5vw, 3.7rem);
      line-height: 0.96;
      letter-spacing: -0.04em;
      max-width: 11ch;
    }

    .hero-main p {
      margin: 0;
      max-width: 60ch;
      color: var(--muted);
      font-size: 1.02rem;
      line-height: 1.6;
    }

    .cta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 24px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 46px;
      padding: 0 18px;
      border-radius: 14px;
      text-decoration: none;
      font-weight: 900;
      letter-spacing: 0.01em;
      transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .btn:hover { transform: translateY(-1px); }

    .btn-primary {
      color: #fff;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      box-shadow: 0 16px 26px rgba(46, 125, 50, 0.28);
    }

    .btn-secondary {
      color: var(--primary-dark);
      background: #fff;
      border: 1px solid var(--border);
    }

    .hero-side {
      display: grid;
      gap: 16px;
    }

    .status-card {
      padding: 24px;
      min-height: 148px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .status-label {
      color: var(--muted);
      font-size: 0.95rem;
      font-weight: 700;
    }

    .status-value {
      margin: 8px 0 0;
      font-size: 2rem;
      font-weight: 900;
      letter-spacing: -0.04em;
    }

    .status-chip {
      align-self: flex-start;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
      font-weight: 800;
      font-size: 0.88rem;
    }

    .good { background: rgba(76, 175, 80, 0.14); color: #1E7E34; }
    .warning { background: rgba(255, 152, 0, 0.14); color: #B76A00; }

    .metrics {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 16px;
      margin-bottom: 22px;
    }

    .metric {
      padding: 18px 18px 20px;
      background: rgba(255, 255, 255, 0.88);
      border: 1px solid var(--border);
      border-radius: 22px;
      box-shadow: 0 14px 30px rgba(27, 94, 32, 0.08);
    }

    .metric span {
      display: block;
      color: var(--muted);
      font-size: 0.88rem;
      font-weight: 800;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .metric strong {
      font-size: 1.8rem;
      letter-spacing: -0.03em;
      display: block;
    }

    .metric small {
      display: block;
      margin-top: 6px;
      color: var(--muted);
    }

    .content {
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 22px;
    }

    .section {
      padding: 24px;
    }

    .section h3 {
      margin: 0 0 8px;
      font-size: 1.1rem;
    }

    .section p.lead {
      margin: 0 0 16px;
      color: var(--muted);
    }

    .progress {
      height: 12px;
      border-radius: 999px;
      overflow: hidden;
      background: rgba(46, 125, 50, 0.08);
      margin: 18px 0 10px;
    }

    .progress > div {
      width: {$successBarWidth}%;
      height: 100%;
      border-radius: 999px;
      background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 100%);
    }

    .progress-note {
      display: flex;
      justify-content: space-between;
      color: var(--muted);
      font-size: 0.92rem;
      font-weight: 700;
    }

    .suite-list {
      display: grid;
      gap: 12px;
    }

    .suite-card {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: center;
      padding: 16px 18px;
      background: rgba(255,255,255,0.88);
      border: 1px solid var(--border);
      border-radius: 18px;
    }

    .suite-name {
      margin: 0;
      font-weight: 900;
      font-size: 1rem;
    }

    .suite-meta {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 0.92rem;
    }

    .suite-time {
      font-size: 1.05rem;
      font-weight: 900;
      color: var(--primary-dark);
      white-space: nowrap;
    }

    .footer {
      margin-top: 18px;
      color: var(--muted);
      font-size: 0.9rem;
    }

    @media (max-width: 960px) {
      .hero, .content { grid-template-columns: 1fr; }
      .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .topbar { flex-direction: column; align-items: flex-start; }
    }

    @media (max-width: 640px) {
      .wrap { padding: 20px 14px 40px; }
      .hero-main { padding: 22px; }
      .metrics { grid-template-columns: 1fr; }
      .suite-card { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header class="topbar">
      <div class="brand">
        <div class="brand-mark" aria-hidden="true"></div>
        <div class="brand-copy">
          <h1>ud-ms-auth</h1>
          <p>Authentication and user management metrics</p>
        </div>
      </div>
      <div class="pill">Updated {$lastUpdated}</div>
    </header>

    <section class="hero">
      <div class="panel hero-main">
        <div class="eyebrow">GitHub Pages report</div>
        <h2>Auth metrics with an app-style dashboard.</h2>
        <p>
          This page keeps the technical report available, but presents the most important numbers in a clean interface
          aligned with the visual language used by the apps: green gradients, rounded cards, strong hierarchy, and a
          calmer reading flow.
        </p>
        <div class="cta-row">
          <a class="btn btn-primary" href="{$testsHtmlName}">Open detailed report</a>
          <a class="btn btn-secondary" href="#suites">View suites</a>
        </div>
      </div>

      <div class="hero-side">
        <div class="panel status-card">
          <div class="status-label">Workflow status</div>
          <div class="status-value">{$statusLabel}</div>
          <div class="status-chip {$statusTone}">{$successRate}% pass rate</div>
        </div>
        <div class="panel status-card">
          <div class="status-label">Total duration</div>
          <div class="status-value">{$stats['time']}s</div>
          <div class="status-chip good">Composer + PHPUnit</div>
        </div>
      </div>
    </section>

    <section class="metrics" aria-label="Summary metrics">
      <div class="metric"><span>Tests</span><strong>{$stats['tests']}</strong><small>Executed in the last run</small></div>
      <div class="metric"><span>Passed</span><strong>{$passed}</strong><small>Successful assertions</small></div>
      <div class="metric"><span>Failures</span><strong>{$stats['failures']}</strong><small>Broken expectations</small></div>
      <div class="metric"><span>Errors</span><strong>{$stats['errors']}</strong><small>Execution issues</small></div>
      <div class="metric"><span>Skipped</span><strong>{$stats['skipped']}</strong><small>Non executed tests</small></div>
    </section>

    <section class="content">
      <div class="panel section">
        <h3>Execution health</h3>
        <p class="lead">The closer the bar is to the right, the healthier the current suite.</p>
        <div class="progress"><div></div></div>
        <div class="progress-note">
          <span>{$successRate}% pass rate</span>
          <span>{$passed} / {$stats['tests']} tests</span>
        </div>
        <div class="footer">
          Detailed PHPUnit HTML remains available in the linked report so you do not lose per-test visibility.
        </div>
      </div>

      <div id="suites" class="panel section">
        <h3>Suites</h3>
        <p class="lead">A quick summary of the suites that ran in this pipeline.</p>
        <div class="suite-list">
          {$suiteCards}
        </div>
      </div>
    </section>
  </div>
</body>
</html>
HTML;

file_put_contents($outputDir . '/index.html', $html);
copy($testsHtmlPath, $outputDir . '/tests.html');

echo "Generated app-style report in {$outputDir}\n";