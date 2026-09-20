<?php

function env_value(string $key, string $default = ''): string {
  $value = getenv($key);
  if ($value === false || $value === '') {
    return $default;
  }
  return $value;
}

function bool_env(string $key, bool $default = false): bool {
  $value = strtolower(env_value($key, $default ? 'true' : 'false'));
  return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function fail_response(string $message, int $status = 400): void {
  http_response_code($status);
  header('Content-Type: text/plain; charset=utf-8');
  echo $message;
  exit;
}

function ok_response(): void {
  http_response_code(200);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'OK';
  exit;
}

function require_post_method(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_response('Method not allowed', 405);
  }
}

function enforce_allowed_hosts(): void {
  $configured = env_value('ALLOWED_HOSTS', 'aihebat.com,www.aihebat.com,localhost,127.0.0.1');
  $allowed = array_map('trim', explode(',', $configured));
  $referer = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : '';

  if ($referer === '' || $referer === null) {
    return;
  }

  if (!in_array($referer, $allowed, true)) {
    fail_response('Forbidden', 403);
  }
}

function clean_text(?string $value, int $maxLength = 5000): string {
  $value = trim((string) $value);
  if ($maxLength > 0 && strlen($value) > $maxLength) {
    $value = substr($value, 0, $maxLength);
  }
  return $value;
}

function valid_email(string $email): bool {
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function http_json_post(string $url, array $payload, array $headers = []): array {
  return http_json_request('POST', $url, $payload, $headers);
}

function http_json_request(string $method, string $url, ?array $payload = null, array $headers = []): array {
  $defaultHeaders = [
    'Content-Type: application/json'
  ];
  $allHeaders = array_merge($defaultHeaders, $headers);

  $options = [
    'http' => [
      'method' => strtoupper($method),
      'header' => implode("\r\n", $allHeaders),
      'ignore_errors' => true,
      'timeout' => 15
    ]
  ];

  if ($payload !== null) {
    $options['http']['content'] = json_encode($payload);
  }

  $context = stream_context_create($options);

  $result = @file_get_contents($url, false, $context);
  $statusCode = 0;

  if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $line) {
      if (preg_match('#HTTP/\S+\s+(\d{3})#', $line, $matches)) {
        $statusCode = (int) $matches[1];
        break;
      }
    }
  }

  $ok = $statusCode >= 200 && $statusCode < 300;
  return [
    'ok' => $ok,
    'status' => $statusCode,
    'body' => $result !== false ? $result : ''
  ];
}

function send_via_sendgrid(string $toEmail, string $subject, string $plainText, string $htmlText, string $replyEmail, string $replyName): bool {
  $apiKey = env_value('SENDGRID_API_KEY');
  if ($apiKey === '') {
    return false;
  }

  $senderEmail = env_value('SENDER_EMAIL', 'no-reply@aihebat.com');
  $senderName = env_value('SENDER_NAME', 'AIHebat Web');

  $payload = [
    'personalizations' => [[
      'to' => [[
        'email' => $toEmail
      ]],
      'subject' => $subject
    ]],
    'from' => [
      'email' => $senderEmail,
      'name' => $senderName
    ],
    'reply_to' => [
      'email' => $replyEmail,
      'name' => $replyName !== '' ? $replyName : $replyEmail
    ],
    'content' => [
      ['type' => 'text/plain', 'value' => $plainText],
      ['type' => 'text/html', 'value' => $htmlText]
    ]
  ];

  $response = http_json_post(
    'https://api.sendgrid.com/v3/mail/send',
    $payload,
    ['Authorization: Bearer ' . $apiKey]
  );

  return $response['ok'];
}

function smtp_read_line($socket): string {
  $line = '';
  while (($chunk = fgets($socket, 515)) !== false) {
    $line .= $chunk;
    if (preg_match('/^\d{3}\s/', $chunk)) {
      break;
    }
  }
  return $line;
}

function smtp_expect($socket, array $allowedCodes, ?string &$lastLine = null): bool {
  $line = smtp_read_line($socket);
  $lastLine = $line;
  if ($line === '') {
    return false;
  }
  $code = (int) substr($line, 0, 3);
  return in_array($code, $allowedCodes, true);
}

function smtp_send_cmd($socket, string $command, array $allowedCodes, ?string &$lastLine = null): bool {
  if (fwrite($socket, $command . "\r\n") === false) {
    return false;
  }
  return smtp_expect($socket, $allowedCodes, $lastLine);
}

function smtp_send_data($socket, string $data, ?string &$lastLine = null): bool {
  $normalized = preg_replace("/\r\n|\r|\n/", "\r\n", $data);
  if ($normalized === null) {
    return false;
  }

  $lines = explode("\r\n", $normalized);
  foreach ($lines as &$line) {
    if (isset($line[0]) && $line[0] === '.') {
      $line = '.' . $line;
    }
  }
  unset($line);

  $payload = implode("\r\n", $lines) . "\r\n.\r\n";
  if (fwrite($socket, $payload) === false) {
    return false;
  }
  return smtp_expect($socket, [250], $lastLine);
}

function send_via_smtp(string $toEmail, string $subject, string $plainText, string $htmlText, string $replyEmail, string $replyName): bool {
  $host = trim(env_value('SMTP_HOST', 'smtp.gmail.com'));
  $port = (int) trim(env_value('SMTP_PORT', '587'));
  $encryption = strtolower(trim(env_value('SMTP_ENCRYPTION', 'tls')));
  $username = trim(env_value('SMTP_USERNAME', env_value('SENDER_EMAIL', '')));
  $password = trim(env_value('SMTP_PASSWORD'));
  $fromEmail = trim(env_value('SENDER_EMAIL', $username));
  $fromName = trim(env_value('SENDER_NAME', 'AIHebat Web'));

  if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
    error_log('SMTP config missing required fields');
    return false;
  }

  $target = ($encryption === 'ssl' || $encryption === 'tls-ssl') ? 'ssl://' . $host : $host;
  $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 20);
  if (!$socket) {
    error_log('SMTP connect failed: ' . $target . ':' . $port . ' errno=' . $errno . ' err=' . $errstr);
    return false;
  }

  stream_set_timeout($socket, 20);

  try {
    $lastLine = '';

    if (!smtp_expect($socket, [220], $lastLine)) {
      error_log('SMTP greeting failed: ' . trim($lastLine));
      return false;
    }

    if (!smtp_send_cmd($socket, 'EHLO ' . $host, [250], $lastLine)) {
      error_log('SMTP EHLO failed: ' . trim($lastLine));
      return false;
    }

    if ($encryption === 'tls' || $encryption === 'starttls') {
      if (!smtp_send_cmd($socket, 'STARTTLS', [220], $lastLine)) {
        error_log('SMTP STARTTLS failed: ' . trim($lastLine));
        return false;
      }
      if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        error_log('SMTP TLS negotiation failed');
        return false;
      }
      if (!smtp_send_cmd($socket, 'EHLO ' . $host, [250], $lastLine)) {
        error_log('SMTP EHLO after STARTTLS failed: ' . trim($lastLine));
        return false;
      }
    }

    if (!smtp_send_cmd($socket, 'AUTH LOGIN', [334], $lastLine)) {
      error_log('SMTP AUTH LOGIN rejected: ' . trim($lastLine));
      return false;
    }
    if (!smtp_send_cmd($socket, base64_encode($username), [334], $lastLine)) {
      error_log('SMTP AUTH username rejected: ' . trim($lastLine));
      return false;
    }
    if (!smtp_send_cmd($socket, base64_encode($password), [235], $lastLine)) {
      error_log('SMTP AUTH password rejected: ' . trim($lastLine));
      return false;
    }

    if (!smtp_send_cmd($socket, 'MAIL FROM:<' . $fromEmail . '>', [250], $lastLine)) {
      error_log('SMTP MAIL FROM rejected: ' . trim($lastLine));
      return false;
    }
    if (!smtp_send_cmd($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251], $lastLine)) {
      error_log('SMTP RCPT TO rejected: ' . trim($lastLine));
      return false;
    }
    if (!smtp_send_cmd($socket, 'DATA', [354], $lastLine)) {
      error_log('SMTP DATA rejected: ' . trim($lastLine));
      return false;
    }

    $boundary = 'b' . md5((string) microtime(true));
    $safeSubject = str_replace(["\r", "\n"], '', $subject);
    $safeFromName = str_replace(["\r", "\n"], '', $fromName);
    $safeReplyName = str_replace(["\r", "\n"], '', $replyName !== '' ? $replyName : $replyEmail);

    $headers = [
      'From: ' . $safeFromName . ' <' . $fromEmail . '>',
      'Reply-To: ' . $safeReplyName . ' <' . $replyEmail . '>',
      'To: <' . $toEmail . '>',
      'Subject: ' . $safeSubject,
      'MIME-Version: 1.0',
      'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n"
      . '--' . $boundary . "\r\n"
      . "Content-Type: text/plain; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: 8bit\r\n\r\n"
      . $plainText . "\r\n\r\n"
      . '--' . $boundary . "\r\n"
      . "Content-Type: text/html; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: 8bit\r\n\r\n"
      . $htmlText . "\r\n\r\n"
      . '--' . $boundary . "--\r\n";

    if (!smtp_send_data($socket, $message, $lastLine)) {
      error_log('SMTP message body rejected: ' . trim($lastLine));
      return false;
    }

    smtp_send_cmd($socket, 'QUIT', [221]);
    return true;
  } finally {
    fclose($socket);
  }
}

function send_outbound_email(string $toEmail, string $subject, string $plainText, string $htmlText, string $replyEmail, string $replyName): bool {
  $provider = strtolower(env_value('MAIL_PROVIDER', 'smtp'));

  if ($provider === 'sendgrid') {
    return send_via_sendgrid($toEmail, $subject, $plainText, $htmlText, $replyEmail, $replyName);
  }

  return send_via_smtp($toEmail, $subject, $plainText, $htmlText, $replyEmail, $replyName);
}

function metadata_get(string $path): string {
  $url = 'http://metadata.google.internal/computeMetadata/v1/' . ltrim($path, '/');
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => "Metadata-Flavor: Google\r\n",
      'ignore_errors' => true,
      'timeout' => 5
    ]
  ]);

  $result = @file_get_contents($url, false, $context);
  return $result === false ? '' : trim($result);
}

function gcp_project_id(): string {
  $fromEnv = env_value('GOOGLE_CLOUD_PROJECT');
  if ($fromEnv !== '') {
    return $fromEnv;
  }
  return metadata_get('project/project-id');
}

function gcp_access_token(): string {
  $url = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token';
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => "Metadata-Flavor: Google\r\n",
      'ignore_errors' => true,
      'timeout' => 5
    ]
  ]);

  $result = @file_get_contents($url, false, $context);
  if ($result === false || $result === '') {
    return '';
  }

  $json = json_decode($result, true);
  if (!is_array($json) || empty($json['access_token'])) {
    return '';
  }

  return $json['access_token'];
}

function firestore_fields(array $data): array {
  $fields = [];
  foreach ($data as $key => $value) {
    if (is_bool($value)) {
      $fields[$key] = ['booleanValue' => $value];
    } elseif (is_int($value)) {
      $fields[$key] = ['integerValue' => (string) $value];
    } elseif (is_float($value)) {
      $fields[$key] = ['doubleValue' => $value];
    } elseif ($value === null) {
      $fields[$key] = ['nullValue' => null];
    } else {
      $fields[$key] = ['stringValue' => (string) $value];
    }
  }
  return $fields;
}

function log_to_firestore(string $collection, array $payload): bool {
  if (!bool_env('ENABLE_FIRESTORE_LOG', true)) {
    return true;
  }

  $projectId = gcp_project_id();
  $token = gcp_access_token();
  if ($projectId === '' || $token === '') {
    return false;
  }

  $doc = [
    'fields' => firestore_fields($payload)
  ];

  $url = 'https://firestore.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/databases/(default)/documents/' . rawurlencode($collection);
  $response = http_json_post($url, $doc, ['Authorization: Bearer ' . $token]);
  return $response['ok'];
}
