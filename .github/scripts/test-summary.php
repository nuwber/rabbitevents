<?php

$phpVersion = $argv[1] ?? PHP_VERSION;
$summary = "## 🧪 PHPUnit Test & Coverage Report (PHP {$phpVersion})\n\n";

$junitFile = getcwd() . '/junit.xml';
if (file_exists($junitFile)) {
    $junit = simplexml_load_file($junitFile);
    $totalTests = 0;
    $failures = 0;
    $errors = 0;
    $skipped = 0;
    $assertions = 0;
    $time = 0.0;
    $failedCases = [];

    // Traverse all testsuites
    $suites = $junit->xpath('//testsuite');
    foreach ($suites as $suite) {
        if (isset($suite->testcase)) {
            foreach ($suite->testcase as $case) {
                $totalTests++;
                $assertions += (int) ($case['assertions'] ?? 0);
                $time += (float) ($case['time'] ?? 0);

                if (isset($case->failure)) {
                    $failures++;
                    $failedCases[] = [
                        'name' => (string) ($case['name'] ?? 'Unknown test'),
                        'class' => (string) ($case['class'] ?? ($case['classname'] ?? '')),
                        'message' => trim((string) $case->failure),
                    ];
                } elseif (isset($case->error)) {
                    $errors++;
                    $failedCases[] = [
                        'name' => (string) ($case['name'] ?? 'Unknown test'),
                        'class' => (string) ($case['class'] ?? ($case['classname'] ?? '')),
                        'message' => trim((string) $case->error),
                    ];
                } elseif (isset($case->skipped)) {
                    $skipped++;
                }
            }
        }
    }

    $passed = $totalTests - $failures - $errors - $skipped;
    $statusIcon = ($failures === 0 && $errors === 0) ? '✅' : '❌';
    $timeFormatted = round($time, 3) . 's';

    $summary .= "### {$statusIcon} Test Results\n\n";
    $summary .= "| Status | Total | Passed | Failed | Errors | Skipped | Assertions | Duration |\n";
    $summary .= "|:------:|:-----:|:------:|:------:|:------:|:-------:|:----------:|:--------:|\n";
    $summary .= "| {$statusIcon} | **{$totalTests}** | {$passed} | {$failures} | {$errors} | {$skipped} | {$assertions} | {$timeFormatted} |\n\n";

    if (!empty($failedCases)) {
        $summary .= "<details><summary>❌ <b>Failed Tests (" . count($failedCases) . ")</b></summary>\n\n";
        foreach ($failedCases as $failed) {
            $summary .= "#### `{$failed['class']}::{$failed['name']}`\n";
            $summary .= "```\n" . substr($failed['message'], 0, 1000) . "\n```\n\n";
        }
        $summary .= "</details>\n\n";
    }
}

$coverageFile = getcwd() . '/coverage.xml';
if (file_exists($coverageFile)) {
    $clover = simplexml_load_file($coverageFile);
    if (isset($clover->project->metrics)) {
        $m = $clover->project->metrics;
        $statements = (int) ($m['statements'] ?? 0);
        $coveredStatements = (int) ($m['coveredstatements'] ?? 0);
        $methods = (int) ($m['methods'] ?? 0);
        $coveredMethods = (int) ($m['coveredmethods'] ?? 0);
        $elements = (int) ($m['elements'] ?? 0);
        $coveredElements = (int) ($m['coveredelements'] ?? 0);

        $linePct = $statements > 0 ? round(($coveredStatements / $statements) * 100, 2) : 0;
        $methodPct = $methods > 0 ? round(($coveredMethods / $methods) * 100, 2) : 0;
        $elemPct = $elements > 0 ? round(($coveredElements / $elements) * 100, 2) : 0;

        $badge = function (float $pct): string {
            if ($pct >= 80) return "🟢 **{$pct}%**";
            if ($pct >= 60) return "🟡 **{$pct}%**";
            return "🔴 **{$pct}%**";
        };

        $summary .= "### 📊 Code Coverage\n\n";
        $summary .= "| Metric | Covered | Total | Coverage |\n";
        $summary .= "|:-------|--------:|------:|:---------|\n";
        $summary .= "| **Lines / Statements** | {$coveredStatements} | {$statements} | " . $badge($linePct) . " |\n";
        $summary .= "| **Methods / Functions** | {$coveredMethods} | {$methods} | " . $badge($methodPct) . " |\n";
        $summary .= "| **Total Elements** | {$coveredElements} | {$elements} | " . $badge($elemPct) . " |\n\n";
    }
}

$summaryFile = getenv('GITHUB_STEP_SUMMARY');
if ($summaryFile && is_writable(dirname($summaryFile))) {
    file_put_contents($summaryFile, $summary, FILE_APPEND);
} else {
    echo $summary;
}
