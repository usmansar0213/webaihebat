<?php
require_once __DIR__ . '/common.php';

enforce_allowed_hosts();

header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$isLocalHost = strpos($hostHeader, 'localhost') !== false || strpos($hostHeader, '127.0.0.1') !== false;
$cspMode = strtolower(env_value('ASSESSMENT_DASHBOARD_CSP_MODE', 'auto'));
if (!in_array($cspMode, ['auto', 'strict', 'dev'], true)) {
  $cspMode = 'auto';
}
$effectiveCspMode = $cspMode === 'auto' ? ($isLocalHost ? 'dev' : 'strict') : $cspMode;

if ($effectiveCspMode === 'dev') {
  header("Content-Security-Policy: default-src 'self' data: blob: http: https:; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data: blob: http: https:; style-src 'self' 'unsafe-inline' https:; script-src 'self' 'unsafe-inline' https:; connect-src 'self' http: https: ws: wss:");
} else {
  header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'");
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => $isHttps,
  'httponly' => true,
  'samesite' => 'Strict'
]);

session_name('aihebat_assessment_dashboard');
session_start();

function dashboard_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dashboard_parse_firestore_value(array $value) {
  if (isset($value['stringValue'])) {
    return (string) $value['stringValue'];
  }

  if (isset($value['integerValue'])) {
    return (int) $value['integerValue'];
  }

  if (isset($value['doubleValue'])) {
    return (float) $value['doubleValue'];
  }

  if (isset($value['booleanValue'])) {
    return (bool) $value['booleanValue'];
  }

  if (array_key_exists('nullValue', $value)) {
    return null;
  }

  if (isset($value['arrayValue']['values']) && is_array($value['arrayValue']['values'])) {
    return array_map('dashboard_parse_firestore_value', $value['arrayValue']['values']);
  }

  if (isset($value['mapValue']['fields']) && is_array($value['mapValue']['fields'])) {
    return dashboard_parse_firestore_fields($value['mapValue']['fields']);
  }

  return null;
}

function dashboard_parse_firestore_fields(array $fields): array {
  $output = [];
  foreach ($fields as $key => $value) {
    if (!is_array($value)) {
      continue;
    }
    $output[$key] = dashboard_parse_firestore_value($value);
  }

  return $output;
}

function dashboard_try_decode_json_string($value) {
  if (!is_string($value) || $value === '') {
    return $value;
  }

  $trimmed = trim($value);
  if (($trimmed[0] ?? '') !== '{' && ($trimmed[0] ?? '') !== '[') {
    return $value;
  }

  $decoded = json_decode($trimmed, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return $value;
  }

  return $decoded;
}

function dashboard_json_pretty($value): string {
  if (is_array($value)) {
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
  }
  return (string) $value;
}

function dashboard_mode_label(string $mode): string {
  if ($mode === 'organization') return 'Organisasi';
  if ($mode === 'personal') return 'Pribadi';
  if ($mode === 'training-evaluation') return 'Evaluasi Pelatihan';
  return $mode;
}

function dashboard_score_to_number(string $score): float {
  $score = trim($score);
  if ($score === '') {
    return 0.0;
  }

  if (preg_match('/^\d+(?:\.\d+)?\/(?:\d+(?:\.\d+)?)$/', $score) === 1) {
    [$valuePart, $maxPart] = explode('/', $score, 2);
    $value = (float) $valuePart;
    $max = (float) $maxPart;
    if ($max > 0) {
      return ($value / $max) * 100;
    }
  }

  if (is_numeric($score)) {
    return (float) $score;
  }

  if (preg_match('/\d+(?:\.\d+)?/', $score, $m) === 1) {
    return (float) $m[0];
  }

  return 0.0;
}

function dashboard_score_percent(string $mode, string $score): float {
  $raw = dashboard_score_to_number($score);

  if ($mode === 'organization') {
    $normalized = ($raw / 5) * 100;
    return max(0.0, min(100.0, $normalized));
  }

  if ($mode === 'personal') {
    $normalized = (($raw - 15) / 60) * 100;
    return max(0.0, min(100.0, $normalized));
  }

  if ($mode === 'training-evaluation') {
    $normalized = ($raw / 5) * 100;
    return max(0.0, min(100.0, $normalized));
  }

  return max(0.0, min(100.0, $raw));
}

function dashboard_mode_bar_class(string $mode): string {
  if ($mode === 'organization') return 'bar-fill-org';
  if ($mode === 'personal') return 'bar-fill-personal';
  if ($mode === 'training-evaluation') return 'bar-fill-training';
  return '';
}

function dashboard_mode_raw_score_label(string $mode, float $rawAverage): string {
  if ($mode === 'personal') {
    return number_format($rawAverage, 1) . '/75';
  }
  if ($mode === 'organization' || $mode === 'training-evaluation') {
    return number_format($rawAverage, 2) . '/5';
  }
  return number_format($rawAverage, 2);
}

function dashboard_query(array $params): string {
  return http_build_query($params);
}

function dashboard_client_ip(): string {
  return clean_text($_SERVER['REMOTE_ADDR'] ?? 'unknown', 64);
}

function dashboard_audit_log(string $event, array $details = []): void {
  $logFile = env_value(
    'ASSESSMENT_DASHBOARD_AUDIT_LOG_FILE',
    rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'aihebat-assessment-dashboard-audit.log'
  );

  $payload = array_merge([
    'ts' => gmdate('c'),
    'event' => $event,
    'ip' => dashboard_client_ip(),
    'ua' => clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 240)
  ], $details);

  $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if (!is_string($line) || $line === '') {
    return;
  }

  @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function dashboard_rate_limit_file(): string {
  return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'aihebat-assessment-dashboard-rate-limit.json';
}

function dashboard_load_rate_limit_map(): array {
  $file = dashboard_rate_limit_file();
  if (!is_file($file)) {
    return [];
  }

  $raw = @file_get_contents($file);
  if (!is_string($raw) || $raw === '') {
    return [];
  }

  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    return [];
  }

  return $decoded;
}

function dashboard_save_rate_limit_map(array $map): void {
  $file = dashboard_rate_limit_file();
  @file_put_contents($file, json_encode($map), LOCK_EX);
}

function dashboard_ensure_csrf_token(): string {
  if (empty($_SESSION['assessment_dashboard_csrf'])) {
    $_SESSION['assessment_dashboard_csrf'] = bin2hex(random_bytes(32));
  }
  return (string) $_SESSION['assessment_dashboard_csrf'];
}

function dashboard_validate_csrf_token(string $token): bool {
  $sessionToken = (string) ($_SESSION['assessment_dashboard_csrf'] ?? '');
  return $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function dashboard_extract_nps_from_answers($answers): ?int {
  if (!is_array($answers)) {
    return null;
  }

  foreach ($answers as $pillar) {
    if (!is_array($pillar) || empty($pillar['answers']) || !is_array($pillar['answers'])) {
      continue;
    }

    foreach ($pillar['answers'] as $answer) {
      if (!is_array($answer)) {
        continue;
      }

      $question = strtolower((string) ($answer['question'] ?? ''));
      if (strpos($question, 'merekomendasikan') === false) {
        continue;
      }

      $rawAnswer = trim((string) ($answer['answer'] ?? ''));
      if ($rawAnswer !== '' && preg_match('/^\d+$/', $rawAnswer)) {
        $value = (int) $rawAnswer;
        if ($value >= 0 && $value <= 10) {
          return $value;
        }
      }
    }
  }

  return null;
}

$passwordError = '';
$infoMessage = '';
$dashboardPassword = env_value('ASSESSMENT_DASHBOARD_PASSWORD', 'Usmansar02131978');
$sessionTtlSeconds = (int) env_value('ASSESSMENT_DASHBOARD_SESSION_TTL', '1800');
if ($sessionTtlSeconds < 300) {
  $sessionTtlSeconds = 300;
}
$maxFailedAttempts = (int) env_value('ASSESSMENT_DASHBOARD_MAX_ATTEMPTS', '5');
if ($maxFailedAttempts < 3) {
  $maxFailedAttempts = 3;
}
$lockoutSeconds = (int) env_value('ASSESSMENT_DASHBOARD_LOCKOUT_SECONDS', '900');
if ($lockoutSeconds < 60) {
  $lockoutSeconds = 60;
}
$ipMaxFailedAttempts = (int) env_value('ASSESSMENT_DASHBOARD_IP_MAX_ATTEMPTS', '10');
if ($ipMaxFailedAttempts < 3) {
  $ipMaxFailedAttempts = 3;
}
$ipLockoutSeconds = (int) env_value('ASSESSMENT_DASHBOARD_IP_LOCKOUT_SECONDS', '900');
if ($ipLockoutSeconds < 60) {
  $ipLockoutSeconds = 60;
}
$ipRateTtlSeconds = (int) env_value('ASSESSMENT_DASHBOARD_IP_RATE_TTL_SECONDS', '86400');
if ($ipRateTtlSeconds < 300) {
  $ipRateTtlSeconds = 300;
}

if (!isset($_SESSION['assessment_dashboard_failed_attempts'])) {
  $_SESSION['assessment_dashboard_failed_attempts'] = 0;
}
if (!isset($_SESSION['assessment_dashboard_lock_until'])) {
  $_SESSION['assessment_dashboard_lock_until'] = 0;
}

$nowTs = time();
$postAction = clean_text($_POST['action'] ?? '', 30);
$postHasValidCsrf = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrfToken = clean_text($_POST['csrf_token'] ?? '', 200);
  $postHasValidCsrf = dashboard_validate_csrf_token($postedCsrfToken);
  if (!$postHasValidCsrf) {
    $passwordError = 'Sesi keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    dashboard_audit_log('csrf_failed', ['action' => $postAction]);
  }
}

if ($postHasValidCsrf && $postAction === 'logout') {
  dashboard_audit_log('logout');
  $_SESSION = [];
  session_destroy();
  header('Location: assessment-dashboard.php');
  exit;
}

$isAuthed = !empty($_SESSION['assessment_dashboard_authenticated']);

if ($isAuthed) {
  $lastActivity = (int) ($_SESSION['assessment_dashboard_last_activity'] ?? 0);
  if ($lastActivity > 0 && ($nowTs - $lastActivity) > $sessionTtlSeconds) {
    $_SESSION = [];
    session_destroy();
    session_start();
    $_SESSION['assessment_dashboard_failed_attempts'] = 0;
    $_SESSION['assessment_dashboard_lock_until'] = 0;
    $isAuthed = false;
    $infoMessage = 'Sesi berakhir karena tidak ada aktivitas. Silakan login kembali.';
  }
}

if ($postHasValidCsrf && $postAction === 'login') {
  $clientIp = dashboard_client_ip();
  $rateLimitMap = dashboard_load_rate_limit_map();

  if (!isset($rateLimitMap[$clientIp]) || !is_array($rateLimitMap[$clientIp])) {
    $rateLimitMap[$clientIp] = ['failed' => 0, 'lock_until' => 0, 'last' => $nowTs];
  }

  $ipLastTouch = (int) ($rateLimitMap[$clientIp]['last'] ?? 0);
  if ($ipLastTouch > 0 && ($nowTs - $ipLastTouch) > $ipRateTtlSeconds) {
    $rateLimitMap[$clientIp] = ['failed' => 0, 'lock_until' => 0, 'last' => $nowTs];
  }

  $ipLockUntil = (int) ($rateLimitMap[$clientIp]['lock_until'] ?? 0);
  if ($ipLockUntil > $nowTs) {
    $waitIpSeconds = $ipLockUntil - $nowTs;
    $passwordError = 'Akses dari IP ini terkunci sementara. Coba lagi dalam ' . $waitIpSeconds . ' detik.';
    dashboard_audit_log('login_ip_locked', ['waitSeconds' => $waitIpSeconds]);
  } else {
    $lockUntil = (int) ($_SESSION['assessment_dashboard_lock_until'] ?? 0);
    if ($lockUntil > $nowTs) {
      $waitSeconds = $lockUntil - $nowTs;
      $passwordError = 'Terlalu banyak percobaan login. Coba lagi dalam ' . $waitSeconds . ' detik.';
      dashboard_audit_log('login_session_locked', ['waitSeconds' => $waitSeconds]);
    } else {
      $inputPassword = clean_text($_POST['password'] ?? '', 200);
      if ($dashboardPassword !== '' && hash_equals($dashboardPassword, $inputPassword)) {
        session_regenerate_id(true);
        $_SESSION['assessment_dashboard_authenticated'] = true;
        $_SESSION['assessment_dashboard_login_at'] = gmdate('c');
        $_SESSION['assessment_dashboard_last_activity'] = $nowTs;
        $_SESSION['assessment_dashboard_failed_attempts'] = 0;
        $_SESSION['assessment_dashboard_lock_until'] = 0;
        $rateLimitMap[$clientIp] = ['failed' => 0, 'lock_until' => 0, 'last' => $nowTs];
        $isAuthed = true;
        dashboard_audit_log('login_success');
      } else {
        $_SESSION['assessment_dashboard_failed_attempts'] = (int) $_SESSION['assessment_dashboard_failed_attempts'] + 1;
        $rateLimitMap[$clientIp]['failed'] = (int) ($rateLimitMap[$clientIp]['failed'] ?? 0) + 1;
        $rateLimitMap[$clientIp]['last'] = $nowTs;

        $remaining = $maxFailedAttempts - (int) $_SESSION['assessment_dashboard_failed_attempts'];
        if ($remaining <= 0) {
          $_SESSION['assessment_dashboard_lock_until'] = $nowTs + $lockoutSeconds;
          $_SESSION['assessment_dashboard_failed_attempts'] = 0;
          $passwordError = 'Terlalu banyak percobaan gagal. Akses dikunci sementara.';
          dashboard_audit_log('login_session_lock_applied', ['lockSeconds' => $lockoutSeconds]);
        } else {
          $passwordError = 'Password salah. Sisa percobaan: ' . $remaining . '.';
        }

        if ((int) $rateLimitMap[$clientIp]['failed'] >= $ipMaxFailedAttempts) {
          $rateLimitMap[$clientIp]['failed'] = 0;
          $rateLimitMap[$clientIp]['lock_until'] = $nowTs + $ipLockoutSeconds;
          $rateLimitMap[$clientIp]['last'] = $nowTs;
          $passwordError = 'Terlalu banyak percobaan gagal dari IP ini. Akses dikunci sementara.';
          dashboard_audit_log('login_ip_lock_applied', ['lockSeconds' => $ipLockoutSeconds]);
        } else {
          dashboard_audit_log('login_failed', [
            'remainingSessionAttempts' => max(0, $remaining),
            'remainingIpAttempts' => max(0, $ipMaxFailedAttempts - (int) $rateLimitMap[$clientIp]['failed'])
          ]);
        }
      }
    }
  }

  foreach ($rateLimitMap as $ipKey => $rateData) {
    $lastSeen = (int) ($rateData['last'] ?? 0);
    if ($lastSeen > 0 && ($nowTs - $lastSeen) > $ipRateTtlSeconds) {
      unset($rateLimitMap[$ipKey]);
    }
  }
  dashboard_save_rate_limit_map($rateLimitMap);
}

$csrfToken = dashboard_ensure_csrf_token();

if ($isAuthed) {
  $_SESSION['assessment_dashboard_last_activity'] = $nowTs;
}

$filterSessionKey = 'assessment_dashboard_filters';
if ($isAuthed && (string) ($_GET['reset'] ?? '') === '1') {
  unset($_SESSION[$filterSessionKey]);
}
$storedFilters = ($isAuthed && isset($_SESSION[$filterSessionKey]) && is_array($_SESSION[$filterSessionKey]))
  ? $_SESSION[$filterSessionKey]
  : [];

$allowedModes = ['organization', 'personal', 'training-evaluation'];
$tabOptions = [
  'all' => 'Semua',
  'organization' => 'Organisasi',
  'personal' => 'Pribadi',
  'training-evaluation' => 'Evaluasi Pelatihan'
];
$tabMeta = [
  'all' => ['icon' => 'bi-grid-3x3-gap', 'subtitle' => 'Gabungan semua data asesmen'],
  'organization' => ['icon' => 'bi-building', 'subtitle' => 'Fokus kesiapan organisasi'],
  'personal' => ['icon' => 'bi-person-check', 'subtitle' => 'Fokus kesiapan individu'],
  'training-evaluation' => ['icon' => 'bi-clipboard-check', 'subtitle' => 'Fokus evaluasi pelatihan']
];

$activeTab = strtolower(clean_text(
  array_key_exists('tab', $_GET)
    ? (string) $_GET['tab']
    : (string) ($storedFilters['tab'] ?? 'all'),
  40
));
if (!array_key_exists($activeTab, $tabOptions)) {
  $activeTab = 'all';
}

$selectedModes = $activeTab === 'all' ? $allowedModes : [$activeTab];

$searchTerm = clean_text(array_key_exists('q', $_GET) ? (string) $_GET['q'] : (string) ($storedFilters['q'] ?? ''), 120);
$dateFrom = clean_text(array_key_exists('date_from', $_GET) ? (string) $_GET['date_from'] : (string) ($storedFilters['date_from'] ?? ''), 20);
$dateTo = clean_text(array_key_exists('date_to', $_GET) ? (string) $_GET['date_to'] : (string) ($storedFilters['date_to'] ?? ''), 20);

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
  $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
  $dateTo = '';
}

$limit = (int) clean_text(array_key_exists('limit', $_GET) ? (string) $_GET['limit'] : (string) ($storedFilters['limit'] ?? '100'), 4);
if ($limit < 1) {
  $limit = 100;
}
if ($limit > 200) {
  $limit = 200;
}

$sortBy = clean_text(array_key_exists('sort', $_GET) ? (string) $_GET['sort'] : (string) ($storedFilters['sort'] ?? 'createdAt'), 30);
$allowedSortBy = ['createdAt', 'name', 'company', 'overallScore', 'overallLevel'];
if (!in_array($sortBy, $allowedSortBy, true)) {
  $sortBy = 'createdAt';
}

$sortOrder = strtolower(clean_text(array_key_exists('order', $_GET) ? (string) $_GET['order'] : (string) ($storedFilters['order'] ?? 'desc'), 5));
if ($sortOrder !== 'asc' && $sortOrder !== 'desc') {
  $sortOrder = 'desc';
}

$perPage = (int) clean_text(array_key_exists('per_page', $_GET) ? (string) $_GET['per_page'] : (string) ($storedFilters['per_page'] ?? '20'), 4);
if ($perPage < 10) {
  $perPage = 10;
}
if ($perPage > 100) {
  $perPage = 100;
}

$page = (int) clean_text($_GET['page'] ?? '1', 6);
if ($page < 1) {
  $page = 1;
}

if ($isAuthed) {
  $_SESSION[$filterSessionKey] = [
    'tab' => $activeTab,
    'q' => $searchTerm,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'limit' => $limit,
    'sort' => $sortBy,
    'order' => $sortOrder,
    'per_page' => $perPage
  ];
}

$items = [];
$errorMessage = '';
$stats = [
  'organization' => 0,
  'personal' => 0,
  'training-evaluation' => 0
];
$dailyCounts = [];
$levelCounts = [];
$npsRawScores = [];
$modeScoreSums = [
  'organization' => 0.0,
  'personal' => 0.0,
  'training-evaluation' => 0.0
];
$modeRawScoreSums = [
  'organization' => 0.0,
  'personal' => 0.0,
  'training-evaluation' => 0.0
];
$modeScoreCounts = [
  'organization' => 0,
  'personal' => 0,
  'training-evaluation' => 0
];

if ($isAuthed) {
  $projectId = gcp_project_id();
  $token = gcp_access_token();

  if ($projectId === '' || $token === '') {
    $errorMessage = 'Firestore belum tersedia pada environment ini.';
  } else {
    $collection = env_value('FIRESTORE_COLLECTION_ASSESSMENT', 'assessment_submissions');
    $runQueryUrl = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/databases/(default)/documents:runQuery';

    $query = [
      'structuredQuery' => [
        'from' => [[
          'collectionId' => $collection
        ]],
        'orderBy' => [[
          'field' => ['fieldPath' => 'createdAt'],
          'direction' => 'DESCENDING'
        ]],
        'limit' => $limit
      ]
    ];

    $response = http_json_post($runQueryUrl, $query, ['Authorization: Bearer ' . $token]);

    if (!$response['ok']) {
      $errorMessage = 'Gagal membaca data assessment dari Firestore.';
    } else {
      $rows = json_decode($response['body'], true);
      if (!is_array($rows)) {
        $errorMessage = 'Response Firestore tidak valid.';
      } else {
        foreach ($rows as $row) {
          if (!is_array($row) || empty($row['document']['fields']) || !is_array($row['document']['fields'])) {
            continue;
          }

          $documentName = (string) ($row['document']['name'] ?? '');
          $parts = explode('/', $documentName);
          $id = end($parts);

          $fields = dashboard_parse_firestore_fields($row['document']['fields']);
          if (isset($fields['pillarScores'])) {
            $fields['pillarScores'] = dashboard_try_decode_json_string($fields['pillarScores']);
          }
          if (isset($fields['answers'])) {
            $fields['answers'] = dashboard_try_decode_json_string($fields['answers']);
          }

          $assessmentMode = (string) ($fields['assessmentMode'] ?? '');
          if (isset($stats[$assessmentMode])) {
            $stats[$assessmentMode]++;
          }

          $items[] = [
            'id' => $id,
            'createdAt' => (string) ($fields['createdAt'] ?? ''),
            'assessmentMode' => $assessmentMode,
            'assessmentType' => (string) ($fields['assessmentType'] ?? ''),
            'name' => (string) ($fields['name'] ?? ''),
            'company' => (string) ($fields['company'] ?? ''),
            'email' => (string) ($fields['email'] ?? ''),
            'trainingGoal' => (string) ($fields['trainingGoal'] ?? ''),
            'overallScore' => (string) ($fields['overallScore'] ?? ''),
            'overallLevel' => (string) ($fields['overallLevel'] ?? ''),
            'weakestPillar' => (string) ($fields['weakestPillar'] ?? ''),
            'recommendation' => (string) ($fields['recommendation'] ?? ''),
            'pillarScores' => $fields['pillarScores'] ?? [],
            'answers' => $fields['answers'] ?? []
          ];

          $createdAt = (string) ($fields['createdAt'] ?? '');
          $dayKey = '';
          if ($createdAt !== '') {
            $ts = strtotime($createdAt);
            if ($ts !== false) {
              $dayKey = gmdate('Y-m-d', $ts);
            }
          }
          if ($dayKey === '') {
            $dayKey = 'unknown';
          }
          if (!isset($dailyCounts[$dayKey])) {
            $dailyCounts[$dayKey] = 0;
          }
          $dailyCounts[$dayKey]++;

          $levelKey = trim((string) ($fields['overallLevel'] ?? ''));
          if ($levelKey !== '') {
            if (!isset($levelCounts[$levelKey])) {
              $levelCounts[$levelKey] = 0;
            }
            $levelCounts[$levelKey]++;
          }

          if ($assessmentMode === 'training-evaluation') {
            $nps = dashboard_extract_nps_from_answers($fields['answers'] ?? []);
            if ($nps !== null) {
              $npsRawScores[] = $nps;
            }
          }
        }
      }
    }
  }
}

if ($isAuthed && empty($errorMessage)) {
  $filtered = [];
  foreach ($items as $item) {
    $itemMode = (string) ($item['assessmentMode'] ?? '');
    if (!in_array($itemMode, $selectedModes, true)) {
      continue;
    }

    if ($searchTerm !== '') {
      $haystack = strtolower(
        ($item['name'] ?? '') . ' ' .
        ($item['email'] ?? '') . ' ' .
        ($item['company'] ?? '')
      );
      if (strpos($haystack, strtolower($searchTerm)) === false) {
        continue;
      }
    }

    if ($dateFrom !== '' || $dateTo !== '') {
      $createdAt = (string) ($item['createdAt'] ?? '');
      $ts = strtotime($createdAt);
      if ($ts === false) {
        continue;
      }
      $day = gmdate('Y-m-d', $ts);
      if ($dateFrom !== '' && $day < $dateFrom) {
        continue;
      }
      if ($dateTo !== '' && $day > $dateTo) {
        continue;
      }
    }

    $filtered[] = $item;
  }
  $items = $filtered;

  $stats = [
    'organization' => 0,
    'personal' => 0,
    'training-evaluation' => 0
  ];
  $dailyCounts = [];
  $levelCounts = [];
  $npsRawScores = [];
  $modeScoreSums = [
    'organization' => 0.0,
    'personal' => 0.0,
    'training-evaluation' => 0.0
  ];
  $modeRawScoreSums = [
    'organization' => 0.0,
    'personal' => 0.0,
    'training-evaluation' => 0.0
  ];
  $modeScoreCounts = [
    'organization' => 0,
    'personal' => 0,
    'training-evaluation' => 0
  ];

  usort($items, function (array $a, array $b) use ($sortBy, $sortOrder): int {
    $cmp = 0;

    if ($sortBy === 'createdAt') {
      $cmp = (strtotime((string) ($a['createdAt'] ?? '')) ?: 0) <=> (strtotime((string) ($b['createdAt'] ?? '')) ?: 0);
    } elseif ($sortBy === 'name') {
      $cmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    } elseif ($sortBy === 'company') {
      $cmp = strcasecmp((string) ($a['company'] ?? ''), (string) ($b['company'] ?? ''));
    } elseif ($sortBy === 'overallLevel') {
      $cmp = strcasecmp((string) ($a['overallLevel'] ?? ''), (string) ($b['overallLevel'] ?? ''));
    } elseif ($sortBy === 'overallScore') {
      $cmp = dashboard_score_to_number((string) ($a['overallScore'] ?? '')) <=> dashboard_score_to_number((string) ($b['overallScore'] ?? ''));
    }

    return $sortOrder === 'asc' ? $cmp : -$cmp;
  });

  foreach ($items as $item) {
    $assessmentMode = (string) ($item['assessmentMode'] ?? '');
    if (isset($stats[$assessmentMode])) {
      $stats[$assessmentMode]++;
    }

    $createdAt = (string) ($item['createdAt'] ?? '');
    $dayKey = '';
    if ($createdAt !== '') {
      $ts = strtotime($createdAt);
      if ($ts !== false) {
        $dayKey = gmdate('Y-m-d', $ts);
      }
    }
    if ($dayKey === '') {
      $dayKey = 'unknown';
    }
    if (!isset($dailyCounts[$dayKey])) {
      $dailyCounts[$dayKey] = 0;
    }
    $dailyCounts[$dayKey]++;

    $levelKey = trim((string) ($item['overallLevel'] ?? ''));
    if ($levelKey !== '') {
      if (!isset($levelCounts[$levelKey])) {
        $levelCounts[$levelKey] = 0;
      }
      $levelCounts[$levelKey]++;
    }

    if ($assessmentMode === 'training-evaluation') {
      $nps = dashboard_extract_nps_from_answers($item['answers'] ?? []);
      if ($nps !== null) {
        $npsRawScores[] = $nps;
      }
    }

    if (isset($modeScoreSums[$assessmentMode])) {
      $rawScore = dashboard_score_to_number((string) ($item['overallScore'] ?? ''));
      $modeScoreSums[$assessmentMode] += dashboard_score_percent($assessmentMode, (string) ($item['overallScore'] ?? ''));
      $modeRawScoreSums[$assessmentMode] += $rawScore;
      $modeScoreCounts[$assessmentMode]++;
    }
  }
}

ksort($dailyCounts);

$dailyCountsRecent = $dailyCounts;
if (count($dailyCountsRecent) > 14) {
  $dailyCountsRecent = array_slice($dailyCountsRecent, -14, 14, true);
}

$maxDailyCount = empty($dailyCountsRecent) ? 0 : max($dailyCountsRecent);

$npsDetractors = 0;
$npsPassives = 0;
$npsPromoters = 0;
foreach ($npsRawScores as $npsScore) {
  if ($npsScore <= 6) {
    $npsDetractors++;
  } elseif ($npsScore <= 8) {
    $npsPassives++;
  } else {
    $npsPromoters++;
  }
}
$npsTotal = count($npsRawScores);
$npsValue = $npsTotal > 0
  ? (int) round((($npsPromoters / $npsTotal) - ($npsDetractors / $npsTotal)) * 100)
  : null;

$modeAverageScores = [];
$modeRawAverageLabels = [];
$totalAverageAccumulator = 0.0;
$totalAverageCount = 0;
foreach (['organization', 'personal', 'training-evaluation'] as $modeKey) {
  if (($modeScoreCounts[$modeKey] ?? 0) > 0) {
    $avg = $modeScoreSums[$modeKey] / $modeScoreCounts[$modeKey];
    $rawAvg = $modeRawScoreSums[$modeKey] / $modeScoreCounts[$modeKey];
    $modeAverageScores[$modeKey] = round($avg, 1);
    $modeRawAverageLabels[$modeKey] = dashboard_mode_raw_score_label($modeKey, $rawAvg);
    $totalAverageAccumulator += $avg * $modeScoreCounts[$modeKey];
    $totalAverageCount += $modeScoreCounts[$modeKey];
  } else {
    $modeAverageScores[$modeKey] = null;
    $modeRawAverageLabels[$modeKey] = '-';
  }
}
$overallAveragePercent = $totalAverageCount > 0 ? round($totalAverageAccumulator / $totalAverageCount, 1) : null;

$totalItems = count($items);
$totalPages = max(1, (int) ceil($totalItems / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$itemsPage = array_slice($items, $offset, $perPage);

$baseParams = [
  'tab' => $activeTab,
  'limit' => $limit,
  'q' => $searchTerm,
  'date_from' => $dateFrom,
  'date_to' => $dateTo,
  'sort' => $sortBy,
  'order' => $sortOrder,
  'per_page' => $perPage,
  'page' => 1
];

if ($isAuthed && isset($_GET['export']) && $_GET['export'] === 'csv' && $errorMessage === '') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="assessment-dashboard-' . gmdate('Ymd-His') . '.csv"');
  $output = fopen('php://output', 'w');

  fputcsv($output, ['id', 'createdAt', 'assessmentMode', 'assessmentType', 'name', 'company', 'email', 'trainingGoal', 'overallScore', 'overallLevel', 'weakestPillar', 'recommendation']);
  foreach ($items as $item) {
    fputcsv($output, [
      $item['id'],
      $item['createdAt'],
      $item['assessmentMode'],
      $item['assessmentType'],
      $item['name'],
      $item['company'],
      $item['email'],
      $item['trainingGoal'],
      $item['overallScore'],
      $item['overallLevel'],
      $item['weakestPillar'],
      $item['recommendation']
    ]);
  }
  fclose($output);
  exit;
}

if ($isAuthed && isset($_GET['export']) && $_GET['export'] === 'csv-detailed' && $errorMessage === '') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="assessment-dashboard-detailed-' . gmdate('Ymd-His') . '.csv"');
  $output = fopen('php://output', 'w');

  fputcsv($output, ['id', 'createdAt', 'assessmentMode', 'assessmentType', 'name', 'company', 'email', 'trainingGoal', 'overallScore', 'overallLevel', 'weakestPillar', 'recommendation', 'pillarScoresJson', 'answersJson']);
  foreach ($items as $item) {
    fputcsv($output, [
      $item['id'],
      $item['createdAt'],
      $item['assessmentMode'],
      $item['assessmentType'],
      $item['name'],
      $item['company'],
      $item['email'],
      $item['trainingGoal'],
      $item['overallScore'],
      $item['overallLevel'],
      $item['weakestPillar'],
      $item['recommendation'],
      dashboard_json_pretty($item['pillarScores'] ?? []),
      dashboard_json_pretty($item['answers'] ?? [])
    ]);
  }
  fclose($output);
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Hasil Assessment - AIHebat</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #f3f7fb;
      --card: #ffffff;
      --text: #233247;
      --muted: #5f728f;
      --line: #d7e0ea;
      --primary: #2a74d6;
      --danger: #b33a3a;
      --ok: #237a4b;
    }
    * { box-sizing: border-box; }
    body {
      background: var(--bg);
      color: var(--text);
      font-family: "Segoe UI", Tahoma, Arial, sans-serif;
      margin: 0;
      padding: 24px;
    }
    .wrap {
      margin: 0 auto;
      max-width: 1160px;
    }
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
      margin-bottom: 18px;
      padding: 18px;
    }
    h1 {
      font-size: 28px;
      margin: 0 0 8px;
    }
    p {
      margin: 0;
    }
    .muted {
      color: var(--muted);
    }
    .toolbar {
      align-items: end;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: space-between;
    }
    .toolbar form {
      align-items: end;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    label {
      color: var(--muted);
      display: block;
      font-size: 13px;
      margin-bottom: 4px;
    }
    input, select, button {
      border: 1px solid #c8d3e0;
      border-radius: 8px;
      font-size: 14px;
      padding: 8px 10px;
    }
    button {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
      cursor: pointer;
    }
    button.secondary {
      background: #fff;
      color: var(--text);
    }
    .stats {
      display: grid;
      gap: 10px;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      margin-top: 12px;
    }
    .stat {
      background: #f8fbff;
      border: 1px solid #d8e8fb;
      border-radius: 10px;
      padding: 12px;
    }
    .stat strong {
      display: block;
      font-size: 22px;
      margin-top: 4px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
    }
    th, td {
      border-bottom: 1px solid var(--line);
      font-size: 13px;
      padding: 10px 8px;
      text-align: left;
      vertical-align: top;
    }
    th {
      background: #f8fbff;
      font-size: 12px;
      letter-spacing: 0.03em;
      text-transform: uppercase;
    }
    details {
      margin-top: 6px;
    }
    details summary {
      color: var(--primary);
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
    }
    pre {
      background: #0f172a;
      border-radius: 8px;
      color: #e8eef8;
      font-size: 12px;
      margin: 8px 0 0;
      overflow: auto;
      padding: 10px;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .error {
      color: var(--danger);
      font-size: 14px;
      margin-top: 10px;
    }
    .ok {
      color: var(--ok);
    }
    .row {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      margin-top: 12px;
    }
    .mini-card {
      background: #ffffff;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 12px;
    }
    .mini-card h3 {
      font-size: 15px;
      margin: 0 0 10px;
    }
    .bars {
      display: grid;
      gap: 8px;
    }
    .bar-item {
      align-items: center;
      display: grid;
      gap: 8px;
      grid-template-columns: 80px 1fr 32px;
    }
    .bar-track {
      background: #ebf2fb;
      border-radius: 8px;
      height: 10px;
      overflow: hidden;
    }
    .bar-fill {
      background: #2a74d6;
      height: 100%;
    }
    .bar-fill-org {
      background: #2a74d6;
    }
    .bar-fill-personal {
      background: #0f9d6a;
    }
    .bar-fill-training {
      background: #f28c2f;
    }
    .nps-grid {
      display: grid;
      gap: 8px;
      grid-template-columns: repeat(3, 1fr);
      margin-top: 10px;
    }
    .nps-box {
      border: 1px solid var(--line);
      border-radius: 8px;
      padding: 8px;
      text-align: center;
    }
    .nps-box strong {
      display: block;
      font-size: 20px;
      margin-top: 4px;
    }
    .help-text {
      color: var(--muted);
      font-size: 12px;
      margin-top: 4px;
    }
    .avg-mode-grid {
      display: grid;
      gap: 10px;
      margin-top: 8px;
    }
    .avg-mode-item {
      align-items: center;
      display: grid;
      gap: 8px;
      grid-template-columns: 130px 1fr 52px;
    }
    .avg-mode-label {
      color: var(--text);
      font-size: 13px;
      font-weight: 600;
    }
    .login {
      margin: 70px auto 0;
      max-width: 440px;
      text-align: left;
    }
    .login form {
      display: grid;
      gap: 10px;
      margin-top: 14px;
    }
    .pager {
      align-items: center;
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: space-between;
      margin-top: 12px;
    }
    .pager-nav {
      display: flex;
      gap: 8px;
    }
    .pager a {
      border: 1px solid #c8d3e0;
      border-radius: 8px;
      color: var(--text);
      padding: 6px 10px;
      text-decoration: none;
    }
    .assessment-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin: 10px 0 14px;
    }
    .assessment-tab {
      align-items: flex-start;
      background: #ffffff;
      border: 2px solid #d8e4f3;
      border-radius: 14px;
      color: var(--text);
      display: flex;
      gap: 10px;
      min-width: 210px;
      padding: 10px 14px;
      text-decoration: none;
      transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .assessment-tab:hover {
      box-shadow: 0 6px 14px rgba(15, 23, 42, 0.08);
      transform: translateY(-1px);
    }
    .assessment-tab i {
      font-size: 19px;
      margin-top: 1px;
    }
    .assessment-tab-content {
      display: grid;
      gap: 2px;
      line-height: 1.2;
    }
    .assessment-tab-title {
      font-size: 14px;
      font-weight: 700;
    }
    .assessment-tab-subtitle {
      color: var(--muted);
      font-size: 12px;
    }
    .assessment-tab.active {
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
      color: #fff;
      transform: translateY(-1px);
    }
    .assessment-tab.active .assessment-tab-subtitle {
      color: rgba(255, 255, 255, 0.85);
    }
    .assessment-tab-all {
      border-color: #9ca3af;
      color: #374151;
    }
    .assessment-tab-all.active {
      background: linear-gradient(135deg, #4b5563, #1f2937);
      border-color: #1f2937;
    }
    .assessment-tab-organization {
      border-color: #7db3f0;
      color: #1d4f8f;
    }
    .assessment-tab-organization.active {
      background: linear-gradient(135deg, #2a74d6, #1e4e9f);
      border-color: #1e4e9f;
    }
    .assessment-tab-personal {
      border-color: #7fd8b8;
      color: #0f7350;
    }
    .assessment-tab-personal.active {
      background: linear-gradient(135deg, #0f9d6a, #0b7c53);
      border-color: #0b7c53;
    }
    .assessment-tab-training-evaluation {
      border-color: #f6b06d;
      color: #9b4f0a;
    }
    .assessment-tab-training-evaluation.active {
      background: linear-gradient(135deg, #f28c2f, #d96f11);
      border-color: #d96f11;
    }
  </style>
</head>
<body>
<div class="wrap">
  <?php if (!$isAuthed): ?>
    <section class="card login">
      <h1>Dashboard Hasil Assessment</h1>
      <p class="muted">Masukkan password untuk melihat data asesmen yang masuk.</p>
      <form method="post" action="assessment-dashboard.php" autocomplete="off">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" value="<?php echo dashboard_h($csrfToken); ?>">
        <div>
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>
        </div>
        <button type="submit">Masuk</button>
      </form>
      <?php if ($passwordError !== ''): ?>
        <p class="error"><?php echo dashboard_h($passwordError); ?></p>
      <?php elseif ($infoMessage !== ''): ?>
        <p class="ok"><?php echo dashboard_h($infoMessage); ?></p>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <section class="card">
      <div class="toolbar">
        <div>
          <h1>Dashboard Hasil Assessment</h1>
          <p class="muted">Data terbaru dari koleksi Firestore assessment submissions.</p>
          <div class="assessment-tabs" role="tablist" aria-label="Jenis asesmen">
            <?php foreach ($tabOptions as $tabKey => $tabLabel): ?>
              <?php $isActiveTab = $activeTab === $tabKey; ?>
              <a class="assessment-tab assessment-tab-<?php echo dashboard_h($tabKey); ?><?php echo $isActiveTab ? ' active' : ''; ?>" href="assessment-dashboard.php?<?php echo dashboard_h(dashboard_query(array_merge($baseParams, ['tab' => $tabKey, 'page' => 1]))); ?>" role="tab" aria-selected="<?php echo $isActiveTab ? 'true' : 'false'; ?>">
                <i class="bi <?php echo dashboard_h($tabMeta[$tabKey]['icon']); ?>" aria-hidden="true"></i>
                <span class="assessment-tab-content">
                  <span class="assessment-tab-title"><?php echo dashboard_h($tabLabel); ?></span>
                  <span class="assessment-tab-subtitle"><?php echo dashboard_h($tabMeta[$tabKey]['subtitle']); ?></span>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <div style="display:flex; gap:8px; align-items:end;">
          <form method="get" action="assessment-dashboard.php">
            <input type="hidden" name="tab" value="<?php echo dashboard_h($activeTab); ?>">
            <div>
              <label for="limit">Limit</label>
              <select id="limit" name="limit">
                <?php foreach ([25, 50, 100, 150, 200] as $option): ?>
                  <option value="<?php echo $option; ?>" <?php echo $limit === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="q">Cari</label>
              <input id="q" name="q" type="text" value="<?php echo dashboard_h($searchTerm); ?>" placeholder="nama/email/perusahaan">
            </div>
            <div>
              <label for="date_from">Dari</label>
              <input id="date_from" name="date_from" type="date" value="<?php echo dashboard_h($dateFrom); ?>">
            </div>
            <div>
              <label for="date_to">Sampai</label>
              <input id="date_to" name="date_to" type="date" value="<?php echo dashboard_h($dateTo); ?>">
            </div>
            <div>
              <label for="sort">Sort</label>
              <select id="sort" name="sort">
                <option value="createdAt" <?php echo $sortBy === 'createdAt' ? 'selected' : ''; ?>>Waktu</option>
                <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Nama</option>
                <option value="company" <?php echo $sortBy === 'company' ? 'selected' : ''; ?>>Perusahaan</option>
                <option value="overallScore" <?php echo $sortBy === 'overallScore' ? 'selected' : ''; ?>>Skor</option>
                <option value="overallLevel" <?php echo $sortBy === 'overallLevel' ? 'selected' : ''; ?>>Level</option>
              </select>
            </div>
            <div>
              <label for="order">Urutan</label>
              <select id="order" name="order">
                <option value="desc" <?php echo $sortOrder === 'desc' ? 'selected' : ''; ?>>Menurun</option>
                <option value="asc" <?php echo $sortOrder === 'asc' ? 'selected' : ''; ?>>Menaik</option>
              </select>
            </div>
            <div>
              <label for="per_page">Per halaman</label>
              <select id="per_page" name="per_page">
                <?php foreach ([10, 20, 30, 50, 100] as $option): ?>
                  <option value="<?php echo $option; ?>" <?php echo $perPage === $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit">Terapkan</button>
            <a href="assessment-dashboard.php?reset=1" style="text-decoration:none;"><button type="button" class="secondary">Reset Filter</button></a>
          </form>
          <a href="assessment-dashboard.php?<?php echo dashboard_h(dashboard_query(array_merge($baseParams, ['page' => $page, 'export' => 'csv']))); ?>" style="text-decoration:none;"><button type="button" class="secondary">Export CSV</button></a>
          <a href="assessment-dashboard.php?<?php echo dashboard_h(dashboard_query(array_merge($baseParams, ['page' => $page, 'export' => 'csv-detailed']))); ?>" style="text-decoration:none;"><button type="button" class="secondary">Export CSV Detail</button></a>
          <form method="post" action="assessment-dashboard.php">
            <input type="hidden" name="action" value="logout">
            <input type="hidden" name="csrf_token" value="<?php echo dashboard_h($csrfToken); ?>">
            <button type="submit" class="secondary">Logout</button>
          </form>
        </div>
      </div>

      <div class="stats">
        <div class="stat">
          <span class="muted">Total tampil</span>
          <strong><?php echo count($items); ?></strong>
        </div>
        <div class="stat">
          <span class="muted">Rata-rata keseluruhan</span>
          <strong><?php echo $overallAveragePercent === null ? '-' : $overallAveragePercent . '%'; ?></strong>
        </div>
        <div class="stat">
          <span class="muted">Organisasi</span>
          <strong><?php echo $stats['organization']; ?></strong>
          <span class="muted"><?php echo $modeAverageScores['organization'] === null ? '-' : 'Avg ' . $modeAverageScores['organization'] . '%'; ?></span>
        </div>
        <div class="stat">
          <span class="muted">Pribadi</span>
          <strong><?php echo $stats['personal']; ?></strong>
          <span class="muted"><?php echo $modeAverageScores['personal'] === null ? '-' : 'Avg ' . $modeAverageScores['personal'] . '%'; ?></span>
        </div>
        <div class="stat">
          <span class="muted">Evaluasi Pelatihan</span>
          <strong><?php echo $stats['training-evaluation']; ?></strong>
          <span class="muted"><?php echo $modeAverageScores['training-evaluation'] === null ? '-' : 'Avg ' . $modeAverageScores['training-evaluation'] . '%'; ?></span>
        </div>
      </div>

      <div class="row">
        <section class="mini-card">
          <h3>Trend Harian (maks 14 hari)</h3>
          <?php if (empty($dailyCountsRecent)): ?>
            <p class="muted">Belum ada data trend.</p>
          <?php else: ?>
            <div class="bars">
              <?php foreach ($dailyCountsRecent as $day => $count): ?>
                <?php $width = $maxDailyCount > 0 ? (int) round(($count / $maxDailyCount) * 100) : 0; ?>
                <div class="bar-item">
                  <span><?php echo dashboard_h($day); ?></span>
                  <div class="bar-track"><div class="bar-fill" style="width: <?php echo $width; ?>%;"></div></div>
                  <strong><?php echo (int) $count; ?></strong>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="mini-card">
          <h3>Distribusi Level</h3>
          <?php if (empty($levelCounts)): ?>
            <p class="muted">Belum ada distribusi level.</p>
          <?php else: ?>
            <div class="bars">
              <?php $maxLevelCount = max($levelCounts); ?>
              <?php foreach ($levelCounts as $levelName => $count): ?>
                <?php $width = $maxLevelCount > 0 ? (int) round(($count / $maxLevelCount) * 100) : 0; ?>
                <div class="bar-item">
                  <span><?php echo dashboard_h($levelName); ?></span>
                  <div class="bar-track"><div class="bar-fill" style="width: <?php echo $width; ?>%;"></div></div>
                  <strong><?php echo (int) $count; ?></strong>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section class="mini-card">
          <h3>NPS Evaluasi Pelatihan (Q14)</h3>
          <?php if ($npsValue === null): ?>
            <p class="muted">Belum ada data NPS dari mode Evaluasi Pelatihan.</p>
          <?php else: ?>
            <p><strong style="font-size:34px;"><?php echo (int) $npsValue; ?></strong> <span class="muted">(rentang -100 s/d 100)</span></p>
            <div class="nps-grid">
              <div class="nps-box"><span class="muted">Detractor</span><strong><?php echo (int) $npsDetractors; ?></strong></div>
              <div class="nps-box"><span class="muted">Passive</span><strong><?php echo (int) $npsPassives; ?></strong></div>
              <div class="nps-box"><span class="muted">Promoter</span><strong><?php echo (int) $npsPromoters; ?></strong></div>
            </div>
            <p class="muted" style="margin-top:8px;">Total respon NPS: <?php echo (int) $npsTotal; ?></p>
          <?php endif; ?>
        </section>

        <section class="mini-card">
          <h3>Rata-rata Skor per Mode</h3>
          <div class="avg-mode-grid">
            <?php foreach (['organization', 'personal', 'training-evaluation'] as $modeKey): ?>
              <?php $avg = $modeAverageScores[$modeKey] ?? null; ?>
              <?php $rawLabel = $modeRawAverageLabels[$modeKey] ?? '-'; ?>
              <?php $tooltip = $avg === null ? 'Belum ada data.' : ('Normalisasi: ' . $avg . '%. Nilai mentah rata-rata: ' . $rawLabel . '.'); ?>
              <div class="avg-mode-item">
                <span class="avg-mode-label"><?php echo dashboard_h(dashboard_mode_label($modeKey)); ?></span>
                <div class="bar-track">
                  <div class="bar-fill <?php echo dashboard_h(dashboard_mode_bar_class($modeKey)); ?>" title="<?php echo dashboard_h($tooltip); ?>" style="width: <?php echo $avg === null ? 0 : (float) $avg; ?>%;"></div>
                </div>
                <strong title="<?php echo dashboard_h($tooltip); ?>"><?php echo $avg === null ? '-' : dashboard_h((string) $avg) . '%'; ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="help-text">Skor sudah dinormalisasi ke rentang 0-100 agar adil dibandingkan antar mode.</p>
        </section>
      </div>

      <?php if ($errorMessage !== ''): ?>
        <p class="error"><?php echo dashboard_h($errorMessage); ?></p>
      <?php elseif (empty($items)): ?>
        <p class="muted" style="margin-top:12px;">Belum ada data untuk filter ini.</p>
      <?php else: ?>
        <div style="margin-top:14px; overflow:auto;">
          <table>
            <thead>
              <tr>
                <th>Waktu</th>
                <th>Mode</th>
                <th>Nama</th>
                <th>Perusahaan</th>
                <th>Email</th>
                <th>Skor</th>
                <th>Level</th>
                <th>Detail</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($itemsPage as $item): ?>
                <tr>
                  <td><?php echo dashboard_h($item['createdAt']); ?></td>
                  <td><?php echo dashboard_h($item['assessmentType'] !== '' ? $item['assessmentType'] : $item['assessmentMode']); ?></td>
                  <td><?php echo dashboard_h($item['name']); ?></td>
                  <td><?php echo dashboard_h($item['company']); ?></td>
                  <td><?php echo dashboard_h($item['email']); ?></td>
                  <td><?php echo dashboard_h($item['overallScore']); ?></td>
                  <td><?php echo dashboard_h($item['overallLevel']); ?></td>
                  <td>
                    <div><strong>Goal:</strong> <?php echo dashboard_h($item['trainingGoal']); ?></div>
                    <div><strong>Prioritas:</strong> <?php echo dashboard_h($item['weakestPillar']); ?></div>
                    <details>
                      <summary>Lihat rekomendasi</summary>
                      <pre><?php echo dashboard_h($item['recommendation']); ?></pre>
                    </details>
                    <details>
                      <summary>Lihat skor per bagian</summary>
                      <pre><?php echo dashboard_h(dashboard_json_pretty($item['pillarScores'])); ?></pre>
                    </details>
                    <details>
                      <summary>Lihat jawaban lengkap</summary>
                      <pre><?php echo dashboard_h(dashboard_json_pretty($item['answers'])); ?></pre>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="pager">
          <div class="muted">
            Menampilkan <?php echo $totalItems > 0 ? ($offset + 1) : 0; ?> - <?php echo min($offset + $perPage, $totalItems); ?> dari <?php echo $totalItems; ?> data
          </div>
          <div class="pager-nav">
            <?php if ($page > 1): ?>
              <a href="assessment-dashboard.php?<?php echo dashboard_h(dashboard_query(array_merge($baseParams, ['page' => $page - 1]))); ?>">Sebelumnya</a>
            <?php endif; ?>
            <span class="muted">Halaman <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
              <a href="assessment-dashboard.php?<?php echo dashboard_h(dashboard_query(array_merge($baseParams, ['page' => $page + 1]))); ?>">Berikutnya</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
</body>
</html>
