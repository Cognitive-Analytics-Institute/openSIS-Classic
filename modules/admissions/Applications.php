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
include('../../RedirectModulesInc.php');

$FILE_FIELDS = array(
    'transcript' => 'Educational Transcripts',
    'chief_letter' => "Letter of Support from Nation's Chief",
    'resume' => 'Resume',
    'reference_letter_1' => 'Reference Letter 1',
    'reference_letter_2' => 'Reference Letter 2',
    'cover_letter' => 'Covering Letter / Learner Statement',
    'additional' => 'Additional Supporting Materials',
);

$STATUS_LABELS = array(
    'submitted' => _applicationStatusSubmitted,
    'under_review' => _applicationStatusUnderReview,
    'approved' => _applicationStatusApproved,
    'rejected' => _applicationStatusRejected,
);

$id = (int)optional_param('id', 0, PARAM_INT);

if (clean_param($_REQUEST['modfunc'], PARAM_ALPHAMOD) == 'updateStatus' && AllowEdit()) {
    $app_id = (int)optional_param('id', 0, PARAM_INT);
    $new_status = optional_param('new_status', '', PARAM_ALPHA);
    $notes = optional_param('review_notes', '', PARAM_NOTAGS);
    if ($app_id && array_key_exists($new_status, $STATUS_LABELS)) {
        DBQuery('UPDATE applications SET status=\'' . addslashes($new_status) . '\', reviewed_by=' . (int)User('STAFF_ID') .
            ', reviewed_date=NOW(), review_notes=\'' . addslashes($notes) . '\' WHERE id=' . $app_id);
    }
    $id = $app_id;
}

DrawBC(_admissions . ' &gt; ' . _applications);

if ($id > 0) {

    $app_RET = DBGet(DBQuery('SELECT * FROM applications WHERE id=' . $id));
    $app = $app_RET[1];

    if (!$app) {
        echo '<div class="alert alert-danger">' . _applicationNotFound . '</div>';
    } else {

        $files_RET = DBGet(DBQuery('SELECT * FROM user_file_upload WHERE USER_ID=' . $id . ' AND PROFILE_ID=99'));
        $files_by_category = array();
        foreach ($files_RET as $f) {
            $info = $f['FILE_INFO']; // 'application:<category>'
            $category = substr($info, strlen('application:'));
            $files_by_category[$category] = $f;
        }

        echo '<div class="panel panel-default">';
        echo '<div class="panel-heading"><h6 class="panel-title">' . htmlspecialchars($app['FULL_NAME']) . '</h6></div>';
        echo '<div class="panel-body">';

        echo '<div class="row">';
        echo '<div class="col-sm-6"><p><strong>' . _status . ':</strong> ' . htmlspecialchars($STATUS_LABELS[$app['STATUS']] ?? $app['STATUS']) . '</p></div>';
        echo '<div class="col-sm-6"><p><strong>' . _submittedDate . ':</strong> ' . htmlspecialchars($app['SUBMITTED_DATE']) . '</p></div>';
        echo '</div>';

        echo '<h6 class="text-bold m-t-20">' . _applicantIdentity . '</h6>';
        echo '<div class="row">';
        echo '<div class="col-sm-4"><p><strong>' . _firstName . ':</strong> ' . htmlspecialchars($app['FIRST_NAME']) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _middleName . ':</strong> ' . htmlspecialchars($app['MIDDLE_NAME']) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _lastName . ':</strong> ' . htmlspecialchars($app['LAST_NAME']) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _dateOfBirth . ':</strong> ' . htmlspecialchars($app['DATE_OF_BIRTH']) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _genderIdentity . ':</strong> ' . htmlspecialchars($app['GENDER_IDENTITY']) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _email . ':</strong> ' . htmlspecialchars($app['EMAIL']) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _phone . ':</strong> ' . htmlspecialchars($app['PHONE']) . '</p></div>';
        echo '<div class="col-sm-8"><p><strong>' . _mailingAddress . ':</strong> ' . htmlspecialchars($app['MAILING_ADDRESS']) . '</p></div>';
        echo '</div>';

        echo '<h6 class="text-bold m-t-20">' . _eligibilityAndRole . '</h6>';
        echo '<div class="row">';
        echo '<div class="col-sm-4"><p><strong>' . _fnhdaMember . ':</strong> ' . ($app['FNHDA_MEMBER'] == 'Y' ? _yes : _no) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _bcFnHealthDirector . ':</strong> ' . ($app['BC_FN_HEALTH_DIRECTOR'] == 'Y' ? _yes : _no) . '</p></div>';
        echo '<div class="col-sm-4"><p><strong>' . _nationName . ':</strong> ' . htmlspecialchars($app['NATION_NAME']) . '</p></div>';
        echo '</div>';

        echo '<h6 class="text-bold m-t-20">' . _education . '</h6>';
        echo '<p><strong>' . _priorPostsecondary . ':</strong> ' . ($app['PRIOR_POSTSECONDARY'] == 'Y' ? _yes : _no) . '</p>';

        echo '<h6 class="text-bold m-t-20">' . _supportingDocumentation . '</h6>';
        echo '<ul class="list-unstyled">';
        foreach ($FILE_FIELDS as $cat => $label) {
            echo '<li style="margin-bottom:6px;">' . htmlspecialchars($label) . ': ';
            if (isset($files_by_category[$cat])) {
                $f = $files_by_category[$cat];
                echo '<a href="DownloadWindow.php?down_id=' . htmlspecialchars($f['DOWNLOAD_ID']) . '" target="_blank">' . htmlspecialchars($f['NAME']) . '</a>';
            } else {
                echo '<span class="text-muted">' . _notProvided . '</span>';
            }
            echo '</li>';
        }
        echo '</ul>';

        echo '<h6 class="text-bold m-t-20">' . _legalConsent . '</h6>';
        echo '<p><strong>' . _signatureName . ':</strong> ' . htmlspecialchars($app['SIGNATURE_NAME']) . ' (' . htmlspecialchars($app['SIGNATURE_DATE']) . ')</p>';

        echo '<h6 class="text-bold m-t-20">' . _review . '</h6>';
        if (AllowEdit()) {
            echo '<form method="post" action="Modules.php?modname=' . htmlspecialchars($_REQUEST['modname']) . '">';
            echo '<input type="hidden" name="modfunc" value="updateStatus">';
            echo '<input type="hidden" name="id" value="' . $id . '">';
            echo '<div class="form-group"><label>' . _status . '</label><select name="new_status" class="form-control" style="max-width:260px;">';
            foreach ($STATUS_LABELS as $val => $label) {
                echo '<option value="' . $val . '" ' . ($app['STATUS'] == $val ? 'selected' : '') . '>' . htmlspecialchars($label) . '</option>';
            }
            echo '</select></div>';
            echo '<div class="form-group"><label>' . _reviewNotes . '</label><textarea name="review_notes" class="form-control" rows="3">' . htmlspecialchars($app['REVIEW_NOTES']) . '</textarea></div>';
            echo '<button type="submit" class="btn btn-primary">' . _save . '</button>';
            echo '</form>';
            if ($app['REVIEWED_BY']) {
                echo '<p class="text-muted m-t-10">' . _lastReviewed . ': ' . htmlspecialchars($app['REVIEWED_DATE']) . '</p>';
            }
        }

        echo '</div>'; // .panel-body
        echo '</div>'; // .panel

        echo '<a href="Modules.php?modname=' . htmlspecialchars($_REQUEST['modname']) . '" class="btn btn-default">' . _backToList . '</a>';
    }
} else {

    $apps_RET = DBGet(DBQuery('SELECT * FROM applications ORDER BY submitted_date DESC'));

    PopTable('header', _applications);
    echo '<div class="table-responsive"><table class="table table-striped">';
    echo '<thead><tr><th>' . _fullName . '</th><th>' . _email . '</th><th>' . _status . '</th><th>' . _submittedDate . '</th><th></th></tr></thead><tbody>';
    if (count($apps_RET)) {
        foreach ($apps_RET as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['FULL_NAME']) . '</td>';
            echo '<td>' . htmlspecialchars($row['EMAIL']) . '</td>';
            echo '<td>' . htmlspecialchars($STATUS_LABELS[$row['STATUS']] ?? $row['STATUS']) . '</td>';
            echo '<td>' . htmlspecialchars($row['SUBMITTED_DATE']) . '</td>';
            echo '<td><a href="Modules.php?modname=' . htmlspecialchars($_REQUEST['modname']) . '&id=' . $row['ID'] . '" class="btn btn-sm btn-primary">' . _view . '</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5" class="text-center text-muted">' . _noApplicationsYet . '</td></tr>';
    }
    echo '</tbody></table></div>';
    PopTable('footer', '');
}
