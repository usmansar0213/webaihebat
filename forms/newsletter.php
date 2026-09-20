<?php
require_once __DIR__ . '/common.php';

require_post_method();
enforce_allowed_hosts();

$email = clean_text($_POST['email'] ?? '', 180);
if ($email === '' || !valid_email($email)) {
  fail_response('Invalid email address');
}

$to = env_value('NEWSLETTER_RECEIVING_EMAIL', env_value('CONTACT_RECEIVING_EMAIL', 'admin@aihebat.com'));
if (!valid_email($to)) {
  fail_response('Newsletter receiving email is not configured', 500);
}

$plain = "New newsletter subscription from AIHebat website\n\n"
  . "Email: {$email}\n"
  . "Time: " . gmdate('c') . "\n";

$html = '<h3>New newsletter subscription</h3>'
  . '<p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Time:</strong> ' . gmdate('c') . '</p>';

$sent = send_outbound_email($to, '[AIHebat Newsletter] New Subscription', $plain, $html, $email, $email);
if (!$sent) {
  fail_response('Unable to process subscription right now. Please try again later.', 502);
}

$collection = env_value('FIRESTORE_COLLECTION_NEWSLETTER', 'newsletter_subscriptions');
log_to_firestore($collection, [
  'email' => $email,
  'ip' => clean_text($_SERVER['REMOTE_ADDR'] ?? '', 64),
  'userAgent' => clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 300),
  'createdAt' => gmdate('c')
]);

ok_response();
?>
