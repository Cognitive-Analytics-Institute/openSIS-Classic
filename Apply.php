<?php
#**************************************************************************
#  Roots is a free student information system for public and non-public
#  schools, based on openSIS from Open Solutions for Education, Inc.
#
#  This program is released under the terms of the GNU General Public License as
#  published by the Free Software Foundation, version 2 of the License.
#  See license.txt.
#
#  This program is distributed in the hope that it will be useful,
#  but WITHOUT ANY WARRANTY; without even the implied warranty of
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#  GNU General Public License for more details.
#
#***************************************************************************************
// Public, unauthenticated application form. This deliberately does NOT use
// Warehouse.php/ConfigInc.php's normal bootstrap: ConfigInc.php pulls in
// UpgradeInc.php, which unconditionally includes RedirectRootInc.php --
// the same file DatabaseInc.php includes directly too -- which redirects
// any request without a logged-in session to index.php. That's exactly
// the auth wall this page needs to sit outside of, so it connects to the
// database directly instead of going through that chain.
error_reporting(0);
session_start();

include 'functions/ParamLibFnc.php';
include 'functions/PragRepFnc.php';
include 'Data.php';
$mysqli = new mysqli($DatabaseServer, $DatabaseUsername, $DatabasePassword, $DatabaseName, (int)$DatabasePort);

$errors = array();
$submitted_ok = isset($_GET['submitted']);

$FILE_FIELDS = array(
    'transcript' => array('label' => 'Educational Transcripts', 'required' => false),
    'chief_letter' => array('label' => "Letter of Support from Nation's Chief", 'required' => true),
    'resume' => array('label' => 'Resume', 'required' => true),
    'reference_letter_1' => array('label' => 'Reference Letter 1', 'required' => true),
    'reference_letter_2' => array('label' => 'Reference Letter 2', 'required' => true),
    'cover_letter' => array('label' => 'Covering Letter / Learner Statement', 'required' => true),
    'additional' => array('label' => 'Additional Supporting Materials', 'required' => false),
);

$ALLOWED_MIME = array('application/pdf', 'image/jpeg', 'image/png');
$MAX_FILE_BYTES = 25 * 1024 * 1024;

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Generate a fresh CAPTCHA challenge whenever we're about to show the form.
function new_captcha() {
    $a = rand(2, 9);
    $b = rand(2, 9);
    $_SESSION['apply_captcha'] = $a + $b;
    return array($a, $b);
}

$old = $_SESSION['apply_old'] ?? array();
unset($_SESSION['apply_old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $captcha_answer = optional_param('captcha_answer', '', PARAM_INT);
    if (!isset($_SESSION['apply_captcha']) || (int)$captcha_answer !== (int)$_SESSION['apply_captcha']) {
        $errors[] = 'The verification answer was incorrect. Please try again.';
    }

    $full_name = trim(optional_param('full_name', '', PARAM_NOTAGS));
    $first_name = trim(optional_param('first_name', '', PARAM_NOTAGS));
    $middle_name = trim(optional_param('middle_name', '', PARAM_NOTAGS));
    $last_name = trim(optional_param('last_name', '', PARAM_NOTAGS));
    $date_of_birth = trim(optional_param('date_of_birth', '', PARAM_NOTAGS));
    $gender_identity = trim(optional_param('gender_identity', '', PARAM_NOTAGS));
    $email = trim(optional_param('email', '', PARAM_NOTAGS));
    $phone = trim(optional_param('phone', '', PARAM_NOTAGS));
    $mailing_address = trim(optional_param('mailing_address', '', PARAM_NOTAGS));
    $fnhda_member = optional_param('fnhda_member', 'N', PARAM_ALPHA) === 'Y' ? 'Y' : 'N';
    $bc_fn_health_director = optional_param('bc_fn_health_director', 'N', PARAM_ALPHA) === 'Y' ? 'Y' : 'N';
    $nation_name = trim(optional_param('nation_name', '', PARAM_NOTAGS));
    $prior_postsecondary = optional_param('prior_postsecondary', 'N', PARAM_ALPHA) === 'Y' ? 'Y' : 'N';
    $declaration_agreed = optional_param('declaration_agreed', 'N', PARAM_ALPHA) === 'Y' ? 'Y' : 'N';
    $signature_name = trim(optional_param('signature_name', '', PARAM_NOTAGS));
    $signature_date = trim(optional_param('signature_date', '', PARAM_NOTAGS));

    if ($full_name === '') $errors[] = 'Full name is required.';
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '') $errors[] = 'Last name is required.';
    if ($date_of_birth === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) $errors[] = 'A valid date of birth is required.';
    if (!in_array($gender_identity, array('Female', 'Male', 'Non-Binary'), true)) $errors[] = 'Gender identity is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if ($phone === '') $errors[] = 'Telephone number is required.';
    if ($mailing_address === '') $errors[] = 'Mailing address is required.';
    if ($signature_name === '') $errors[] = 'Signature (typed name) is required.';
    if ($signature_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $signature_date)) $errors[] = 'Signature date is required.';
    if ($declaration_agreed !== 'Y') $errors[] = 'You must agree to the declaration to submit this application.';

    $uploaded_files = array();
    foreach ($FILE_FIELDS as $field => $meta) {
        $has_file = isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE;
        if (!$has_file) {
            if ($meta['required']) {
                $errors[] = $meta['label'] . ' is required.';
            }
            continue;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $meta['label'] . ' failed to upload. Please try again.';
            continue;
        }
        if ($_FILES[$field]['size'] > $MAX_FILE_BYTES) {
            $errors[] = $meta['label'] . ' is too large (25MB max).';
            continue;
        }
        $mime = mime_content_type($_FILES[$field]['tmp_name']);
        if (!in_array($mime, $ALLOWED_MIME, true)) {
            $errors[] = $meta['label'] . ' must be a PDF, JPG, or PNG file.';
            continue;
        }
        $uploaded_files[$field] = array(
            'name' => basename($_FILES[$field]['name']),
            'type' => $mime,
            'size' => $_FILES[$field]['size'],
            'content' => file_get_contents($_FILES[$field]['tmp_name']),
        );
    }

    if (empty($errors)) {
        $stmt = $mysqli->prepare('INSERT INTO applications (
                status, full_name, first_name, middle_name, last_name, date_of_birth, gender_identity,
                email, phone, mailing_address, fnhda_member, bc_fn_health_director, nation_name,
                prior_postsecondary, declaration_agreed, signature_name, signature_date
            ) VALUES (
                \'submitted\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )');
        $stmt->bind_param('ssssssssssssssss',
            $full_name, $first_name, $middle_name, $last_name, $date_of_birth, $gender_identity,
            $email, $phone, $mailing_address, $fnhda_member, $bc_fn_health_director, $nation_name,
            $prior_postsecondary, $declaration_agreed, $signature_name, $signature_date);
        $stmt->execute();
        $application_id = $mysqli->insert_id;
        $stmt->close();

        foreach ($uploaded_files as $field => $file) {
            $download_id = bin2hex(random_bytes(16));
            $file_info = 'application:' . $field;
            $empty_content = '';
            $file_stmt = $mysqli->prepare('INSERT INTO user_file_upload (USER_ID, PROFILE_ID, SCHOOL_ID, SYEAR, DOWNLOAD_ID, NAME, SIZE, TYPE, CONTENT, FILE_INFO)
                VALUES (?, 99, 0, 0, ?, ?, ?, ?, ?, ?)');
            $file_stmt->bind_param('issisbs', $application_id, $download_id, $file['name'], $file['size'], $file['type'], $empty_content, $file_info);
            $file_stmt->send_long_data(5, $file['content']);
            $file_stmt->execute();
            $file_stmt->close();
        }

        session_unset();
        header('Location: Apply.php?submitted=1');
        exit;
    } else {
        // Preserve entered values across the re-render (files are not preserved -- browsers can't refill file inputs anyway).
        $_SESSION['apply_old'] = compact('full_name', 'first_name', 'middle_name', 'last_name', 'date_of_birth',
            'gender_identity', 'email', 'phone', 'mailing_address', 'fnhda_member', 'bc_fn_health_director',
            'nation_name', 'prior_postsecondary', 'declaration_agreed', 'signature_name', 'signature_date');
        $old = $_SESSION['apply_old'];
    }
}

list($captcha_a, $captcha_b) = new_captcha();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Apply - Roots</title>
<link href="assets/css/icons/icomoon/styles.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" type="text/css" href="assets/css/login.css">
<style>
    body.apply-body { background: #1c1917; padding: 48px 16px; }
    .apply-wrapper { max-width: 760px; margin: 0 auto; }
    .apply-panel { background: #fff; border-radius: 6px; box-shadow: 0 2px 12px rgba(0,0,0,0.25); padding: 32px 36px 40px; }
    .apply-panel h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; color: #1c1917; }
    .apply-panel .subtitle { color: #78716c; margin-bottom: 28px; }
    .apply-panel h2 { font-size: 16px; font-weight: 700; margin: 28px 0 14px; padding-bottom: 8px; border-bottom: 1px solid #e7e5e4; color: #1c1917; }
    .apply-panel label { font-weight: 600; color: #1c1917; }
    .apply-panel .help-text { color: #78716c; font-size: 12px; margin-top: -8px; margin-bottom: 12px; }
    .apply-alert-errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 4px; padding: 14px 18px; margin-bottom: 20px; }
    .apply-alert-errors ul { margin: 6px 0 0; padding-left: 20px; }
    .apply-submit-btn { background: #ea580c; border-color: #ea580c; }
    .apply-submit-btn:hover, .apply-submit-btn:focus { background: #c2410c; border-color: #c2410c; }
    .apply-success { text-align: center; padding: 40px 20px; }
    .apply-success h1 { color: #15803d; }
</style>
</head>
<body class="apply-body">
<div class="apply-wrapper">
    <div class="apply-panel">
<?php if ($submitted_ok): ?>
        <div class="apply-success">
            <h1>Application Submitted</h1>
            <p>Thank you for applying to the First Nations Health Director Certificate Program. Our team will review your application and be in touch.</p>
        </div>
<?php else: ?>
        <h1>First Nations Health Director Application</h1>
        <div class="subtitle">Centre for Indigenous Health Leadership</div>

<?php if (!empty($errors)): ?>
        <div class="apply-alert-errors">
            <strong>Please fix the following before submitting:</strong>
            <ul>
<?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
<?php endforeach; ?>
            </ul>
        </div>
<?php endif; ?>

        <form method="post" enctype="multipart/form-data" novalidate>

            <h2>Applicant Identity</h2>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= h($old['full_name'] ?? '') ?>" required>
            </div>
            <div class="row">
                <div class="col-sm-4"><div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-control" value="<?= h($old['first_name'] ?? '') ?>" required></div></div>
                <div class="col-sm-4"><div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?= h($old['middle_name'] ?? '') ?>"></div></div>
                <div class="col-sm-4"><div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-control" value="<?= h($old['last_name'] ?? '') ?>" required></div></div>
            </div>
            <div class="row">
                <div class="col-sm-6"><div class="form-group"><label>Date of Birth *</label><input type="date" name="date_of_birth" class="form-control" value="<?= h($old['date_of_birth'] ?? '') ?>" required></div></div>
                <div class="col-sm-6"><div class="form-group"><label>Gender Identity *</label>
                    <select name="gender_identity" class="form-control" required>
                        <option value="">-- Select --</option>
<?php foreach (array('Female', 'Male', 'Non-Binary') as $g): ?>
                        <option value="<?= h($g) ?>" <?= (($old['gender_identity'] ?? '') === $g) ? 'selected' : '' ?>><?= h($g) ?></option>
<?php endforeach; ?>
                    </select>
                </div></div>
            </div>
            <div class="row">
                <div class="col-sm-6"><div class="form-group"><label>Email Address *</label><input type="email" name="email" class="form-control" value="<?= h($old['email'] ?? '') ?>" required></div></div>
                <div class="col-sm-6"><div class="form-group"><label>Telephone Number *</label><input type="text" name="phone" class="form-control" value="<?= h($old['phone'] ?? '') ?>" required></div></div>
            </div>
            <div class="form-group">
                <label>Mailing Address *</label>
                <textarea name="mailing_address" class="form-control" rows="2" required><?= h($old['mailing_address'] ?? '') ?></textarea>
            </div>

            <h2>Eligibility &amp; Role Information</h2>
            <div class="form-group">
                <label>Are you a current FNHDA member in good standing? *</label><br>
                <label class="radio-inline"><input type="radio" name="fnhda_member" value="Y" <?= (($old['fnhda_member'] ?? '') === 'Y') ? 'checked' : '' ?> required> Yes</label>
                <label class="radio-inline"><input type="radio" name="fnhda_member" value="N" <?= (($old['fnhda_member'] ?? 'N') === 'N') ? 'checked' : '' ?>> No</label>
            </div>
            <div class="form-group">
                <label>Are you a British Columbia First Nations Health Director? *</label><br>
                <label class="radio-inline"><input type="radio" name="bc_fn_health_director" value="Y" <?= (($old['bc_fn_health_director'] ?? '') === 'Y') ? 'checked' : '' ?> required> Yes</label>
                <label class="radio-inline"><input type="radio" name="bc_fn_health_director" value="N" <?= (($old['bc_fn_health_director'] ?? 'N') === 'N') ? 'checked' : '' ?>> No</label>
            </div>
            <div class="form-group">
                <label>Nation Name</label>
                <input type="text" name="nation_name" class="form-control" value="<?= h($old['nation_name'] ?? '') ?>">
            </div>

            <h2>Education</h2>
            <div class="form-group">
                <label>Have you completed previous post-secondary programs? *</label><br>
                <label class="radio-inline"><input type="radio" name="prior_postsecondary" value="Y" <?= (($old['prior_postsecondary'] ?? '') === 'Y') ? 'checked' : '' ?> required> Yes</label>
                <label class="radio-inline"><input type="radio" name="prior_postsecondary" value="N" <?= (($old['prior_postsecondary'] ?? 'N') === 'N') ? 'checked' : '' ?>> No</label>
            </div>

            <h2>Supporting Documentation</h2>
<?php foreach ($FILE_FIELDS as $field => $meta): ?>
            <div class="form-group">
                <label><?= h($meta['label']) ?><?= $meta['required'] ? ' *' : '' ?></label>
                <input type="file" name="<?= h($field) ?>" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?= $meta['required'] ? 'required' : '' ?>>
            </div>
<?php endforeach; ?>
            <div class="help-text">Accepted formats: PDF, JPG, PNG. 25MB max per file.</div>

            <h2>Legal Consent</h2>
            <div class="form-group">
                <label class="checkbox-inline"><input type="checkbox" name="declaration_agreed" value="Y" <?= (($old['declaration_agreed'] ?? '') === 'Y') ? 'checked' : '' ?> required> I confirm the information provided in this application is accurate and complete, and I agree to the program's terms. *</label>
            </div>
            <div class="row">
                <div class="col-sm-6"><div class="form-group"><label>Signature (type your full name) *</label><input type="text" name="signature_name" class="form-control" value="<?= h($old['signature_name'] ?? '') ?>" required></div></div>
                <div class="col-sm-6"><div class="form-group"><label>Date Signed *</label><input type="date" name="signature_date" class="form-control" value="<?= h($old['signature_date'] ?? '') ?>" required></div></div>
            </div>

            <h2>Verification</h2>
            <div class="form-group">
                <label>What is <?= (int)$captcha_a ?> + <?= (int)$captcha_b ?>? *</label>
                <input type="text" name="captcha_answer" class="form-control" style="max-width: 120px;" required autocomplete="off">
            </div>

            <button type="submit" class="btn btn-lg btn-block apply-submit-btn" style="color: #fff; margin-top: 12px;">Submit Application</button>
        </form>
<?php endif; ?>
    </div>
</div>
</body>
</html>
