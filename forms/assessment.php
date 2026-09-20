<?php
require_once __DIR__ . '/common.php';

require_post_method();
enforce_allowed_hosts();

$name = clean_text($_POST['name'] ?? '', 120);
$company = clean_text($_POST['company'] ?? '', 180);
$email = clean_text($_POST['email'] ?? '', 180);
$assessmentMode = strtolower(clean_text($_POST['assessment_mode'] ?? '', 40));
$assessmentType = clean_text($_POST['assessment_type'] ?? 'Organisasi', 80);
$trainingGoal = clean_text($_POST['training_goal'] ?? '', 240);
$overallScore = clean_text($_POST['overall_score'] ?? '', 20);
$overallLevel = clean_text($_POST['overall_level'] ?? '', 120);
$weakestPillar = clean_text($_POST['weakest_pillar'] ?? '', 120);
$pillarScores = clean_text($_POST['pillar_scores'] ?? '', 5000);
$answers = clean_text($_POST['answers'] ?? '', 12000);
$recommendation = clean_text($_POST['recommendation'] ?? '', 1000);

if ($assessmentMode !== 'organization' && $assessmentMode !== 'personal' && $assessmentMode !== 'training-evaluation') {
  $normalizedType = strtolower($assessmentType);
  if ($normalizedType === 'pribadi') {
    $assessmentMode = 'personal';
  } elseif ($normalizedType === 'evaluasi pelatihan') {
    $assessmentMode = 'training-evaluation';
  } else {
    $assessmentMode = 'organization';
  }
}

if ($assessmentType === '') {
  if ($assessmentMode === 'personal') {
    $assessmentType = 'Pribadi';
  } elseif ($assessmentMode === 'training-evaluation') {
    $assessmentType = 'Evaluasi Pelatihan';
  } else {
    $assessmentType = 'Organisasi';
  }
}

if ($assessmentMode === 'personal' && strtolower($assessmentType) !== 'pribadi') {
  $assessmentType = 'Pribadi';
}

if ($assessmentMode === 'organization' && strtolower($assessmentType) !== 'organisasi') {
  $assessmentType = 'Organisasi';
}

if ($assessmentMode === 'training-evaluation' && strtolower($assessmentType) !== 'evaluasi pelatihan') {
  $assessmentType = 'Evaluasi Pelatihan';
}

$companyRequired = $assessmentMode === 'organization';
$trainingGoalRequired = $assessmentMode !== 'training-evaluation';

if ($name === '' || $email === '' || ($trainingGoalRequired && $trainingGoal === '') || $overallScore === '' || $overallLevel === '' || ($companyRequired && $company === '')) {
  fail_response('Please complete the assessment form');
}

if (!valid_email($email)) {
  fail_response('Invalid email address');
}

$to = env_value('ASSESSMENT_RECEIVING_EMAIL', env_value('CONTACT_RECEIVING_EMAIL', 'admin@aihebat.com'));
if (!valid_email($to)) {
  fail_response('Assessment receiving email is not configured', 500);
}

$plain = "New AI Readiness Assessment submission\n\n"
  . "Assessment Mode: {$assessmentMode}\n"
  . "Assessment Type: {$assessmentType}\n"
  . "Name: {$name}\n"
  . "Company: {$company}\n"
  . "Email: {$email}\n"
  . "Training Goal: {$trainingGoal}\n"
  . "Overall Score: {$overallScore}\n"
  . "Overall Level: {$overallLevel}\n"
  . "Weakest Pillar: {$weakestPillar}\n\n"
  . "Recommendation:\n{$recommendation}\n\n"
  . "Pillar Scores:\n{$pillarScores}\n\n"
  . "Answers:\n{$answers}\n";

$html = '<h3>New AI Readiness Assessment submission</h3>'
  . '<p><strong>Assessment Mode:</strong> ' . htmlspecialchars($assessmentMode, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Assessment Type:</strong> ' . htmlspecialchars($assessmentType, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Company:</strong> ' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Training Goal:</strong> ' . htmlspecialchars($trainingGoal, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Overall Score:</strong> ' . htmlspecialchars($overallScore, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Overall Level:</strong> ' . htmlspecialchars($overallLevel, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Weakest Pillar:</strong> ' . htmlspecialchars($weakestPillar, ENT_QUOTES, 'UTF-8') . '</p>'
  . '<p><strong>Recommendation:</strong><br>' . nl2br(htmlspecialchars($recommendation, ENT_QUOTES, 'UTF-8')) . '</p>'
  . '<p><strong>Pillar Scores:</strong><br><pre>' . htmlspecialchars($pillarScores, ENT_QUOTES, 'UTF-8') . '</pre></p>'
  . '<p><strong>Answers:</strong><br><pre>' . htmlspecialchars($answers, ENT_QUOTES, 'UTF-8') . '</pre></p>';

$collection = env_value('FIRESTORE_COLLECTION_ASSESSMENT', 'assessment_submissions');
$logged = log_to_firestore($collection, [
  'name' => $name,
  'company' => $company,
  'email' => $email,
  'assessmentMode' => $assessmentMode,
  'assessmentType' => $assessmentType,
  'trainingGoal' => $trainingGoal,
  'overallScore' => $overallScore,
  'overallLevel' => $overallLevel,
  'weakestPillar' => $weakestPillar,
  'pillarScores' => $pillarScores,
  'answers' => $answers,
  'recommendation' => $recommendation,
  'ip' => clean_text($_SERVER['REMOTE_ADDR'] ?? '', 64),
  'userAgent' => clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 300),
  'createdAt' => gmdate('c')
]);

$subjectEntity = $company !== '' ? $company : $name;
$sent = send_outbound_email($to, '[AIHebat Assessment ' . $assessmentType . '] ' . $subjectEntity . ' - ' . $overallLevel, $plain, $html, $email, $name);
if (!$logged && !$sent) {
  fail_response('Unable to save assessment right now. Please try again later.', 502);
}

ok_response();
?>
