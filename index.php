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
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/forumx/lib.php');
require_once($CFG->libdir.'/rsslib.php');

$id = optional_param('id', 0, PARAM_INT); // Course id.

$url = new moodle_url('/mod/forumx/index.php', array('id'=>$id));
$PAGE->set_url($url);

if ($id) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_forumx\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strforums       = get_string('forums', 'forumx');
$strforum        = get_string('forum', 'forumx');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'forumx');
$strsubscribed   = get_string('subscribed', 'forumx');
$strunreadposts  = get_string('unreadposts', 'forumx');
$strtracking     = get_string('tracking', 'forumx');
$strmarkallread  = get_string('markallread', 'forumx');
$strtrackforum   = get_string('trackforum', 'forumx');
$strnotrackforum = get_string('notrackforum', 'forumx');
$strsubscribe    = get_string('subscribe', 'forumx');
$strunsubscribe  = get_string('unsubscribe', 'forumx');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');
$strsubscribeyes = get_string('subscribeyes', 'forumx');
$strsubscribeno  = get_string('subscribeno', 'forumx');
$strtrackyes     = get_string('trackyes', 'forumx');
$strtrackno      = get_string('trackno', 'forumx');


// Retrieve the list of forum digest options for later.
$digestoptions = forumx_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/forumx/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Forums.

$generaltable = new html_table();
$generaltable->head  = array($strforum, $strdescription, $strdiscussions);
$generaltable->align = array('left', 'left', 'center');

if ($usetracking = forumx_tp_can_track_forums()) {
    $untracked = forumx_tp_get_untracked_forums($USER->id, $course->id);
}

// Fill the subscription cache for this course and user combination.
\mod_forumx\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = !isguestuser() && isloggedin() && has_capability('mod/forumx:viewdiscussion', $coursecontext);

$show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->forumx_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->forumx_enablerssfeeds);

$usesections = course_format_uses_sections($course->format);

// Parse and organise all the forums.  Most forums are course modules but
// some special ones are not.  These get placed in the general forums
// category with the forums in section 0.

$forums = $DB->get_records_sql('SELECT f.*,
           d.maildigest
      FROM {forumx} f
 LEFT JOIN {forumx_digests} d ON d.forumx = f.id AND d.userid = ?
     WHERE f.course = ?', 
		array($USER->id, $course->id));

$forumslist = array('generalforums' => array(), 'learningforums' => array());
$forumsoutput = array('generalforums' => array(), 'learningforums' => array());
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('forumx') as $forumid=>$cm) {
    if (!$cm->uservisible or !isset($forums[$forumid])) {
        continue;
    }

    $forum = $forums[$forumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue; // Shouldn't happen.
    }

    if (!has_capability('mod/forumx:viewdiscussion', $context)) {
        continue;
    }

    // Fill two type array - order in modinfo is the same as in course.
    if ($forum->type == 'news' || $forum->type == 'social') {
        $forumslist['generalforums'][$forum->id] = $forum;

    } else if ($course->id == SITEID || empty($cm->sectionnum)) {
        $forumslist['generalforums'][$forum->id] = $forum;

    } else {
        $forumslist['learningforums'][$forum->id] = $forum;
    }
}

// Only real courses have learning forums.
if ($course->id == SITEID) {
	unset($forumslist['learningforums']);
}
$sub_count = 0;
$track_count = 0;
foreach ($forumslist as $forumtype=>$typelist) {
	foreach ($typelist as $forum) {
		$trackunread = 0;
		$cm      = $modinfo->instances['forumx'][$forum->id];
		$context = context_module::instance($cm->id);

		$count = forumx_count_discussions($forum, $cm, $course);
		
		if ($usetracking) {
			if ($forum->trackingtype == forumx_TRACKING_OFF) {
			} else {
				if (isset($untracked[$forum->id])) {
				} else if ($unread = forumx_tp_count_forum_unread_posts($cm, $course)) {
					$track_count++;
					$trackunread = $unread;
				} else {
					$track_count++;
				}
		
				if (($forum->trackingtype == forumx_TRACKING_FORCED) && ($CFG->forumx_allowforcedreadtracking)) {
				} else if ($forum->trackingtype === forumx_TRACKING_OFF || ($USER->trackforums == 0)) {
				}
			}
		}
		$forum->intro = format_module_intro('forumx', $forum, $cm->id);
		$forumname = format_string($forum->name, true);
		
		if ($cm->visible) {
			$style = '';
		} else {
			$style = 'class="dimmed"';
		}
		$forumlink = "<a href=\"view.php?f=$forum->id\" $style>".format_string($forum->name,true)."</a>";
		$discussionlink = "<a href=\"view.php?f=$forum->id\" $style>".$count."</a>";
		
	    $lastupdate = forumx_get_last_forum_update($cm, $course);
	    // If forum has no discussion, set the forum's updata date.
	    if ($lastupdate == 0) {
	    	$lastupdate = $forum->timemodified;
	    }
	    $roww = array(
	    		'forum'=>$forum,
	    		'visible'=>$cm->visible, 
	    		'name'=>format_string($forum->name, true),
	    		'intro'=>$forum->intro,
	    		'discussions'=>$count,
	    		'cantrack'=>$usetracking,
	    		'istracked'=>!isset($untracked[$forum->id]),
	    		'unread'=>$trackunread,
	    		'lastupdate'=>$lastupdate,
	    		'cansubscribe'=>$can_subscribe,
	    		'locked'=>forumx_is_forum_locked($forum)
	    );
	    // TODO: Set digest option in the interface.
        // If this forum has RSS activated, calculate it.
        if ($show_rss) {
        	if ($forum->rsstype and $forum->rssarticles) {
        		//Calculate the tooltip text
        		if ($forum->rsstype == 1) {
        			$tooltiptext = get_string('rsssubscriberssdiscussions', 'forumx');
        		} else {
        			$tooltiptext = get_string('rsssubscriberssposts', 'forumx');
        		}
        
        		if (!isloggedin() && $course->id == SITEID) {
        			$userid = guest_user()->id;
        		} else {
        			$userid = $USER->id;
        		}
        		// Get html code for RSS link.
        		$roww['rss'] = rss_get_link($context->id, $userid, 'mod_forumx', $forum->id, $tooltiptext);
        	} else {
        	}
        }
        $forumsoutput[$forumtype][$forum->id] = $roww;
        
	}
}
forumx_add_desktop_styles();
$PAGE->requires->js_call_amd('mod_forumx/forumindex', 'init', array(array(
		'course'=>$course->id
)));
$PAGE->requires->strings_for_js(array(
		'actionsuccess', 'actionfail', 'cancel', 'subscribeforum:no', 'subscribeforum:nolabel',
		'subscribeforum:yes', 'subscribeforum:yeslabel', 'tracking:no', 'tracking:nolabel',
		'tracking:yes', 'tracking:yeslabel', 'confirm', 'unreadposts', 'markallread',
		'subscribeforumindex:no', 'subscribeforumindex:nolabel', 'subscribeforumindex:yes', 'subscribeforumindex:yeslabel',
		'trackingindex:no', 'trackingindex:nolabel', 'trackingindex:yes', 'trackingindex:yeslabel')
		, 'forumx');

// Output the page.
$PAGE->navbar->add($strforums);
$PAGE->set_title("$course->shortname: $strforums");
$PAGE->add_body_class('path-mod-forumx2');
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo forumx_print_top_panel($course, '', false);

echo $OUTPUT->heading(get_string('forumslist', 'forumx'), 2, 'forum_index_title');
echo forumx_print_top_buttons_index_menu($can_subscribe, $usetracking);
if (!empty($forumsoutput['generalforums'])) {
	echo forumx_print_forums_list($forumsoutput['generalforums'], get_string('generalforums', 'forumx'));
}

if (!empty($forumsoutput['learningforums'])) {
	echo forumx_print_forums_list($forumsoutput['learningforums'], get_string('learningforums', 'forumx'));
}

echo $OUTPUT->footer();
