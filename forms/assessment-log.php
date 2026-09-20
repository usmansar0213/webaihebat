<?php
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  fail_response('Method not allowed', 405);
}

enforce_allowed_hosts();

function assessment_log_json_response(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($payload);
  exit;
}

function assessment_parse_firestore_value(array $value) {
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
    return array_map('assessment_parse_firestore_value', $value['arrayValue']['values']);
  }

  if (isset($value['mapValue']['fields']) && is_array($value['mapValue']['fields'])) {
    return assessment_parse_firestore_fields($value['mapValue']['fields']);
  }

  return null;
}

function assessment_parse_firestore_fields(array $fields): array {
  $output = [];
  foreach ($fields as $key => $value) {
    if (!is_array($value)) {
      continue;
    }
    $output[$key] = assessment_parse_firestore_value($value);
  }

  return $output;
}

function assessment_try_decode_json_string($value) {
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

$expectedToken = env_value('ASSESSMENT_LOG_TOKEN');
if ($expectedToken === '') {
  assessment_log_json_response([
    'ok' => false,
    'message' => 'ASSESSMENT_LOG_TOKEN is not configured'
  ], 503);
}

$providedToken = clean_text($_GET['token'] ?? '', 160);
if (!hash_equals($expectedToken, $providedToken)) {
  assessment_log_json_response([
    'ok' => false,
    'message' => 'Forbidden'
  ], 403);
}

$mode = strtolower(clean_text($_GET['mode'] ?? '', 20));
if ($mode !== '' && $mode !== 'organization' && $mode !== 'personal' && $mode !== 'training-evaluation') {
  assessment_log_json_response([
    'ok' => false,
    'message' => 'Invalid mode. Use organization, personal, or training-evaluation'
  ], 400);
}

$limit = (int) clean_text($_GET['limit'] ?? '50', 4);
if ($limit < 1) {
  $limit = 50;
}
if ($limit > 200) {
  $limit = 200;
}

$projectId = gcp_project_id();
$token = gcp_access_token();
if ($projectId === '' || $token === '') {
  assessment_log_json_response([
    'ok' => false,
    'message' => 'Firestore is not available'
  ], 503);
}

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

if ($mode !== '') {
  $query['structuredQuery']['where'] = [
    'fieldFilter' => [
      'field' => ['fieldPath' => 'assessmentMode'],
      'op' => 'EQUAL',
      'value' => ['stringValue' => $mode]
    ]
  ];
}

$response = http_json_post($runQueryUrl, $query, ['Authorization: Bearer ' . $token]);
if (!$response['ok']) {
  assessment_log_json_response([
    'ok' => false,
    'message' => 'Unable to query assessment logs',
    'status' => $response['status']
  ], 502);
}

$rows = json_decode($response['body'], true);
if (!is_array($rows)) {
  assessment_log_json_response([
    'ok' => false,
    'message' => 'Invalid Firestore response'
  ], 502);
}

$items = [];
foreach ($rows as $row) {
  if (!is_array($row) || empty($row['document']['fields']) || !is_array($row['document']['fields'])) {
    continue;
  }

  $documentName = (string) ($row['document']['name'] ?? '');
  $parts = explode('/', $documentName);
  $id = end($parts);

  $fields = assessment_parse_firestore_fields($row['document']['fields']);
  if (isset($fields['pillarScores'])) {
    $fields['pillarScores'] = assessment_try_decode_json_string($fields['pillarScores']);
  }
  if (isset($fields['answers'])) {
    $fields['answers'] = assessment_try_decode_json_string($fields['answers']);
  }

  $items[] = [
    'id' => $id,
    'createdAt' => $fields['createdAt'] ?? '',
    'assessmentMode' => $fields['assessmentMode'] ?? '',
    'assessmentType' => $fields['assessmentType'] ?? '',
    'name' => $fields['name'] ?? '',
    'company' => $fields['company'] ?? '',
    'email' => $fields['email'] ?? '',
    'trainingGoal' => $fields['trainingGoal'] ?? '',
    'overallScore' => $fields['overallScore'] ?? '',
    'overallLevel' => $fields['overallLevel'] ?? '',
    'weakestPillar' => $fields['weakestPillar'] ?? '',
    'recommendation' => $fields['recommendation'] ?? '',
    'pillarScores' => $fields['pillarScores'] ?? '',
    'answers' => $fields['answers'] ?? ''
  ];
}

assessment_log_json_response([
  'ok' => true,
  'mode' => $mode === '' ? 'all' : $mode,
  'limit' => $limit,
  'count' => count($items),
  'items' => $items
]);
?>
