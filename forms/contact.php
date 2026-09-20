<?php
require_once __DIR__ . '/common.php';

require_post_method();
enforce_allowed_hosts();

$name = clean_text($_POST['name'] ?? '', 120);
$email = clean_text($_POST['email'] ?? '', 180);
$subject = clean_text($_POST['subject'] ?? '', 180);
$message = clean_text($_POST['message'] ?? '', 5000);
$phone = clean_text($_POST['phone'] ?? '', 60);

if ($name === '' || $email === '' || $subject === '' || $message === '') {
  fail_response('Please fill all required fields');
}

if (!valid_email($email)) {
  fail_response('Invalid email address');
}

$to = env_value('CONTACT_RECEIVING_EMAIL', 'admin@aihebat.com');
if (!valid_email($to)) {
  fail_response('Contact receiving email is not configured', 500);
}

$plain = "New contact message from AIHebat website\n\n"
  . "Name: {$name}\n"
  . "Email: {$email}\n"
  . "Phone: {$phone}\n"
  . "Subject: {$subject}\n\n"
  . "Message:\n{$message}\n";

$html = '<h3>New contact message from AIHebat website</h3>'
  . '<p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Phone:</strong> ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Subject:</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Message:</strong><br>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>';

$sent = send_outbound_email($to, '[AIHebat Contact] ' . $subject, $plain, $html, $email, $name);
if (!$sent) {
  fail_response('Unable to send message right now. Please try again later.', 502);
}

$collection = env_value('FIRESTORE_COLLECTION_CONTACT', 'contact_submissions');
log_to_firestore($collection, [
  'name' => $name,
  'email' => $email,
  'phone' => $phone,
  'subject' => $subject,
  'message' => $message,
  'ip' => clean_text($_SERVER['REMOTE_ADDR'] ?? '', 64),
  'userAgent' => clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 300),
  'createdAt' => gmdate('c')
]);

ok_response();
?>
