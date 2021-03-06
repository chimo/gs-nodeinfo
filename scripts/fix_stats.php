#!/usr/bin/env php
<?php
/**
 * GNU social - a federating social network
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Plugin
 * @package   GNUsocial
 * @copyright 2018 Free Software Foundation http://fsf.org
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      https://www.gnu.org/software/social/
 */

// GNU social INSTALLDIR if we're in /plugins/Nodeinfo/scripts
$dir = realpath(__DIR__ . '/../../..');

// GNU social INSTALLDIR if we're in /local/plugins/Nodeinfo/scripts
// NOTE: False positive if the root folder of the GS install is
//       called 'local', I guess
if (preg_match('/\/local$/', $dir) === 1) {
    $dir = realpath($dir . '/..');
}

define('INSTALLDIR', $dir);

$longoptions = ['type='];

$helptext = <<<END_OF_HELP
fix_stats.php [options]
Counts the stats from database values and updates the table.

    --type          Optional flag to specify the type to update. They all are updated by default.
                       Type: can be 'users', 'posts' or 'comments'. Or 'all', to update all the types.

END_OF_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';

$valid_types = ['all', 'users', 'posts', 'comments'];

$verbose = have_option('v', 'verbose');

$type_to_fix = get_option_value('type');
if (!in_array($type_to_fix, $valid_types)) {
    echo "You must provide a valid type!\n\n";
    show_help();
    exit(1);
}

if ($verbose) {
    echo "Started.\n\n";
}

if ($type_to_fix == 'all' || $type_to_fix == 'users') {
    if ($verbose) {
        echo "[+] Updating Users stats...\n";
    }
    $us = Usage_stats::getKV('users');
    $us->count = getUserCount();
    $us->update();
}

if ($type_to_fix == 'all' || $type_to_fix == 'posts') {
    if ($verbose) {
        echo "[+] Updating Posts stats...\n";
    }
    $us = Usage_stats::getKV('posts');
    $us->count = getPostCount();
    $us->update();
}

if ($type_to_fix == 'all' || $type_to_fix == 'comments') {
    if ($verbose) {
        echo "[+] Updating Comments stats...\n";
    }
    $us = Usage_stats::getKV('comments');
    $us->count = getCommentCount();
    $us->update();
}

if ($verbose) {
    echo "\nDONE.\n";
}

/*
 * Counting functions
 */

function getUserCount()
{
    $users = new User();
    $userCount = $users->count();

    return $userCount;
}

function getPostCount()
{
    $notices = new Notice();
    $notices->is_local = Notice::LOCAL_PUBLIC;
    $notices->whereAdd('reply_to IS NULL');
    $noticeCount = $notices->count();

    return $noticeCount;
}

function getCommentCount()
{
    $notices = new Notice();
    $notices->is_local = Notice::LOCAL_PUBLIC;
    $notices->whereAdd('reply_to IS NOT NULL');
    $commentCount = $notices->count();

    return $commentCount;
}
