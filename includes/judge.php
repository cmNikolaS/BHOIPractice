<?php
/**
 * includes/judge.php — Thin client for a hosted Judge0 instance.
 * ----------------------------------------------------------------------
 * MVP scope: compile + run a user's source against a single stdin and
 * return stdout/stderr/compile output + time/memory. This powers the
 * "Run code" panel (Pokreni kod) on task.php. Judging against the official
 * test sets is a documented phase 2 (see JUDGE.md) — it needs the test
 * cases imported, which is intentionally out of this MVP.
 *
 * Everything here is dormant unless JUDGE0_URL is configured.
 */

declare(strict_types=1);

/** Is the online judge configured/enabled? */
function judge_enabled(): bool
{
    return defined('JUDGE0_URL') && JUDGE0_URL !== '';
}

/**
 * Languages we expose, mapped to Judge0 CE language ids. Kept small and
 * aligned with what the archive's solutions use.
 */
function judge_languages(): array
{
    return [
        'cpp'    => ['id' => 54, 'label' => 'C++ (GCC)'],
        'c'      => ['id' => 50, 'label' => 'C (GCC)'],
        'python' => ['id' => 71, 'label' => 'Python 3'],
        'java'   => ['id' => 62, 'label' => 'Java'],
        'pascal' => ['id' => 67, 'label' => 'Pascal (FPC)'],
    ];
}

/**
 * Run source against one stdin via Judge0 (synchronous: wait=true).
 * Returns a normalized array, or ['error' => '...'] on transport failure.
 */
function judge_run(string $source, int $languageId, string $stdin = '', ?string $expectedOutput = null): array
{
    if (!judge_enabled()) {
        return ['error' => 'Judge nije konfigurisan (JUDGE0_URL nije postavljen).'];
    }

    $payload = [
        'language_id'  => $languageId,
        'source_code'  => base64_encode($source),
        'stdin'        => base64_encode($stdin),
        'redirect_stderr_to_stdout' => false,
    ];
    if ($expectedOutput !== null) {
        $payload['expected_output'] = base64_encode($expectedOutput);
    }

    $headers = ['Content-Type: application/json'];
    if (JUDGE0_KEY !== '') {
        $headers[] = 'X-RapidAPI-Key: ' . JUDGE0_KEY;
        if (JUDGE0_HOST !== '') {
            $headers[] = 'X-RapidAPI-Host: ' . JUDGE0_HOST;
        }
    }

    $ch = curl_init(JUDGE0_URL . '/submissions?base64_encoded=true&wait=true');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        return ['error' => 'Judge nije dostupan (HTTP ' . $code . ($err ? ", $err" : '') . ').'];
    }
    $r = json_decode((string) $body, true);
    if (!is_array($r)) {
        return ['error' => 'Neispravan odgovor od judge-a.'];
    }

    $b64 = static fn($v) => $v === null ? '' : base64_decode((string) $v);
    return [
        'status'      => $r['status']['description'] ?? 'Unknown',
        'status_id'   => (int) ($r['status']['id'] ?? 0),
        'stdout'      => $b64($r['stdout'] ?? null),
        'stderr'      => $b64($r['stderr'] ?? null),
        'compile'     => $b64($r['compile_output'] ?? null),
        'message'     => $b64($r['message'] ?? null),
        'time'        => $r['time'] ?? null,   // seconds
        'memory'      => $r['memory'] ?? null, // KB
    ];
}
