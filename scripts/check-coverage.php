<?php

declare(strict_types=1);

/**
 * Per-layer coverage threshold checker.
 *
 * Usage (CLI):
 *   php scripts/check-coverage.php <clover-xml> <thresholds-json> <package-src-dir>
 *
 * Exit codes:
 *   0 — all thresholds met.
 *   1 — at least one threshold not met.
 *   2 — input error (missing files, malformed XML/JSON).
 */

final class CoverageChecker
{
    /**
     * Walk all <file> elements in the clover XML and return per-file metrics.
     *
     * @return array<string, array{statements:int,covered:int}>
     */
    public function collectFileCoverage(\SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->xpath('//file') as $file) {
            $name = (string) $file['name'];
            $metrics = $file->metrics ?? null;
            if ($metrics === null) {
                continue;
            }
            $stmts = (int) $metrics['statements'];
            $covered = (int) $metrics['coveredstatements'];
            if ($stmts > 0) {
                $out[$name] = ['statements' => $stmts, 'covered' => $covered];
            }
        }
        return $out;
    }

    /**
     * Check per-layer thresholds.
     *
     * @param array<string, array{statements:int,covered:int}> $files
     * @param array<string, array{path:string,min:float}> $config
     * @return array{failed:bool, lines:list<string>}
     * @throws \InvalidArgumentException if any layer config entry is malformed
     */
    public function check(array $files, array $config, string $packageDir): array
    {
        $failed = false;
        $lines = [];

        foreach ($config as $layer => $cfg) {
            if (!is_array($cfg) || !isset($cfg['path'], $cfg['min']) || !is_string($cfg['path']) || !is_numeric($cfg['min'])) {
                throw new \InvalidArgumentException(
                    "Thresholds JSON entry '$layer' missing required keys 'path' (string) and 'min' (numeric)."
                );
            }

            $layerPath = $packageDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cfg['path']);
            $min = (float) $cfg['min'];

            $totalStmts = 0;
            $totalCovered = 0;
            foreach ($files as $absPath => $m) {
                if (str_starts_with($absPath, $layerPath . DIRECTORY_SEPARATOR) || $absPath === $layerPath) {
                    $totalStmts += $m['statements'];
                    $totalCovered += $m['covered'];
                }
            }

            if ($totalStmts === 0) {
                $lines[] = sprintf('[SKIP] %s: no statements found under %s', $layer, $cfg['path']);
                continue;
            }

            $pct = ($totalCovered / $totalStmts) * 100.0;
            $status = $pct >= $min ? 'OK ' : 'FAIL';
            $lines[] = sprintf('[%s] %-20s %.2f%% (min %.2f%%, %d/%d stmts, path=%s)',
                $status, $layer, $pct, $min, $totalCovered, $totalStmts, $cfg['path']);
            if ($pct < $min) {
                $failed = true;
            }
        }

        return ['failed' => $failed, 'lines' => $lines];
    }
}

// CLI boot — only when executed directly, not when required by tests.
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath((string) $argv[0]) === __FILE__) {
    if ($argc < 4) {
        fwrite(STDERR, "Usage: php check-coverage.php <clover.xml> <thresholds.json> <package-src-dir>\n");
        exit(2);
    }

    $cloverPath = $argv[1];
    $configPath = $argv[2];
    $packageDir = realpath($argv[3]);

    if ($packageDir === false || !is_file($cloverPath) || !is_file($configPath)) {
        fwrite(STDERR, "Error: clover, thresholds, or package dir not found.\n");
        exit(2);
    }

    $config = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($config)) {
        fwrite(STDERR, "Error: thresholds JSON malformed.\n");
        exit(2);
    }

    $xml = @simplexml_load_file($cloverPath);
    if ($xml === false) {
        fwrite(STDERR, "Error: cannot parse clover XML.\n");
        exit(2);
    }

    $checker = new CoverageChecker();
    try {
        $result = $checker->check($checker->collectFileCoverage($xml), $config, $packageDir);
    } catch (\InvalidArgumentException $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(2);
    }

    foreach ($result['lines'] as $line) {
        echo $line, "\n";
    }
    exit($result['failed'] ? 1 : 0);
}
