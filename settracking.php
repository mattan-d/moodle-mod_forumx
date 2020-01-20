<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Set tracking option for the forum.
 *
 * @package   mod_forumx
 * @copyright 2005 mchurch
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

$id         = required_param('id', PARAM_INT);                       // The forum to subscribe or unsubscribe to.
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE); // Page to return to.

require_sesskey();

if (!$forum = $DB->get_record('forumx', array('id' => $id))) {
    print_error('invalidforumid', 'forumx');
}

if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
    print_error('invalidcoursemodule');
}

if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);
$returnpageurl = new moodle_url('/mod/forumx/'.$returnpage, array('id' => $course->id, 'f' => $forum->id));
$returnto = $returnpageurl;

if (!forumx_tp_can_track_forums($forum)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->forum = format_string($forum->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('forumid' => $forum->id),
);

if (forumx_tp_is_tracked($forum) ) {
    if (forumx_tp_stop_tracking($forum->id)) {
        $event = \mod_forumx\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string('nownottracking', 'forumx', $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (forumx_tp_start_tracking($forum->id)) {
        $event = \mod_forumx\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string('nowtracking', 'forumx', $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}