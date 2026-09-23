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
$menu['admissions']['admin'] = array(
    'admissions/Applications.php' => _applications,
);
