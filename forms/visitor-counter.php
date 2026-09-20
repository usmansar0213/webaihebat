<?php
require_once __DIR__ . '/common.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
  fail_response('Method not allowed', 405);
}

enforce_allowed_hosts();

function visitor_json_response(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload);
  exit;
}

function visitor_firestore_document_url(string $projectId, string $collection, string $documentId): string {
  return 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId)
    . '/databases/(default)/documents/' . rawurlencode($collection) . '/' . rawurlencode($documentId);
}

function visitor_parse_count(array $document, int $fallback): int {
  $fields = $document['fields'] ?? [];
  $count = $fields['count']['integerValue'] ?? $fields['count']['doubleValue'] ?? null;
  if ($count === null) {
    return $fallback;
  }
  return max(0, (int) $count);
}

function visitor_parse_commit_count(array $commit, int $fallback): int {
  $writeResults = $commit['writeResults'] ?? [];
  foreach ($writeResults as $writeResult) {
    $transformResults = $writeResult['transformResults'] ?? [];
    foreach ($transformResults as $transformResult) {
      $count = $transformResult['integerValue'] ?? $transformResult['doubleValue'] ?? null;
      if ($count !== null) {
        return max(0, (int) $count);
      }
    }
  }
  return $fallback;
}

function visitor_increment_counter(string $projectId, string $collection, string $documentId, array $authHeader): array {
  $documentName = 'projects/' . $projectId . '/databases/(default)/documents/' . $collection . '/' . $documentId;
  $commitUrl = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/databases/(default)/documents:commit';

  return http_json_request('POST', $commitUrl, [
    'writes' => [[
      'update' => [
        'name' => $documentName,
        'fields' => [
          'updatedAt' => ['stringValue' => gmdate('c')]
        ]
      ],
      'updateMask' => [
        'fieldPaths' => ['updatedAt']
      ],
      'currentDocument' => [
        'exists' => true
      ],
      'updateTransforms' => [[
        'fieldPath' => 'count',
        'increment' => ['integerValue' => '1']
      ]]
    ]]
  ], $authHeader);
}

$startCount = (int) env_value('VISITOR_COUNTER_START', '999');
if ($startCount < 1) {
  $startCount = 999;
}

$collection = env_value('FIRESTORE_COLLECTION_VISITOR', 'visitor_counters');
$documentId = env_value('VISITOR_COUNTER_DOCUMENT', 'site_total');
$projectId = gcp_project_id();
$token = gcp_access_token();

if ($projectId === '' || $token === '') {
  visitor_json_response([
    'ok' => false,
    'count' => $startCount,
    'source' => 'fallback',
    'message' => 'Firestore is not available; using local fallback'
  ], 200);
}

$authHeader = ['Authorization: Bearer ' . $token];
$docUrl = visitor_firestore_document_url($projectId, $collection, $documentId);
$commitResponse = visitor_increment_counter($projectId, $collection, $documentId, $authHeader);

if (!$commitResponse['ok'] && in_array($commitResponse['status'], [404, 409, 412], true)) {
  $createResponse = http_json_request('PATCH', $docUrl, [
    'fields' => [
      'count' => ['integerValue' => (string) ($startCount - 1)],
      'createdAt' => ['stringValue' => gmdate('c')],
      'updatedAt' => ['stringValue' => gmdate('c')]
    ]
  ], $authHeader);

  if (!$createResponse['ok'] && $createResponse['status'] !== 409) {
    visitor_json_response([
      'ok' => false,
      'count' => $startCount,
      'source' => 'fallback',
      'message' => 'Unable to initialize visitor counter; using local fallback'
    ], 200);
  }

  $commitResponse = visitor_increment_counter($projectId, $collection, $documentId, $authHeader);
}

if (!$commitResponse['ok']) {
  visitor_json_response([
    'ok' => false,
    'count' => $startCount,
    'source' => 'fallback',
    'message' => 'Unable to update visitor counter; using local fallback'
  ], 200);
}

$commitDocument = json_decode($commitResponse['body'], true);
if (!is_array($commitDocument)) {
  visitor_json_response([
    'ok' => false,
    'count' => $startCount,
    'source' => 'fallback',
    'message' => 'Invalid visitor counter response; using local fallback'
  ], 200);
}

visitor_json_response([
  'ok' => true,
  'count' => visitor_parse_commit_count($commitDocument, $startCount),
  'source' => 'firestore'
]);
?>
