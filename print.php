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
 * @package   mod_forumx
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');

$forumid = required_param('f', PARAM_INT);
$postid  = required_param('p', PARAM_INT);

$PAGE->set_url('/mod/forumx/print.php', array('f' => $forumid, 'post' => $postid));


if (!$forum = $DB->get_record('forumx', array('id' => $forumid))) {
	print_error('invalidforumid', 'forumx');
}
if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
	print_error('invalidcourseid');
}
if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id)) {
	print_error('invalidcoursemodule');
}
if (!$post = forumx_get_post_full($postid, $forum->hideauthor)) {
	print_error('invalidpostid', 'forumx');
}
if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion))) {
	print_error('notpartofdiscussion', 'forumx');
}
$modcontext = context_module::instance($cm->id);

require_course_login($course, true, $cm);

$PAGE->set_pagelayout('base');
$PAGE->set_cm($cm);
$PAGE->set_context($modcontext);

echo $OUTPUT->header();

echo html_writer::tag('button', get_string('print', 'forumx'), array('class'=>'not-printable', 'onclick'=>'print()'));

$data = new stdClass();
$data->message = '';
$data->format = FORMAT_HTML;
$print = forumx_print_post_plain($post, $cm, $course, $forum, $data, null, false, $USER->timezone, false);

echo $print[FORMAT_HTML];
echo $OUTPUT->footer();
