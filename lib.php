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

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/../forum/deprecatedlib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

define('forumx_MODE_FLATOLDEST', 1);
define('forumx_MODE_FLATNEWEST', -1);
define('forumx_MODE_THREADED', 2);
define('forumx_MODE_NESTED', 3);
define('forumx_MODE_ONLY_DISCUSSION', 4);
define('forumx_MODE_ALL', 5);
define('forumx_MODE_BLOG', 7);

define('forumx_CHOOSESUBSCRIBE', 0);
define('forumx_FORCESUBSCRIBE', 1);
define('forumx_INITIALSUBSCRIBE', 2);
define('forumx_DISALLOWSUBSCRIBE', 3);

/**
 * forumx_DISPLAY_DEFAULT - Use default display.
 */
define('forumx_DISPLAY_DEFAULT', 0);

/**
 * forumx_DISPLAY_NO_COMMANDS - display without commands.
 */
define('forumx_DISPLAY_NO_COMMANDS', 1);

/**
 * forumx_DISPLAY_OPEN - Display opened.
 */
define('forumx_DISPLAY_OPEN', 2);

/**
 * forumx_DISPLAY_OPEN_NO_COMMANDS - Display opened without commands.
 */
define('forumx_DISPLAY_OPEN_NO_COMMANDS', 3);

/**
 * forumx_DISPLAY_OPEN_CLEAN - Display opened without commands and other parameters.
 */
define('forumx_DISPLAY_OPEN_CLEAN', 4);

/**
 * forumx_DISPLAY_THREADED - Display in threaded mode.
 */
define('forumx_DISPLAY_THREADED', 5);

/**
 * forumx_UNLOCKED - Forum is locked.
 */
define('forumx_UNLOCKED', 0);

/**
 * forumx_LOCKED - Forum is unlocked.
 */
define('forumx_LOCKED', 1);
/**
 * forumx_TRACKING_OFF - Tracking is not available for this forum.
 */
define('forumx_TRACKING_OFF', 0);

/**
 * forumx_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('forumx_TRACKING_OPTIONAL', 1);

/**
 * forumx_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as forumx_TRACKING_OPTIONAL if $CFG->forumx_allowforcedreadtracking is off.
 */
define('forumx_TRACKING_FORCED', 2);

define('forumx_MAILED_PENDING', 0);
define('forumx_MAILED_SUCCESS', 1);
define('forumx_MAILED_ERROR', 2);

define('forumx_EMAIL_DIVIDER', "---------------------------------------------------------------------\n");

if (!defined('forumx_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in forum cron. */
    define('forumx_CRON_USER_CACHE', 5000);
}

/**
 * forumx_POSTS_ALL_USER_GROUPS - All the posts in groups where the user is enrolled.
 */
define('forumx_POSTS_ALL_USER_GROUPS', -2);


define('forumx_SUBSACRIBE_DISCUSSION_DIALLOWED', -2);
define('forumx_SUBSACRIBE_DISCUSSION_SUBSCRIBED_TO_FORUM', -1);
define('forumx_SUBSACRIBE_DISCUSSION_ALLOWED_NOT_SUBSCRIBED', 0);
define('forumx_SUBSACRIBE_DISCUSSION_ALLOWED_SUBSCRIBED', 1);
define('forumx_OUIL_TABLES', 0);

define('forumx_EXTRACT_SHORTNAME_NONE', 0);
define('forumx_EXTRACT_SHORTNAME_PRE', 1);
define('forumx_EXTRACT_SHORTNAME_POST', 2);
/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $forum add forum instance
 * @param mod_forumx_mod_form $mform
 * @return int intance id
 */
function forumx_add_instance($forum, $mform = null)
{
    global $CFG, $DB;

    $forum->timemodified = time();

    if (empty($forum->assessed)) {
        $forum->assessed = 0;
    }

    if (empty($forum->ratingtime) || empty($forum->assessed)) {
        $forum->assesstimestart = 0;
        $forum->assesstimefinish = 0;
    }

    $forum->id = $DB->insert_record('forumx', $forum);
    $modcontext = context_module::instance($forum->coursemodule);

    if ($forum->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course = $forum->course;
        $discussion->forumx = $forum->id;
        $discussion->name = $forum->name;
        $discussion->assessed = $forum->assessed;
        $discussion->message = $forum->intro;
        $discussion->messageformat = $forum->introformat;
        $discussion->messagetrust = trusttext_trusted(context_course::instance($forum->course));
        $discussion->mailnow = false;
        $discussion->groupid = -1;

        $message = '';

        $discussion->id = forumx_add_discussion($discussion, null, $message);

        if ($mform && $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('forumx_discussions', array('id' => $discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('forumx_posts', array('id' => $discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs' => true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_forumx', 'post', $post->id, $options, $post->message);
            $DB->set_field('forumx_posts', 'message', $post->message, array('id' => $post->id));
        }
    }
    forumx_grade_item_update($forum);

    return $forum->id;
}


/**
 * Handle changes following the creation of a forum instance.
 * This function is typically called by the course_module_created observer.
 *
 * @param object $context the forum context
 * @param stdClass $forum The forum object
 * @return void
 */
function forumx_instance_created($context, $forum)
{

    if ($forum->forcesubscribe == forumx_INITIALSUBSCRIBE) {
        $users = \mod_forumx\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email');
        $exam_users = forumx_get_users_in_groupname($forum->course, 'exam');

        foreach ($users as $user) {
            if (isset($exam_users[$user->id])) {
                continue;
            }
            \mod_forumx\subscriptions::subscribe_user($user->id, $forum, $context);
        }
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $forum forum instance (with magic quotes)
 * @return bool success
 * @global object
 */
function forumx_update_instance($forum, $mform)
{
    global $DB, $OUTPUT, $USER;

    $forum->timemodified = time();
    $forum->id = $forum->instance;

    if (empty($forum->assessed)) {
        $forum->assessed = 0;
    }

    if (empty($forum->ratingtime) || empty($forum->assessed)) {
        $forum->assesstimestart = 0;
        $forum->assesstimefinish = 0;
    }

    $oldforum = $DB->get_record('forumx', array('id' => $forum->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire forum
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldforum->assessed <> $forum->assessed) || ($oldforum->scale <> $forum->scale)) {
        forumx_update_grades($forum); // recalculate grades for the forum
    }

    if ($forum->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('forumx_discussions', array('forumx' => $forum->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'forumx'));
            }
            $discussion = array_pop($discussions);
        } else {
            // Try to recover by creating initial discussion - MDL-16262.
            $discussion = new stdClass();
            $discussion->course = $forum->course;
            $discussion->forumx = $forum->id;
            $discussion->name = $forum->name;
            $discussion->assessed = $forum->assessed;
            $discussion->message = $forum->intro;
            $discussion->messageformat = $forum->introformat;
            $discussion->messagetrust = true;
            $discussion->mailnow = false;
            $discussion->groupid = -1;

            $message = '';

            forumx_add_discussion($discussion, null, $message);

            if (!$discussion = $DB->get_record('forumx_discussions', array('forumx' => $forum->id))) {
                print_error('cannotadd', 'forumx');
            }
        }
        if (!$post = $DB->get_record('forumx_posts', array('id' => $discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'forumx');
        }

        $cm = get_coursemodule_from_instance('forumx', $forum->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('forumx_posts', array('id' => $discussion->firstpost), '*', MUST_EXIST);
        $post->subject = $forum->name;
        $post->message = $forum->intro;
        $post->messageformat = $forum->introformat;
        $post->messagetrust = trusttext_trusted($modcontext);
        $post->modified = $forum->timemodified;
        $post->userid = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform && $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs' => true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_forumx', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('forumx_posts', $post);
        $discussion->name = $forum->name;
        $DB->update_record('forumx_discussions', $discussion);
    }

    $DB->update_record('forumx', $forum);
    forumx_grade_item_update($forum);

    if (($forum->forcesubscribe == forumx_INITIALSUBSCRIBE) && ($oldforum->forcesubscribe <> $forum->forcesubscribe)) {
        forumx_post_availability_changes($forum->id, false);
    }
    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id forum instance id
 * @return bool success
 * @global object
 */
function forumx_delete_instance($id)
{
    global $DB;

    if (!$forum = $DB->get_record('forumx', array('id' => $id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('forumx', $forum->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // Now get rid of all files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    // Delete digest and subscription preferences.
    $DB->delete_records('forumx_digests', array('forumx' => $forum->id));
    $DB->delete_records('forumx_subscriptions', array('forumx' => $forum->id));
    $DB->delete_records('forumx_discussion_sub', array('forumid' => $forum->id));

    if ($discussions = $DB->get_records('forumx_discussions', array('forumx' => $forum->id))) {
        foreach ($discussions as $discussion) {
            if (!forumx_delete_discussion($discussion, true, $course, $cm, $forum)) {
                $result = false;
            }
        }
    }

    forumx_tp_delete_read_records(-1, -1, -1, $forum->id);

    if (!$DB->delete_records('forumx', array('id' => $forum->id))) {
        $result = false;
    }

    forumx_grade_item_delete($forum);

    return $result;
}

/**
 * Indicates API features that the forum supports.
 *
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 */
function forumx_supports($feature)
{
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_RATE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_PLAGIARISM:
            return true;

        default:
            return null;
    }
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 * @global object
 * @global object
 */
function forumx_get_completion_state($course, $cm, $userid, $type)
{
    global $CFG, $DB;

    // Get forum details.
    if (!($forum = $DB->get_record('forumx', array('id' => $cm->instance)))) {
        throw new Exception("Can't find forum {$cm->instance}");
    }

    $result = $type; // Default return value.

    $postcountparams = array('userid' => $userid, 'forumid' => $forum->id);
    $postcountsql = '
SELECT
    COUNT(1)
FROM
    {forumx_posts} fp
    INNER JOIN {forumx_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.forumx=:forumid';

    if ($forum->completiondiscussions) {
        $value = $forum->completiondiscussions <=
            $DB->count_records('forumx_discussions', array('forumx' => $forum->id, 'userid' => $userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forum->completionreplies) {
        $value = $forum->completionreplies <=
            $DB->get_field_sql($postcountsql . ' AND fp.parent<>0', $postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($forum->completionposts) {
        $value = $forum->completionposts <= $DB->get_field_sql($postcountsql, $postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    return $result;
}

/**
 * Create a message-id string to use in the custom headers of forum notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the forum post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @param string $hostname The server's hostname
 * @return string A unique message-id
 */
function forumx_get_email_message_id($postid, $usertoid, $hostname)
{
    return '<' . hash('sha256', $postid . 'to' . $usertoid) . '@' . $hostname . '>';
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function forumx_cron_minimise_user_record(stdClass $user)
{

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the scheduled task.
 *
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers as well as other maintance tasks.
 *
 * NOTE: Since 2.7.2 this function is run by scheduled task rather
 * than standard cron.
 *
 * @todo MDL-44734 The function will be split up into seperate tasks.
 */
function forumx_cron()
{
    global $CFG, $USER, $DB, $PAGE;

    $site = get_site();

    // The main renderers.
    $htmlout = $PAGE->get_renderer('mod_forumx', 'email', 'htmlemail');
    $textout = $PAGE->get_renderer('mod_forumx', 'email', 'textemail');
    $htmldigestfullout = $PAGE->get_renderer('mod_forumx', 'emaildigestfull', 'htmlemail');
    $textdigestfullout = $PAGE->get_renderer('mod_forumx', 'emaildigestfull', 'textemail');
    $htmldigestbasicout = $PAGE->get_renderer('mod_forumx', 'emaildigestbasic', 'htmlemail');
    $textdigestbasicout = $PAGE->get_renderer('mod_forumx', 'emaildigestbasic', 'textemail');

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!

    // Status arrays.
    $mailcount = array();
    $errorcount = array();

    // caches
    $discussions = array();
    $forums = array();
    $courses = array();
    $coursemodules = array();
    $subscribedusers = array();
    $messageinboundhandlers = array();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow = time();
    $endtime = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier.

    // Get the list of forum subscriptions for per-user per-forum maildigest settings.
    $digestsset = $DB->get_recordset('forumx_digests', null, '', 'id, userid, forumx, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->forumx])) {
            $digests[$thisrow->forumx] = array();
        }
        $digests[$thisrow->forumx][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    // Create the generic messageinboundgenerator.
    $messageinboundgenerator = new \core\message\inbound\address_manager();
    $messageinboundgenerator->set_handler('\mod_forumx\message\inbound\reply_handler');

    if ($posts = forumx_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!forumx_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                    \mod_forumx\subscriptions::fill_subscription_cache($discussion->forumx);
                    \mod_forumx\subscriptions::fill_discussion_subscription_cache($discussion->forumx);

                } else {
                    mtrace('Could not find discussion ' . $discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $forumid = $discussions[$discussionid]->forumx;
            if (!isset($forums[$forumid])) {
                if ($forum = $DB->get_record('forumx', array('id' => $forumid))) {
                    $forums[$forumid] = $forum;
                } else {
                    mtrace('Could not find forum ' . $forumid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $forums[$forumid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course ' . $courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$forumid])) {
                if ($cm = get_coursemodule_from_instance('forumx', $forumid, $courseid)) {
                    $coursemodules[$forumid] = $cm;
                } else {
                    mtrace('Could not find course module for forum ' . $forumid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            // Save the Inbound Message datakey here to reduce DB queries later.
            $messageinboundgenerator->set_data($pid);
            $messageinboundhandlers[$pid] = $messageinboundgenerator->fetch_data_key();

            // Caching subscribed users of each forum.
            if (!isset($subscribedusers[$forumid])) {
                $modcontext = context_module::instance($coursemodules[$forumid]->id);
                if ($subusers = \mod_forumx\subscriptions::fetch_subscribed_users($forums[$forumid], 0, $modcontext, 'u.*', true)) {

                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this forum
                        $subscribedusers[$forumid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > forumx_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            forumx_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }
            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {
            // Terminate if processing of any account takes longer than 2 minutes.
            core_php_time_limit::raise(120);

            mtrace('Processing user ' . $userto->id);

            // Init user caches - we keep the cache for one cycle only, otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                forumx_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost = array();
            $userto->markposts = array();

            // Setup this user so that the capabilities are cached, and environment matches receiving user.
            cron_setup_user($userto);

            // Reset the caches.
            foreach ($coursemodules as $forumid => $unused) {
                $coursemodules[$forumid]->cache = new stdClass();
                $coursemodules[$forumid]->cache->caps = array();
                unset($coursemodules[$forumid]->uservisible);
            }

            foreach ($posts as $pid => $post) {
                $discussion = $discussions[$post->discussion];
                $forum = $forums[$discussion->forumx];
                $course = $courses[$forum->course];
                $cm =& $coursemodules[$forum->id];

                // Do some checks to see if we can bail out now.

                // Do not send emails from a hidden course.
                if ($course->visible == 0) {
                    mtrace('Course ' . $course->id . ' is hidden. Do not email');
                    continue;
                }
                // Only active enrolled users are in the list of subscribers.
                // This does not necessarily mean that the user is subscribed to the forum or to the discussion though.
                if (!isset($subscribedusers[$forum->id][$userto->id])) {
                    // The user does not subscribe to this forum.
                    continue;
                }

                if (!\mod_forumx\subscriptions::is_subscribed($userto->id, $forum, $post->discussion, $coursemodules[$forum->id])) {
                    // The user does not subscribe to this forum, or to this specific discussion.
                    continue;
                }

                if ($subscriptiontime = \mod_forumx\subscriptions::fetch_discussion_subscription($forum->id, $userto->id)) {
                    // Skip posts if the user subscribed to the discussion after it was created.
                    if (isset($subscriptiontime[$post->discussion]) && ($subscriptiontime[$post->discussion] > $post->created)) {
                        continue;
                    }
                }

                // Don't send email if the forum is Q&A and the user has not posted.
                // Initial topics are still mailed.
                if ($forum->type == 'qanda' && !forumx_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email ' . $userto->id . ' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user.
                if (array_key_exists($post->userid, $users)) {
                    // We might know the user already.
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        forumx_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    forumx_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= forumx_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }
                } else {
                    mtrace('Could not find user ' . $post->userid . ', author of post ' . $post->id . '. Unable to send message.');
                    continue;
                }

                // Note: If we want to check that userto and userfrom are not the same person this is probably the spot to do it.

                // Setup global $COURSE properly - needed for roles and languages.
                cron_setup_user($userto, $course);

                // Fill caches.
                if (!isset($userto->viewfullnames[$forum->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$forum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = forumx_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$forum->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$forum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$forum->id] = $userfrom->groups[$forum->id];
                    }
                }

                // Make sure groups allow this user to see this email.
                if ($discussion->groupid > 0 && $groupmode = groups_get_activity_groupmode($cm, $course)) {
                    // Groups are being used.
                    if (!groups_group_exists($discussion->groupid)) {
                        // Can't find group - be safe and don't this message.
                        continue;
                    }

                    if (!groups_is_member($discussion->groupid) && !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // Do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS.
                        continue;
                    }
                }

                // Make sure we're allowed to see the post.
                if (!forumx_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                    mtrace('User ' . $userto->id . ' can not see ' . $post->id . '. Not sending message.');
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = forumx_get_user_maildigest_bulk($digests, $userto, $forum->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form.
                    $queue = new stdClass();
                    $queue->userid = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('forumx_queue', $queue);
                    continue;
                }

                // Prepare to actually send the post now, and build up the content.

                $cleanforumname = str_replace('"', "'", strip_tags(format_string($forum->name)));

                $userfrom->customheaders = array(
                    // Headers to make emails easier to track.
                    'List-Id: "' . $cleanforumname . '" <moodleforum' . $forum->id . '@' . $hostname . '>',
                    'List-Help: ' . $CFG->wwwroot . '/mod/forumx/view.php?f=' . $forum->id,
                    'Message-ID: ' . forumx_get_email_message_id($post->id, $userto->id, $hostname),
                    'X-Course-Id: ' . $course->id,
                    'X-Course-Name: ' . format_string($course->fullname, true),

                    // Headers to help prevent auto-responders.
                    'Precedence: Bulk',
                    'X-Auto-Response-Suppress: All',
                    'Auto-Submitted: auto-generated',
                );

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                // Generate a reply-to address from using the Inbound Message handler.
                $replyaddress = null;
                if ($userto->canpost[$discussion->id] && array_key_exists($post->id, $messageinboundhandlers)) {
                    $messageinboundgenerator->set_data($post->id, $messageinboundhandlers[$post->id]);
                    $replyaddress = $messageinboundgenerator->generate($userto->id);
                }

                if (!isset($userto->canpost[$discussion->id])) {
                    $canreply = forumx_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                } else {
                    $canreply = $userto->canpost[$discussion->id];
                }

                if ($forum->hideauthor) {
                    $userfrom = core_user::get_noreply_user();
                    $userfrom->firstname = get_string('forumauthorhidden', 'forumx');
                    $userfrom->fullname = get_string('forumauthorhidden', 'forumx');
                    $post->userid = core_user::NOREPLY_USER;
                }

                $data = new \mod_forumx\output\forumx_post_email(
                    $course,
                    $cm,
                    $forum,
                    $discussion,
                    $post,
                    $userfrom,
                    $userto,
                    $canreply
                );

                if (!isset($userto->viewfullnames[$forum->id])) {
                    $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                } else {
                    $data->viewfullnames = $userto->viewfullnames[$forum->id];
                }

                $a = new stdClass();
                $a->coursefullname = $data->get_coursefullname();
                $a->forumname = $cleanforumname;
                $a->subject = $data->get_subject();
                $postsubject = html_to_text(get_string('postmailsubjectlong', 'forumx', $a), 0);

                $rootid = forumx_get_email_message_id($discussion->firstpost, $userto->id, $hostname);

                if ($post->parent) {
                    // This post is a reply, so add reply header (RFC 2822).
                    $parentid = forumx_get_email_message_id($post->parent, $userto->id, $hostname);
                    $userfrom->customheaders[] = "In-Reply-To: $parentid";

                    // If the post is deeply nested we also reference the parent message id and
                    // the root message id (if different) to aid threading when parts of the email
                    // conversation have been deleted (RFC1036).
                    if ($post->parent != $discussion->firstpost) {
                        $userfrom->customheaders[] = "References: $rootid $parentid";
                    } else {
                        $userfrom->customheaders[] = "References: $parentid";
                    }
                }

                // MS Outlook / Office uses poorly documented and non standard headers, including
                // Thread-Topic which overrides the Subject and shouldn't contain Re: or Fwd: etc.
                $a->subject = $discussion->name;
                $postsubject = html_to_text(get_string('postmailsubjectlong', 'forumx', $a), 0);
                $userfrom->customheaders[] = "Thread-Topic: $postsubject";
                $userfrom->customheaders[] = "Thread-Index: " . substr($rootid, 1, 28);

                // Send the post now!
                mtrace('Sending ', '');

                $eventdata = new \core\message\message();
                $eventdata->component = 'mod_forumx';
                $eventdata->name = 'posts';
                $eventdata->userfrom = $userfrom;
                $eventdata->userto = $userto;
                $eventdata->subject = $postsubject;
                $eventdata->fullmessage = $textout->render($data);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = $htmlout->render($data);
                $eventdata->notification = 1;
                $eventdata->replyto = $replyaddress;
                if (!empty($replyaddress)) {
                    // Add extra text to email messages if they can reply back.
                    $textfooter = "\n\n" . get_string('replytopostbyemail', 'mod_forumx');
                    $htmlfooter = html_writer::tag('p', get_string('replytopostbyemail', 'mod_forumx'));
                    $additionalcontent = array('fullmessage' => array('footer' => $textfooter),
                        'fullmessagehtml' => array('footer' => $htmlfooter));
                    $eventdata->set_additional_content('email', $additionalcontent);
                }

                // If forumx_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->forumx_replytouser)) {
                    $eventdata->userfrom = core_user::get_noreply_user();
                }
                if ($forum->hideauthor) {
                    $eventdata->userfrom->firstname = get_string('forumauthorhidden', 'forumx');
                } else {
                    $eventdata->userfrom->firstname = fullname($userfrom);
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user = fullname($userfrom);
                $smallmessagestrings->forumname = "$shortname: " . format_string($forum->name, true) . ": " . $discussion->name;
                $smallmessagestrings->message = $post->message;

                // Make sure strings are in message recipients language.
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'forumx', $smallmessagestrings, $userto->lang);

                $contexturl = new moodle_url('/mod/forumx/discuss.php', array('d' => $discussion->id), 'p' . $post->id);
                $eventdata->contexturl = $contexturl->out();
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult) {
                    mtrace("Error: mod/forumx/lib.php forumx_cron(): Could not send out mail for id $post->id to user $userto->id" .
                        " ($userto->email) .. not trying again.");
                    $errorcount[$post->id]++;
                } else {
                    $mailcount[$post->id]++;

                    // Mark post as read if forumx_usermarksread is set off.
                    if (!$CFG->forumx_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post ' . $post->id . ': ' . $post->subject);
            }

            // Mark processed posts as read.
            forumx_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id] . " users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('forumx_posts', 'mailed', forumx_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = core_date::get_server_timezone();

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    core_php_time_limit::raise(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->ouildigestmailtimelast)) {    // To catch the first time
        set_config('ouildigestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->ouildigestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('forumx_queue', "timemodified < ?", array($weekago));
    mtrace('Cleaned old digest records');

    if ($CFG->ouildigestmailtimelast < $digesttime && $timenow > $digesttime) {

        mtrace('Sending forum digests: ' . userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('forumx_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('forumx_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('forumx_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $forumid = $discussions[$discussionid]->forumx;
                if (!isset($forums[$forumid])) {
                    if ($forum = $DB->get_record('forumx', array('id' => $forumid))) {
                        $forums[$forumid] = $forum;
                    } else {
                        continue;
                    }
                }

                $courseid = $forums[$forumid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$forumid])) {
                    if ($cm = get_coursemodule_from_instance('forumx', $forumid, $courseid)) {
                        $coursemodules[$forumid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset

            // Set hidden user for anonymous forums.
            $hidden_user = core_user::get_noreply_user();
            $hidden_user->firstname = get_string('forumauthorhidden', 'forumx');
            $hidden_user->fullname = get_string('forumauthorhidden', 'forumx');

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                core_php_time_limit::raise(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'forumx', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('forumx_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) && isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    forumx_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost = array();
                $userto->markposts = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'forumx', format_string($site->shortname, true));
                // Set top message according to digest type.
                $maildigest = forumx_get_user_maildigest_bulk($digests, $userto, $forum->id);
                if ($maildigest == 2) {
                    $digest_title_text = 'digestmailheadersubject';
                } else {
                    $digest_title_text = 'digestmailheaderfull';
                }

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot . '/user/edit.php?id=' . $userid . '&amp;course=' . $site->id;

                //$posttext = get_string('digestmailheader', 'forumx', $headerdata)."\n\n";
                $posttext = get_string($digest_title_text, 'forumx') . "\n\n";
                $headerdata->userprefs = '<a target="_blank" href="' . $headerdata->userprefs . '">' . get_string('digestmailprefs', 'forumx') . '</a>';

                $posthtml = "<head>";
                /*                foreach ($CFG->stylesheets as $stylesheet) {
                    //TODO: MDL-21120
                    $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                }*/
                $posthtml .= "</head>\n<body id=\"email\">\n";
                //$posthtml .= '<p>'.get_string('digestmailheader', 'forumx', $headerdata).'</p><br /><hr size="1" noshade="noshade" />';
                $posthtml .= '<p>' . get_string($digest_title_text, 'forumx') . '</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    core_php_time_limit::raise(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $forum = $forums[$discussion->forumx];
                    $course = $courses[$forum->course];
                    $cm = $coursemodules[$forum->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$forum->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$forum->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = forumx_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strforums = get_string('forums', 'forumx');
                    $canunsubscribe = !\mod_forumx\subscriptions::is_forcesubscribed($forum);
                    $canreply = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strforums -> " . format_string($forum->name, true);
                    if ($discussion->name != $forum->name) {
                        $posttext .= " -> " . format_string($discussion->name, true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot . '/mod/forumx/discuss.php?d=' . $discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">" .
                        "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> " .
                        "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumx/index.php?id=$course->id\">$strforums</a> -> " .
                        "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumx/view.php?f=$forum->id\">" . format_string($forum->name, true) . "</a>";
                    if ($discussion->name == $forum->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/forumx/discuss.php?d=$discussion->id\">" . format_string($discussion->name, true) . "</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                forumx_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            forumx_cron_minimise_user_record($userfrom);
                            if ($userscount <= forumx_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user ' . $post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$forum->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$forum->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$forum->id] = $userfrom->groups[$forum->id];
                            }
                        }

                        $original_user;
                        // Hide users if necessary.
                        if ($forum->hideauthor) {
                            $userfrom = clone($hidden_user);
                            $original_user = $post->userid;
                            $post->userid = core_user::NOREPLY_USER;
                        }

                        // Headers to help prevent auto-responders.
                        $userfrom->customheaders = array(
                            "Precedence: Bulk",
                            'X-Auto-Response-Suppress: All',
                            'Auto-Submitted: auto-generated',
                        );

                        //$maildigest = forumx_get_user_maildigest_bulk($digests, $userto, $forum->id);
                        if (!isset($userto->canpost[$discussion->id])) {
                            $canreply = forumx_user_can_post($forum, $discussion, $userto, $cm, $course, $modcontext);
                        } else {
                            $canreply = $userto->canpost[$discussion->id];
                        }

                        $data = new \mod_forumx\output\forumx_post_email(
                            $course,
                            $cm,
                            $forum,
                            $discussion,
                            $post,
                            $userfrom,
                            $userto,
                            $canreply
                        );

                        if (!isset($userto->viewfullnames[$forum->id])) {
                            $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                        } else {
                            $data->viewfullnames = $userto->viewfullnames[$forum->id];
                        }

                        if ($maildigest == 2) {
                            // Subjects and link only.
                            $posttext .= $textdigestbasicout->render($data);
                            $posthtml .= $htmldigestbasicout->render($data);
                        } else {
                            // The full treatment.
                            $posttext .= $textdigestfullout->render($data);
                            $posthtml .= $htmldigestfullout->render($data);

                            // Create an array of postid's for this user to mark as read.
                            if (!$CFG->forumx_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                        if ($forum->hideauthor) {
                            $post->userid = $original_user; // Reset to the original user's id.
                        }
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/forumx/subscribe.php?id=$forum->id\">" . get_string("unsubscribe", 'forumx') . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", 'forumx');
                    }
                    //$footerlinks[] = "<a href='{$CFG->wwwroot}/mod/forumx/index.php?id={$forum->course}'>" . get_string("digestmailpost", 'forumx') . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $attachment = $attachname = '';
                // Directly email forum digests rather than sending them via messaging, use the
                // site shortname as 'from name', the noreply address will be used by email_to_user.
                $mailresult = email_to_user($userto, $site->shortname, $postsubject, $posttext, $posthtml, $attachment, $attachname);

                if (!$mailresult) {
                    mtrace("ERROR: mod/forumx/cron.php: Could not send out digest mail to user $userto->id " .
                        "($userto->email)... not trying again.");
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if forumx_usermarksread is set off
                    forumx_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
        /// We have finishied all digest emails, update $CFG->ouildigestmailtimelast
        set_config('ouildigestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'forumx', $usermailcount));
    }

    if (!empty($CFG->forumx_lastreadclean)) {
        $timenow = time();
        if ($CFG->forumx_lastreadclean + (24 * 3600) < $timenow) {
            set_config('forumx_lastreadclean', $timenow);
            mtrace('Removing old forum read tracking info...');
            forumx_tp_clean_read_records();
        }
    } else {
        set_config('forumx_lastreadclean', time());
    }

    return true;
}

/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $forum
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function forumx_user_outline($course, $user, $mod, $forum)
{
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'forumx', $forum->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = forumx_count_user_posts($forum->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string('numposts', 'forumx', $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}


/**
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $forum
 * @global object
 * @global object
 */
function forumx_user_complete($course, $user, $mod, $forum)
{
    global $CFG, $USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'forumx', $forum->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade') . ': ' . $grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback') . ': ' . $grade->str_feedback);
        }
    }

    if ($posts = forumx_get_user_posts($forum->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = forumx_get_user_involved_discussions($forum->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            forumx_print_post($post, $discussion, $forum, $cm, $course, false, false, false);
        }
    } else {
        echo '<p>' . get_string('noposts', 'forumx') . '</p>';
    }
}

/**
 * Filters the forum discussions according to groups membership and config.
 *
 * @param array $discussions Discussions with new posts array
 * @return array Forums with the number of new posts
 * @since  Moodle 2.8, 2.7.1, 2.6.4
 */
function forumx_filter_user_groups_discussions($discussions)
{

    // Group the remaining discussions posts by their forumid.
    $filteredforums = array();

    // Discard not visible groups.
    foreach ($discussions as $discussion) {

        // Course data is already cached.
        $instances = get_fast_modinfo($discussion->course)->get_instances();
        $forum = $instances['forumx'][$discussion->forumx];

        // Continue if the user should not see this discussion.
        if (!forumx_is_user_group_discussion($forum, $discussion->groupid)) {
            continue;
        }

        // Grouping results by forum.
        if (empty($filteredforums[$forum->instance])) {
            $filteredforums[$forum->instance] = new stdClass();
            $filteredforums[$forum->instance]->id = $forum->id;
            $filteredforums[$forum->instance]->count = 0;
        }
        $filteredforums[$forum->instance]->count += $discussion->count;

    }

    return $filteredforums;
}

/**
 * Returns whether the discussion group is visible by the current user or not.
 *
 * @param cm_info $cm The discussion course module
 * @param int $discussiongroupid The discussion groupid
 * @return bool
 * @since Moodle 2.8, 2.7.1, 2.6.4
 */
function forumx_is_user_group_discussion(cm_info $cm, $discussiongroupid)
{

    if ($discussiongroupid == -1 || $cm->effectivegroupmode != SEPARATEGROUPS) {
        return true;
    }

    if (isguestuser()) {
        return false;
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id)) ||
        in_array($discussiongroupid, $cm->get_modinfo()->get_groups($cm->groupingid))) {
        return true;
    }

    return false;
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 * @uses VISIBLEGROUPS
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 */
function forumx_print_recent_activity($course, $viewfullnames, $timestart)
{
    global $CFG, $USER, $DB, $OUTPUT;

    // Do not use log table if possible, it may be huge and is expensive to join with other tables.

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS forumtype, d.forumx, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {forumx_posts} p
                                              JOIN {forumx_discussions} d ON d.id = p.discussion
                                              JOIN {forumx} f ON f.id = d.forumx
                                              JOIN {user} u ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course->id))) { // Order by initial posting date.
        return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['forumx'][$post->forum])) {
            // Not visible.
            continue;
        }
        $cm = $modinfo->instances['forumx'][$post->forum];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/forumx:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->forumx_enabletimedposts) && $USER->id != $post->duserid
            && (($post->timestart > 0 && $post->timestart > time()) || ($post->timeend > 0 && $post->timeend < time()))) {
            if (!has_capability('mod/forumx:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        // Check that the user can see the discussion.
        if (forumx_is_user_group_discussion($cm, $post->groupid)) {
            $printposts[] = $post;
        }

    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newforumposts', 'forumx') . ':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">' .
            '<div class="date">' . userdate($post->modified, $strftimerecent) . '</div>' .
            '<div class="name">' . fullname($post, $viewfullnames) . '</div>' .
            '</div>';
        echo '<div class="info' . $subjectclass . '">';
        if (empty($post->parent)) {
            echo '"<a href="' . $CFG->wwwroot . '/mod/forumx/discuss.php?d=' . $post->discussion . '">';
        } else {
            echo '"<a href="' . $CFG->wwwroot . '/mod/forumx/discuss.php?d=' . $post->discussion . '&amp;parent=' . $post->parent . '#p' . $post->id . '">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param object $forum
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 * @global object
 * @global object
 */
function forumx_get_user_grades($forum, $userid = 0)
{
    global $CFG;

    require_once($CFG->dirroot . '/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_forumx';
    $ratingoptions->ratingarea = 'post';

    // Need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'forumx';
    $ratingoptions->moduleid = $forum->id;
    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $forum->assessed;
    $ratingoptions->scaleid = $forum->scale;
    $ratingoptions->itemtable = 'forumx_posts';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Update activity grades
 *
 * @param object $forum
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 * @category grade
 */
function forumx_update_grades($forum, $userid = 0, $nullifnone = true)
{
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$forum->assessed) {
        forumx_grade_item_update($forum);

    } else if ($grades = forumx_get_user_grades($forum, $userid)) {
        forumx_grade_item_update($forum, $grades);

    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = NULL;
        forumx_grade_item_update($forum, $grade);

    } else {
        forumx_grade_item_update($forum);
    }
}

/**
 * Create/update grade item for given forum
 *
 * @param stdClass $forum Forum object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 * @uses GRADE_TYPE_SCALE
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 */
function forumx_grade_item_update($forum, $grades = NULL)
{
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    $params = array('itemname' => $forum->name, 'idnumber' => $forum->cmidnumber);

    if (!$forum->assessed || $forum->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($forum->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $forum->scale;
        $params['grademin'] = 0;

    } else if ($forum->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$forum->scale;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/forumx', $forum->course, 'mod', 'forumx', $forum->id, 0, $grades, $params);
}

/**
 * Delete grade item for given forum
 *
 * @param stdClass $forum Forum object
 * @return grade_item
 * @category grade
 */
function forumx_grade_item_delete($forum)
{
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/forumx', $forum->course, 'mod', 'forumx', $forum->id, 0, NULL, array('deleted' => 1));
}


/**
 * This function returns if a scale is being used by one forum
 *
 * @param int $forumid
 * @param int $scaleid negative number
 * @return bool
 * @global object
 */
function forumx_scale_used($forumid, $scaleid)
{
    global $DB;
    $return = false;

    $rec = $DB->get_record('forumx', array('id' => "$forumid", 'scale' => -$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of forum
 *
 * This is used to find out if scale used anywhere
 *
 * @param $scaleid int
 * @return boolean True if the scale is used by any forum
 * @global object
 */
function forumx_scale_used_anywhere($scaleid)
{
    global $DB;
    if ($scaleid && $DB->record_exists('forumx', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for forumx_print_post
 * Most of these joins are just to get the forum id
 *
 * @param int $postid
 * @param int $hideauthor if active then ignore author information
 * @return mixed array of posts or false
 * @global object
 * @global object
 */
function forumx_get_post_full($postid, $hideauthor = false)
{
    global $CFG, $DB, $USER;

    $params = array($USER->id, $postid);

    $post_flag_sel = ', pf.id as postflag';
    $post_flag_join = 'LEFT JOIN {forumx_flags} pf ON (pf.postid = p.id AND pf.userid = ?)';

    if (!$hideauthor) {
        $allnames = get_all_user_name_fields(true, 'u');
        $sql = 'SELECT p.*, d.forumx, ' . $allnames . ', u.email, u.picture, u.imagealt' . $post_flag_sel . '
				FROM {forumx_posts} p
				JOIN {forumx_discussions} d ON p.discussion = d.id
				LEFT JOIN {user} u ON p.userid = u.id
				' . $post_flag_join . '
				WHERE p.id = ?';
    } else {
        $sql = 'SELECT p.*, d.forumx' . $post_flag_sel . '
				FROM {forumx_posts} p
				JOIN {forumx_discussions} d ON p.discussion = d.id
				' . $post_flag_join . '
				WHERE p.id = ?';
    }
    return $DB->get_record_sql($sql, $params);
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the forum?
 * @param bool $hideauthor is the author hidden
 * @param int $only_first If true, return only the first post in the discussion.
 * @return array of posts
 * @global object
 * @global object
 * @global object
 */
function forumx_get_all_discussion_posts($discussionid, $sort = 'p.created ASC', $tracking = false, $hideauthor = false, $only_first = false)
{
    global $CFG, $DB, $USER;

    $tr_sel = '';
    $tr_join = '';
    $params = array($USER->id);
    $post_limit = $only_first ? 1 : null;

    if ($tracking) {
        $tr_sel = ', fr.id AS postread';
        $tr_join = 'LEFT JOIN {forumx_read} fr ON (fr.postid = p.id AND fr.userid = ?)';
        $params[] = $USER->id;
    }
    $params[] = $discussionid;

    if (!$hideauthor) {
        $allnames = get_all_user_name_fields(true, 'u');
        if (!$posts = $DB->get_records_sql('SELECT p.*, ' . $allnames . ',
    			u.email, u.picture, u.imagealt' . $tr_sel . ', pf.flag AS postflag
    			FROM {forumx_posts} p
    			LEFT JOIN {user} u ON p.userid = u.id
    			LEFT JOIN {forumx_flags} pf ON (pf.postid = p.id AND pf.userid = ?)
    			INNER JOIN {forumx_discussions} d ON d.id = p.discussion
    			' . $tr_join . '
    			WHERE p.discussion = ?
    			ORDER BY ' . $sort, $params, null, $post_limit))
            return array();
    } else {
        if (!$posts = $DB->get_records_sql('SELECT p.*' . $tr_sel . ', pf.flag AS postflag
    			FROM {forumx_posts} p
    			INNER JOIN {forumx_discussions} d ON d.id = p.discussion
    			LEFT JOIN {forumx_flags} pf ON (pf.postid = p.id AND pf.userid = ?)
    			' . $tr_join . '
    			WHERE p.discussion = ?
    			ORDER BY ' . $sort, $params, null, $post_limit)) {
            return array();
        }
    }

    foreach ($posts as $pid => $p) {
        if (is_null($p->postflag)) {
            $p->postflag = 0;
        }
        if ($tracking) {
            if (forumx_tp_is_post_old($p)) {
                $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // Parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    // Start with the last child of the first post.
    $post = &$posts[reset($posts)->id];

    $lastpost = false;
    while (!$lastpost) {
        if (!isset($post->children)) {
            $post->lastpost = true;
            $lastpost = true;
        } else {
            // Go to the last child of this post.
            $post = &$posts[end($post->children)->id];
        }
    }

    return $posts;
}

/**
 * Gets a single discussion. Used after creating a new discussion with AJAX.
 * @param stdClass $forum
 * @param stdClass $context
 * @param stdClass $course
 * @param stdClass $cm
 * @param int $discussionid
 * @param int $displaymode
 */
function forumx_get_single_discussion($forum, $context, $course, $cm, $discussionid, $displaymode = null)
{
    global $CFG, $DB, $USER;

    if (is_null($displaymode)) {
        $displaymode = get_user_preferences('forumx_displaymode', $CFG->forumx_displaymode);
    }
    $discussion = $DB->get_record('forumx_discussions', array('id' => $discussionid));
    $post = forumx_get_post_full($discussion->firstpost);
    $canreply = true;
    $canrate = has_capability('mod/forumx:rate', $context);
    switch ($forum->type) {
        case 'single':
            forumx_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate, null, null, $post->id);
            break;

        case 'eachuser':
            forumx_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate, null, null, $post->id);
            break;

        case 'teacher':
            forumx_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate, null, null, $post->id);
            break;

        case 'blog':
            $sort = $displaymode == forumx_MODE_FLATOLDEST ? 'p.created ASC' : 'p.created DESC';
            forumx_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate, null, null, $post->id);
            break;

        default:
            forumx_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate, null, null, $post->id);
            break;
    }
}

/**
 * An array of forum objects that the user is allowed to read/search through.
 *
 * @param int $userid
 * @param int $courseid if 0, we look for forums throughout the whole site.
 * @return array of forum objects, or false if no matches
 *         Forum objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 * @global object
 * @global object
 * @global object
 */
function forumx_get_readable_forums($userid, $courseid = 0)
{

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/course/lib.php');

    if (!$forummod = $DB->get_record('modules', array('name' => 'forumx'))) {
        print_error('notinstalled', 'forumx');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readableforums = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['forumx'])) {
            // Hmm, no forums?
            continue;
        }

        $courseforums = $DB->get_records('forumx', array('course' => $course->id));

        foreach ($modinfo->instances['forumx'] as $forumid => $cm) {
            if (!$cm->uservisible || !isset($courseforums[$forumid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $forum = $courseforums[$forumid];
            $forum->context = $context;
            $forum->cm = $cm;

            if (!has_capability('mod/forumx:viewdiscussion', $context)) {
                continue;
            }

            // Group access.
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {

                $forum->onlygroups = $modinfo->get_groups($cm->groupingid);
                $forum->onlygroups[] = -1;
            }

            // Hidden timed discussions.
            $forum->viewhiddentimedposts = true;
            if (!empty($CFG->forumx_enabletimedposts)) {
                if (!has_capability('mod/forumx:viewhiddentimedposts', $context)) {
                    $forum->viewhiddentimedposts = false;
                }
            }

            // Ganda access.
            if ($forum->type == 'qanda'
                && !has_capability('mod/forumx:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda forum.
                $forum->onlydiscussions = array();  // Holds discussion ids for the discussions
                // the user is allowed to see in this forum.
                if ($discussionspostedin = forumx_discussions_user_has_posted_in($forum->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $forum->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readableforums[$forum->id] = $forum;
        }

        unset($modinfo);

    } // End foreach $courses.

    return $readableforums;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 * @global object
 * @global object
 * @global object
 */
function forumx_search_posts($searchterms, $courseid = 0, $limitfrom = 0, $limitnum = 50,
                             &$totalcount, $extrasql = '')
{
    global $CFG, $DB, $USER;
    require_once($CFG->libdir . '/searchlib.php');

    $forums = forumx_get_readable_forums($USER->id, $courseid);

    if (count($forums) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // DB friendly.

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($forums as $forumid => $forum) {
        $select = array();

        if (!$forum->viewhiddentimedposts) {
            $select[] = '(d.userid = :userid' . $forumid . ' OR (d.timestart < :timestart' . $forumid . ' AND (d.timeend = 0 OR d.timeend > :timeend' . $forumid . ')))';
            $params = array_merge($params, array('userid' . $forumid => $USER->id, 'timestart' . $forumid => $now, 'timeend' . $forumid => $now));
        }

        $cm = $forum->cm;
        $context = $forum->context;

        if ($forum->type == 'qanda'
            && !has_capability('mod/forumx:viewqandawithoutposting', $context)) {
            if (!empty($forum->onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($forum->onlydiscussions, SQL_PARAMS_NAMED, 'qanda' . $forumid . '_');
                $params = array_merge($params, $discussionid_params);
                $select[] = '(d.id ' . $discussionid_sql . ' OR p.parent = 0)';
            } else {
                $select[] = 'p.parent = 0';
            }
        }

        if (!empty($forum->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($forum->onlygroups, SQL_PARAMS_NAMED, 'grps' . $forumid . '_');
            $params = array_merge($params, $groupid_params);
            $select[] = 'd.groupid ' . $groupid_sql;
        }

        if ($select) {
            $selects = implode(' AND ', $select);
            $where[] = '(d.forumx = :forum' . $forumid . ' AND ' . $selects . ')';
            $params['forum' . $forumid] = $forumid;
        } else {
            $fullaccess[] = $forumid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = '(d.forumx ' . $fullid_sql . ')';
    }

    $selectdiscussion = '(' . implode(' OR ', $where) . ')';

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach ($searchterms as $searchterm) {
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"", "\"", $searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
        list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject',
            'p.userid', 'u.id', 'u.firstname',
            'u.lastname', 'p.modified', 'd.forumx');
        $params = array_merge($params, $msparams);
    }

    $fromsql = '{forumx_posts} p,
                  {forumx_discussions} d,
                  {user} u';

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.forumx,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function forumx_get_unmailed_posts($starttime, $endtime, $now = null)
{
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = forumx_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->forumx_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $selectsql = 'AND (p.created >= :ptimestart OR d.timestart >= :pptimestart)';
        $params['pptimestart'] = $starttime;
        $timedsql = 'AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))';
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
        $selectsql = 'AND p.created >= :ptimestart';
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.forumx
                                 FROM {forumx_posts} p
                                 JOIN {forumx_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 $selectsql
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 * @global object
 * @global object
 */
function forumx_mark_old_posts_as_mailed($endtime, $now = null)
{
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = forumx_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = forumx_MAILED_PENDING;

    if (empty($CFG->forumx_enabletimedposts)) {
        return $DB->execute('UPDATE {forumx_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending', $params);
    } else {
        return $DB->execute('UPDATE {forumx_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {forumx_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending', $params);
    }
}

/**
 * Get all the posts for a user in a forum suitable for forumx_print_post
 *
 * @return array
 * @global object
 * @uses CONTEXT_MODULE
 * @global object
 */
function forumx_get_user_posts($forumid, $userid)
{
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumid, $userid);

    if (!empty($CFG->forumx_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumx', $forumid);
        if (!has_capability('mod/forumx:viewhiddentimedposts', context_module::instance($cm->id))) {
            $now = time();
            $timedsql = 'AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))';
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.forumx, $allnames, u.email, u.picture, u.imagealt
                              FROM {forumx} f
                                   JOIN {forumx_discussions} d ON d.forumx = f.id
                                   JOIN {forumx_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @param int $forumid
 * @param int $userid
 * @return array Array or false
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 */
function forumx_get_user_involved_discussions($forumid, $userid)
{
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumid, $userid);
    if (!empty($CFG->forumx_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumx', $forumid);
        if (!has_capability('mod/forumx:viewhiddentimedposts', context_module::instance($cm->id))) {
            $now = time();
            $timedsql = 'AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))';
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
                              FROM {forumx} f
                                   JOIN {forumx_discussions} d ON d.forumx = f.id
                                   JOIN {forumx_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a forum suitable for forumx_print_post
 *
 * @param int $forumid
 * @param int $userid
 * @return array of counts or false
 * @global object
 * @global object
 */
function forumx_count_user_posts($forumid, $userid)
{
    global $CFG, $DB;

    $timedsql = "";
    $params = array($forumid, $userid);
    if (!empty($CFG->forumx_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('forumx', $forumid);
        if (!has_capability('mod/forumx:viewhiddentimedposts', context_module::instance($cm->id))) {
            $now = time();
            $timedsql = 'AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))';
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {forumx} f
                                  JOIN {forumx_discussions} d ON d.forumx = f.id
                                  JOIN {forumx_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the forum post details for it.
 *
 * @param object $log
 * @return array|null
 * @global object
 * @global object
 */
function forumx_get_post_from_log($log)
{
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == 'add post') {

        return $DB->get_record_sql("SELECT p.*, f.type AS forumtype, d.forumx, d.groupid, $allnames, u.email, u.picture
                                 FROM {forumx_discussions} d,
                                      {forumx_posts} p,
                                      {forumx} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forumx", array($log->info));


    } else if ($log->action == 'add discussion') {

        return $DB->get_record_sql("SELECT p.*, f.type AS forumtype, d.forumx, d.groupid, $allnames, u.email, u.picture
                                 FROM {forumx_discussions} d,
                                      {forumx_posts} p,
                                      {forumx} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.forumx", array($log->info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @param int $dicsussionid
 * @return array
 * @global object
 * @global object
 */
function forumx_get_firstpost_from_discussion($discussionid)
{
    global $CFG, $DB;

    return $DB->get_record_sql('SELECT p.*
                             FROM {forumx_discussions} d,
                                  {forumx_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ', array($discussionid));
}

/**
 * Return replies count for each discussion
 *
 * @param int $forumid
 * @param string $forumsort
 * @param int $limit
 * @param int $page
 * @param int $perpage
 * @return array
 */
function forumx_count_discussion_replies($forumid, $forumsort = '', $limit = -1, $page = -1, $perpage = 0)
{
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum = $limit;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitnum = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum = 0;
    }

    if ($forumsort == '') {
        $orderby = '';
        $groupby = '';
    } else {
        $orderby = 'ORDER BY ' . $forumsort;
        $groupby = ', ' . str_replace(array('desc', 'asc'), '', strtolower($forumsort));
    }

    if (($limitfrom == 0 && $limitnum == 0) || $forumsort == '') {
        $sql = 'SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {forumx_posts} p
                       JOIN {forumx_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.forumx = ?
              GROUP BY p.discussion';
        return $DB->get_records_sql($sql, array($forumid));

    } else {
        $sql = 'SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {forumx_posts} p
                       JOIN {forumx_discussions} d ON p.discussion = d.id
                 WHERE d.forumx = ?
              GROUP BY p.discussion ' . $groupby . ' ' . $orderby;
        return $DB->get_records_sql($sql, array($forumid), $limitfrom, $limitnum);
    }
}

/**
 * @param object $forum
 * @param object $cm
 * @param object $course
 * @return mixed
 * @global object
 * @global object
 * @staticvar array $cache
 * @global object
 */
function forumx_count_discussions($forum, $cm, $course)
{
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2); // DB cache friendliness.

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->forumx_enabletimedposts)) {
            $timedsql = 'AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)';
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = '';
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {forumx} f
                       JOIN {forumx_discussions} d ON d.forumx = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$forum->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$forum->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$forum->id];
    }

    require_once($CFG->dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // Add all groups posts.
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $forum->id;

    if (!empty($CFG->forumx_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = '';
    }

    $sql = "SELECT COUNT(d.id)
              FROM {forumx_discussions} d
             WHERE d.groupid $mygroups_sql AND d.forumx = ?
                   $timedsql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Get all discussions in a forum
 *
 * @param object $cm
 * @param string $forumsort
 * @param bool $fullpost
 * @param int $unused
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @param int $groupid if groups enabled, get discussions for this group overriding the current group.
 *                     Use forumx_POSTS_ALL_USER_GROUPS for all the user groups
 * @param bool $ignore_pinned
 * @return array
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 */
function forumx_get_discussions($cm, $forumsort = '', $fullpost = true, $unused = -1, $limit = -1,
                                $userlastmodified = false, $page = -1, $perpage = 0, $groupid = -1, $ignore_pinned = false)
{
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm->instance);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/forumx:viewdiscussion', $modcontext)) { // User must have perms to view discussions.
        return array();
    }

    if (!empty($CFG->forumx_enabletimedposts)) { // Users must fulfill timed posts.

        if (!has_capability('mod/forumx:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))';
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = ?';
                $params[] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum = $limit;
    } else if ($page != -1) {
        $limitfrom = $page * $perpage;
        $limitnum = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum = 0;
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($groupmode) {

        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        // Special case, we received a groupid to override currentgroup.
        if ($groupid > 0) {
            $course = get_course($cm->course);
            if (!groups_group_visible($groupid, $course, $cm)) {
                // User doesn't belong to this group, return nothing.
                return array();
            }
            $currentgroup = $groupid;
        } else if ($groupid === -1) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            // Get discussions for all groups current user can see.
            $currentgroup = null;
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = ? OR d.groupid = -1)';
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            // Separate groups.

            // Get discussions for all groups current user can see.
            if ($currentgroup === null) {
                $mygroups = array_keys(groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id'));
                if (empty($mygroups)) {
                    $groupselect = 'AND d.groupid = -1';
                } else {
                    list($insqlgroups, $inparamsgroups) = $DB->get_in_or_equal($mygroups);
                    $groupselect = 'AND (d.groupid = -1 OR d.groupid ' . $insqlgroups . ')';
                    $params = array_merge($params, $inparamsgroups);
                }
            } else if ($currentgroup) {
                $groupselect = 'AND (d.groupid = ? OR d.groupid = -1)';
                $params[] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    } else {
        $groupselect = "";
    }
    $pinned = $ignore_pinned ? 'AND d.on_top = 0' : '';

    if (empty($forumsort)) {
        $forumsort = forumx_get_default_sort_order();
    }
    if (empty($fullpost)) {
        $postdata = 'p.id,p.subject,p.modified,p.discussion,p.userid';
    } else {
        $postdata = 'p.*';
    }

    if (empty($userlastmodified)) {  // We don't need to know this.
        $umfields = "";
        $umtable = "";
    } else {
        $umfields = ', ' . get_all_user_name_fields(true, 'um', null, 'um') . ', um.email AS umemail, um.picture AS umpicture,
                        um.imagealt AS umimagealt';
        $umtable = ' LEFT JOIN {user} um ON (d.usermodified = um.id)';
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, $allnames,
                   d.firstpost, d.on_top, d.locked,
                   u.email, u.picture, u.imagealt $umfields
              FROM {forumx_discussions} d
                   JOIN {forumx_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.forumx = ? AND p.parent = 0
                   $timelimit $groupselect $pinned
          ORDER BY $forumsort";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Gets the neighbours (previous and next) of a discussion.
 *
 * The calculation is based on the timemodified of the discussion and does not handle
 * the neighbours having an identical timemodified. The reason is that we do not have any
 * other mean to sort the records, e.g. we cannot use IDs as a greater ID can have a lower
 * timemodified.
 *
 * For blog-style forums, the calculation is based on the original creation time of the
 * blog post.
 *
 * Please note that this does not check whether or not the discussion passed is accessible
 * by the user, it simply uses it as a reference to find the neighbours. On the other hand,
 * the returned neighbours are checked and are accessible to the current user.
 *
 * @param object $cm The CM record.
 * @param object $discussion The discussion record.
 * @param object $forum The forum instance record.
 * @return array That always contains the keys 'prev' and 'next'. When there is a result
 *               they contain the record with minimal information such as 'id' and 'name'.
 *               When the neighbour is not found the value is false.
 */
function forumx_get_discussion_neighbours($cm, $discussion, $forum)
{
    global $CFG, $DB, $USER;

    if ($cm->instance != $discussion->forumx or $discussion->forumx != $forum->id or $forum->id != $cm->instance) {
        throw new coding_exception('Discussion is not part of the same forum.');
    }

    $neighbours = array('prev' => false, 'next' => false);
    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    // Users must fulfill timed posts.
    $timelimit = '';
    if (!empty($CFG->forumx_enabletimedposts)) {
        if (!has_capability('mod/forumx:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= :tltimestart AND (d.timeend = 0 OR d.timeend > :tltimeend))';
            $params['tltimestart'] = $now;
            $params['tltimeend'] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = :tluserid';
                $params['tluserid'] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    // Limiting to posts accessible according to groups.
    $groupselect = '';
    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            }
        } else {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    }

    $params['forumid'] = $cm->instance;
    $params['discid1'] = $discussion->id;
    $params['discid2'] = $discussion->id;
    $params['discid3'] = $discussion->id;
    $params['discid4'] = $discussion->id;
    $params['disctimecompare1'] = $discussion->timemodified;
    $params['disctimecompare2'] = $discussion->timemodified;
    $params['pinnedstate1'] = (int)$discussion->on_top;
    $params['pinnedstate2'] = (int)$discussion->on_top;
    $params['pinnedstate3'] = (int)$discussion->on_top;
    $params['pinnedstate4'] = (int)$discussion->on_top;

    $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
    FROM {forumx_discussions} d
    JOIN {forumx_posts} p ON d.firstpost = p.id
    WHERE d.forumx = :forumid
    AND d.id <> :discid1
    $timelimit
    $groupselect";

    $comparefield = 'd.timemodified';
    $comparevalue = ':disctimecompare1';
    $comparevalue2 = ':disctimecompare2';

    if (!empty($CFG->forum_enabletimedposts)) {
        // Here we need to take into account the release time (timestart)
        // if one is set, of the neighbouring posts and compare it to the
        // timestart or timemodified of *this* post depending on if the
        // release date of this post is in the future or not.
        // This stops discussions that appear later because of the
        // timestart value from being buried under discussions that were
        // made afterwards.
        $comparefield = 'CASE WHEN d.timemodified < d.timestart
                                THEN d.timestart ELSE d.timemodified END';
        if ($discussion->timemodified < $discussion->timestart) {
            // Normally we would just use the timemodified for sorting
            // discussion posts. However, when timed discussions are enabled,
            // then posts need to be sorted base on the later of timemodified
            // or the release date of the post (timestart).
            $params['disctimecompare1'] = $discussion->timestart;
            $params['disctimecompare2'] = $discussion->timestart;
        }
    }

    $orderbydesc = forumx_get_default_sort_order(true, $comparefield, 'd', false);
    $orderbyasc = forumx_get_default_sort_order(false, $comparefield, 'd', false);

    if ($forum->type === 'blog') {
        $subselect = 'SELECT pp.created
                  FROM {forumx_discussions} dd
                  JOIN {forumx_posts} pp ON dd.firstpost = pp.id ';

        $subselectwhere1 = ' WHERE dd.id = :discid3';
        $subselectwhere2 = ' WHERE dd.id = :discid4';

        $comparefield = 'p.created';

        $sub1 = $subselect . $subselectwhere1;
        $comparevalue = "($sub1)";

        $sub2 = $subselect . $subselectwhere2;
        $comparevalue2 = "($sub2)";

        $orderbydesc = 'd.on_top, p.created DESC';
        $orderbyasc = 'd.on_top, p.created ASC';
    }

    $prevsql = $sql . " AND ( (($comparefield < $comparevalue) AND :pinnedstate1 = d.on_top)
    OR ($comparefield = $comparevalue2 AND (d.on_top = 0 OR d.on_top = :pinnedstate4) AND d.id < :discid2)
    OR (d.on_top = 0 AND d.on_top <> :pinnedstate2))
    ORDER BY CASE WHEN d.on_top = :pinnedstate3 THEN 1 ELSE 0 END DESC, $orderbydesc, d.id DESC";

    $nextsql = $sql . " AND ( (($comparefield > $comparevalue) AND :pinnedstate1 = d.on_top)
    OR ($comparefield = $comparevalue2 AND (d.on_top = 1 OR d.on_top = :pinnedstate4) AND d.id > :discid2)
    OR (d.on_top = 1 AND d.on_top <> :pinnedstate2))
    ORDER BY CASE WHEN d.on_top = :pinnedstate3 THEN 1 ELSE 0 END DESC, $orderbyasc, d.id ASC";

    $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
    $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);
    return $neighbours;
}

/**
 * Get the sql to use in the ORDER BY clause for forum discussions.
 *
 * This has the ordering take timed discussion windows into account.
 *
 * @param bool $desc True for DESC, False for ASC.
 * @param string $compare The field in the SQL to compare to normally sort by.
 * @param string $prefix The prefix being used for the discussion table.
 * @param bool $on_top sort pinned discussions to the top
 * @return string
 */
function forumx_get_default_sort_order($desc = true, $compare = 'd.timemodified', $prefix = 'd', $on_top = true)
{
    global $CFG;

    if (!empty($prefix)) {
        $prefix .= '.';
    }

    $dir = $desc ? 'DESC' : 'ASC';

    if ($on_top == true) {
        $on_top = "{$prefix}on_top DESC,";
    } else {
        $on_top = '';
    }

    $sort = "{$prefix}timemodified";
    if (!empty($CFG->forumx_enabletimedposts)) {
        $sort = "CASE WHEN {$compare} < {$prefix}timestart
                 THEN {$prefix}on_top DESC, {$prefix}timestart
                 ELSE {$prefix}on_top DESC, {$compare}
                 END";
    }
    return "$on_top $sort $dir";
}

/**
 * Get an array of discussions with their cmount of unread posts
 * @param stdClass $cm
 * @param int $discussionid Linit the query for a single discussion (optional)
 * @return array
 * @uses VISIBLEGROUPS
 * @uses CONTEXT_MODULE
 */
function forumx_get_discussions_unread($cm, $discussionid = null)
{
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->forumx_oldpostdays * DAYSECS);

    $params = array();
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :currentgroup OR d.groupid = -1)';
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = '';
            }
        } else {
            // Separate groups without access all.
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :currentgroup OR d.groupid = -1)';
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    } else {
        $groupselect = '';
    }

    if (!empty($CFG->forumx_enabletimedposts)) {
        $timedsql = 'AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)';
        $params['now1'] = $now;
        $params['now2'] = $now;
    } else {
        $timedsql = '';
    }

    if ($discussionid) {
        $singlediscussion = 'AND d.id = :discussionid';
        $params['discussionid'] = $discussionid;
    } else {
        $singlediscussion = '';
    }
    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {forumx_discussions} d
                   JOIN {forumx_posts} p ON p.discussion = d.id
                   LEFT JOIN {forumx_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.forumx = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
                   $groupselect
                   $timedsql
                   $singlediscussion
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

/**
 * @param object $cm
 * @return array
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @global object
 * @global object
 */
function forumx_get_discussions_count($cm)
{
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = ? OR d.groupid = -1)';
                $params[] = $currentgroup;
            } else {
                $groupselect = '';
            }

        } else {
            // Seprate groups without access all.
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = ? OR d.groupid = -1)';
                $params[] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    } else {
        $groupselect = '';
    }

    $cutoffdate = $now - ($CFG->forumx_oldpostdays * DAYSECS);

    $timelimit = '';

    if (!empty($CFG->forumx_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/forumx:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))';
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = ?';
                $params[] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    $sql = 'SELECT COUNT(d.id)
              FROM {forumx_discussions} d
                   JOIN {forumx_posts} p ON p.discussion = d.id
             WHERE d.forumx = ? AND p.parent = 0
                   ' . $groupselect . ' ' . $timelimit;

    return $DB->get_field_sql($sql, $params);
}


// OTHER FUNCTIONS ///////////////////////////////////////////////////////////


/**
 * @param int $courseid
 * @param string $type
 * @global object
 * @global object
 */
function forumx_get_course_forum($courseid, $type)
{
    // How to set up special 1-per-course forums.
    global $CFG, $DB, $OUTPUT, $USER;

    if ($forums = $DB->get_records_select('forumx', 'course = ? AND type = ?', array($courseid, $type), 'id ASC')) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($forums as $forum) {
            return $forum;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $forum = new stdClass();
    $forum->course = $courseid;
    $forum->type = "$type";
    if (!empty($USER->htmleditor)) {
        $forum->introformat = $USER->htmleditor;
    }
    $create_subscribe = false;

    switch ($forum->type) {
        case 'news':
            $forum->name = get_string('namenews', 'forumx');
            $forum->intro = get_string('intronews', 'forumx');
            //yifatsh #6797
            //$forum->forcesubscribe = forumx_FORCESUBSCRIBE;
            $forum->forcesubscribe = forumx_INITIALSUBSCRIBE;
            $create_subscribe = true;
            $forum->assessed = 0;
            if ($courseid == SITEID) {
                $forum->name = get_string('sitenews');
                $forum->forcesubscribe = 0;
            }
            break;
        case 'social':
            $forum->name = get_string('namesocial', 'forumx');
            $forum->intro = get_string('introsocial', 'forumx');
            $forum->assessed = 0;
            $forum->forcesubscribe = 0;
            break;
        case 'blog':
            $forum->name = get_string('blogforum', 'forumx');
            $forum->intro = get_string('introblog', 'forumx');
            $forum->assessed = 0;
            $forum->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That forum type doesn't exist!");
            return false;
            break;
    }

    $forum->timemodified = time();
    $forum->id = $DB->insert_record('forumx', $forum);


    if (!$module = $DB->get_record('modules', array('name' => 'forumx'))) {
        echo $OUTPUT->notification("Could not find forum module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $forum->id;
    $mod->section = 0;
    include_once($CFG->dirroot . '/course/lib.php');
    if (!$mod->coursemodule = add_course_module($mod)) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);

    if ($create_subscribe) {
        forumx_post_availability_changes($forum->id, true);
    }


    return $DB->get_record('forumx', array('id' => "$forum->id"));
}

/**
 * Print a forum post
 *
 * @param stdClass $post The post to print.
 * @param stdClass $discussion
 * @param stdClass $forum
 * @param stdClass $cm
 * @param stdClass $course
 * @param bool $ownpost Whether this post belongs to the current user.
 * @param bool $reply Whether to print a 'reply' link at the bottom of the message.
 * @param bool $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $postisread true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param bool $dummyifcantsee When forumx_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @param bool $return
 * @param int $displaytype
 * @param bool $is_discussion Is the output in a discussion page or view page
 * @return void
 */
function forumx_print_post($post, $discussion, $forum, &$cm, $course, $ownpost = false,
                           $reply = false, $link = false, $footer = "", $highlight = "", $postisread = null,
                           $dummyifcantsee = true, $istracked = null, $return = false, $displaytype = null, $is_discussion = false)
{
    global $USER, $CFG, $OUTPUT;

    require_once($CFG->libdir . '/filelib.php');
    static $userdate = '';
    if (empty($userdate)) {
        $userdate = get_string('strftimedatetimeshort', 'langconfig');

        $dateregex = "/\s+([%:hHmMsS]+)/";
        if (preg_match($dateregex, $userdate)) {
            $userdate = preg_replace_callback(
                $dateregex,
                function ($matches) {
                    return ' <span class="clean_userdate">' . $matches[1] . '</span>';
                }, $userdate);
        }
    }

    // Strings cache.
    static $str;

    $modcontext = context_module::instance($cm->id);
    $first_post = empty($post->parent) && $displaytype != forumx_DISPLAY_OPEN_CLEAN;

    $post->course = $course->id;
    $post->forum = $forum->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_forumx', 'post', $post->id);
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir . '/plagiarismlib.php');
        $post->message .= plagiarism_get_links(array('userid' => $post->userid,
            'content' => $post->message,
            'cmid' => $cm->id,
            'course' => $post->course,
            'forum' => $post->forum));
    }

    // Caching.
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/forumx:viewdiscussion'] = has_capability('mod/forumx:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames'] = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/forumx:editanypost'] = has_capability('mod/forumx:editanypost', $modcontext);
        $cm->cache->caps['mod/forumx:splitdiscussions'] = has_capability('mod/forumx:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/forumx:deleteownpost'] = has_capability('mod/forumx:deleteownpost', $modcontext);
        $cm->cache->caps['mod/forumx:deleteanypost'] = has_capability('mod/forumx:deleteanypost', $modcontext);
        $cm->cache->caps['mod/forumx:viewanyrating'] = has_capability('mod/forumx:viewanyrating', $modcontext);
        $cm->cache->caps['mod/forumx:exportpost'] = has_capability('mod/forumx:exportpost', $modcontext);
        $cm->cache->caps['mod/forumx:exportownpost'] = has_capability('mod/forumx:exportownpost', $modcontext);
        $cm->cache->caps['mod/forumx:lockmessage'] = has_capability('mod/forumx:lockmessage', $modcontext);
        $cm->cache->caps['mod/forumx:markmessage'] = has_capability('mod/forumx:markmessage', $modcontext);
        $cm->cache->caps['mod/forumx:movemessage'] = has_capability('mod/forumx:movemessage', $modcontext);
        $cm->cache->caps['mod/forumx:updateflag'] = has_capability('mod/forumx:updateflag', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = \core_availability\info_module::is_user_visible($cm, 0, false);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = forumx_tp_is_post_read($USER->id, $post);
    }

    $displaymode = get_user_preferences('forumx_displaymode', $CFG->forumx_displaymode);
    if (!forumx_user_can_see_post($forum, $discussion, $post, null, $cm)) {
        $output = '';
        if (!$dummyifcantsee) {
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        $output .= html_writer::tag('a', '', array('id' => 'p' . $post->id));
        $output .= html_writer::start_tag('div', array('id' => 'post' . $post->id . 'container',
            'class' => 'forumx_post clearfix',
            'role' => 'region',
            'data-postid' => $post->id,
            'aria-label' => get_string('hiddenforumpost', 'forumx')));
        $output .= html_writer::start_tag('div', array('class' => 'row header'));
        $output .= html_writer::tag('div', '', array('class' => 'left picture')); // Picture.
        if ($first_post) {
            $output .= html_writer::start_tag('div', array('class' => 'topic starter'));
        } else {
            $output .= html_writer::start_tag('div', array('class' => 'topic'));
        }
        // Subject.
        $output .= html_writer::tag('div', get_string('forumsubjecthidden', 'forumx'), array('class' => 'subject', 'role' => 'header'));
        // Author.
        $output .= html_writer::tag('div', get_string('forumauthorhidden', 'forumx'), array('class' => 'author', 'role' => 'header'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class' => 'row'));
        // Groups.
        $output .= html_writer::tag('div', '&nbsp;', array('class' => 'left side'));
        $output .= html_writer::tag('div', get_string('forumbodyhidden', 'forumx'), array('class' => 'content')); // Content
        $output .= html_writer::end_tag('div'); // row
        if (!$first_post) {// || ($first_post && $displaymode != forumx_MODE_ONLY_DISCUSSION))
            $output .= html_writer::end_tag('div'); // forumpost.
        }
        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str)) {
        $str = new \stdClass();
        $str->edit = get_string('edit', 'forumx');
        $str->editpost = get_string('editpost', 'forumx');
        $str->delete = get_string('delete', 'forumx');
        $str->reply = get_string('reply', 'forumx');
        $str->parent = get_string('parent', 'forumx');
        $str->moveheading = get_string('pruneheading', 'forumx');
        $str->move = get_string('prune', 'forumx');
        $str->displaymode = forumx_normalize_layout_mode($displaymode);
        $str->markread = get_string('markread', 'forumx');
        $str->markunread = get_string('markunread', 'forumx');
        $str->flagpost = get_string('flagpost', 'forumx');
        $str->unflagpost = get_string('unflagpost', 'forumx');
        $str->linktopost = get_string('linktopost', 'forumx');
        $str->print = get_string('print', 'forumx');
        $str->mailmessage = get_string('sendbymail', 'forumx');
        $str->recommendpost = get_string('recommendpost', 'forumx');
        $str->unrecommendpost = get_string('unrecommendpost', 'forumx');
        $str->discussthistopic = get_string('discussthistopic', 'forumx');
        $str->subscribe_yes = get_string('subscriptiondiscussionyes', 'forumx');
        $str->subscribe_no = get_string('subscriptiondiscussionno', 'forumx');
        $str->closediscussion = get_string('opendiscussionthread', 'forumx');
        $str->opendiscussion = get_string('closediscussionthread', 'forumx');
        $str->discussionislocked = get_string('discussionislocked', 'forumx');
        $str->movepost = get_string('movepost', 'forumx');
        $str->movediscussion = get_string('movediscussion', 'forumx');
        $str->repliesamount = get_string('repliesamount', 'forumx');
        $str->publishdate = get_string('publishdate', 'forumx');
        $str->flag = get_string('flag:level', 'forumx');
        $str->flagnone = get_string('flag:level0', 'forumx');
        $str->flagmenu = get_string('flag:menulevel', 'forumx');
        $str->flagmenunone = get_string('flag:menulevel0', 'forumx');
    }

    $is_opened = (isset($post->active_post) ||
        $str->displaymode == forumx_MODE_FLATNEWEST ||
        $str->displaymode == forumx_MODE_FLATOLDEST ||
        $displaymode == forumx_MODE_ALL ||
        $displaytype == forumx_DISPLAY_OPEN ||
        $displaytype == forumx_DISPLAY_OPEN_CLEAN ||
        $displaytype == forumx_DISPLAY_OPEN_NO_COMMANDS);
    $use_commands = $displaytype != forumx_DISPLAY_OPEN_NO_COMMANDS && $displaytype != forumx_DISPLAY_OPEN_CLEAN;
    $discussionlink = new moodle_url('/mod/forumx/discuss.php', array('d' => $post->discussion));

    // A blog forum behaves differently in view page.
    $is_blog_view = !$is_discussion && $forum->type === 'blog';

    // Build an object that represents the posting user.
    $postuser = new \stdClass();
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;

    // Hide user details when in anonymous mode.
    if ($forum->hideauthor) {
        $postuser->fullname = get_string('forumauthorhidden', 'forumx');
        $postuser->profilelink = '';
        $postuser->roleicon = forumx_user_role_icon($postuser->fullname);
        $postuser->picture = 0;
        $postuser->imagealt = get_string('forumauthorhidden', 'forumx');
    } else {
        $postuser->fullname = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
        $postuser->profilelink = new moodle_url('/user/view.php', array('id' => $post->userid, 'course' => $course->id));
        $postuser->roleicon = forumx_user_role_icon(forumx_get_user_main_role($post->userid, $forum->course));
    }

    // Prepare the groups the posting user belongs to.
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }

    // Prepare the attachements for the post, files then images.
    list($attachments, $attachedimages) = forumx_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post.
    $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->forumx_longpost));

    // Prepare an array of commands.
    $commands = array();
    $commands_menu = array();
    $reply_button = '';
    $single_and_firstpost = $forum->type == 'single' && $discussion->firstpost == $post->id;
    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG->forumx_usermarksread && isloggedin()) {
        /*
		$url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
		$text = $str->markunread;
		if (!$postisread) {
			$url->param('mark', 'read');
			$text = $str->markread;
		}
		if ($str->displaymode == forumx_MODE_THREADED) {
			$url->param('parent', $post->parent);
		} else {
			$url->set_anchor('p'.$post->id);
		}
		$commands[] = array('url'=>$url, 'text'=>$text, 'attributes'=>null);
		*/
    }

    //@todo
    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->created;
    if ($first_post && $forum->type == 'news' && $discussion->timestart > time()) {
        $age = 0;
    }

    if ($single_and_firstpost) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the forum description.
            $commands_menu[] = array('url' => new moodle_url('/course/modedit.php', array('update' => $cm->id, 'sesskey' => sesskey(), 'return' => 1)), 'text' => $str->edit, 'attributes' => null);
        }
    } else {
        $edit_del = $ownpost && $age < $CFG->maxeditingtime;
        if ($edit_del || $cm->cache->caps['mod/forumx:editanypost']) {
            $array = forumx_set_return_in_url(array('edit' => $post->id), $is_discussion);
            $commands_menu[] = array('url' => new moodle_url('/mod/forumx/post.php', $array), 'text' => $str->edit, 'attributes' => null);
        }
        // Do not allow deleting of first post in single simple type.
        if (($edit_del && $cm->cache->caps['mod/forumx:deleteownpost']) || $cm->cache->caps['mod/forumx:deleteanypost']) {
            $array = forumx_set_return_in_url(array('delete' => $post->id), $is_discussion);
            $commands_menu['delete'] = array('url' => new moodle_url('/mod/forumx/post.php', $array), 'text' => $str->delete, 'attributes' => array('text' => $str->delete));
        }
    }

    // Blog mode shows maximum of three options: edit, delete and view discussion
    /*if ($forum->type === 'blog' && !$is_discussion) {
		// delete post
		if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/forumx:deleteownpost']) || $cm->cache->caps['mod/forumx:deleteanypost']) {
			$commands[] = array('url'=>new moodle_url('/mod/forumx/post.php', array('delete'=>$post->id)), 'text'=>$str->delete, 'attributes'=>array('class'=>'forumx_delete'));
		}
		$commands[] = array('url'=>new moodle_url('/mod/forumx/discuss.php?d='.$post->discussion, array()), 'text' => $str->discussthistopic, 'attributes'=>null);
	}
	// Standard commands.
	else {*/
    // Reply.
    if ($reply) {
        $reply_button = forumx_print_post_reply_button($discussion->locked == 1);
    } else if ($is_blog_view) {
        $reply_button = \html_writer::link(new moodle_url('/mod/forumx/discuss.php?d=' . $post->discussion), $str->discussthistopic);
    }

    // Like post.
    $likedata = count_likes($post->id, $post->discussion);
    $userslike = users_likes($post->id, $post->discussion);

    foreach ($userslike as $userlike) {
        $userslikelist .= '<li><a href="' . $userlike->id . '">' . $userlike->firstname . ' ' . $userlike->lastname . '</a></li>';
    }

    $commands[] = html_writer::tag('button', '<span class="bubble">' . $likedata->count . '</span> <ul class="users">' . $userslikelist . '</ul>', array(
        'class' => 'forumx_like' . ($likedata->status ? ' liked' : ''),
        'data-action' => 'likepost',
        'data-actiontype' => 'like',
        //'title' => 'like this post',
        'aria-label' => 'like this post',
        'data-postid' => $post->id));

    // comments count
    $commands[] = html_writer::tag('button', '<span class="bubble">' . ($post->replies ?? 0) . '</span>', array(
        'class' => 'forumx_comments',
        //'title' => 'number of comments',
        'aria-label' => 'number of comments',
        'data-postid' => $post->id));

    // Flag post.
    if ($cm->cache->caps['mod/forumx:updateflag'] && !$is_blog_view) {
        $flag = new \mod_forumx\simpleaction_menu(null, null, $post->id);
        if (empty($post->postflag)) {
            $post->postflag = 0;
        }
        $flag_title = $post->postflag == 0 ? $str->flagmenunone : $str->flagmenu . ' ' . $post->postflag;
        $flag_value = $post->postflag == 0 ? $str->flagnone : $str->flag . ' ' . $post->postflag;
        $flag->set_menu_attributes(array('title' => $flag_title, 'aria-label' => $flag_title));
        $flag->set_menu_icon('');
        $flag->set_grid();
        $flag->flip_side();
        $flag->set_attributes(array(
            'data-postid' => $post->id,
            'data-actiontype' => 'flag',
            'data-flagstatus' => $post->postflag
        ));
        $flag->close_on_click(true);
        $arr = array('role' => 'menuitem', 'data-actiontype' => 'flag');
        $items = array(0, 1, 2, 3);
        foreach ($items as $item) {
            $flag_value = $item == 0 ? $str->flagnone : $str->flag . ' ' . $item;
            $arr['data-flagvalue'] = $item;
            $arr['title'] = $flag_value;
            $arr['aria-label'] = $flag_value;
            $flag->add_item(html_writer::link('#', '', $arr));
        }
        $commands[] = $flag->render();
    }

    // Recommend post.
    if (!$is_blog_view) {
        if ($cm->cache->caps['mod/forumx:markmessage']) {
            $action = '';
            $rec_value = '';
            if ($post->mark == 1) {
                $action = 'unrecommendpost';
                $rec_value = $str->unrecommendpost;
            } else {
                $action = 'recommendpost';
                $rec_value = $str->recommendpost;
            }
            $commands[] = html_writer::tag('button', '', array(
                'class' => 'forumx_recommend',
                'data-action' => $action,
                'data-actiontype' => 'recommend',
                'title' => $rec_value,
                'aria-label' => $rec_value,
                'data-postid' => $post->id));

        } else if ($post->mark == 1) {
            $commands[] = html_writer::span('', 'forumx_recommend icon_status', array(
                'title' => get_string('postrecommend', 'forumx'),
                'aria-label' => get_string('postrecommend', 'forumx')
            ));
        }
    }
    // Pin/lock discussion.
    if ($first_post) {
        if ($cm->cache->caps['mod/forumx:lockmessage']) {
            // Lock discussion.
            $lock_str = 'lock';
            $lock_value = 1;
            if ($discussion->locked == 1) {
                $lock_str = 'unlock';
                $lock_value = -1;
            }
            $commands[] = html_writer::tag('button', '', array(
                'class' => 'forumx_' . $lock_str,
                'data-actiontype' => 'lock',
                'data-action' => $lock_str,
                'data-discussionid' => $discussion->id,
                'title' => get_string($lock_str . 'discussion', 'forumx'),
                'aria-label' => get_string($lock_str . 'discussion', 'forumx')
            ));

            // Pin discussion.
            if (!$is_discussion) {
                $pin_action = $discussion->on_top == 1 ? -1 : 1;
                $pin_action_str = $discussion->on_top == 1 ? 'unpin' : 'pin';
                $pin_str = $discussion->on_top == 1 ? get_string('unpindiscussion', 'forumx') : get_string('pindiscussion', 'forumx');
                $commands[] = html_writer::tag('button', '', array(
                    'class' => 'pin_discussion',
                    'data-action' => $pin_action_str,
                    'data-discussionid' => $discussion->id,
                    'title' => $pin_str));
            }
        } else {
            if ($discussion->locked == 1) {
                $commands[] = html_writer::span('', 'forumx_unlock icon_status', array(
                    'title' => get_string('discussionislocked', 'forumx'),
                    'aria-label' => get_string('discussionislocked', 'forumx')
                ));
            }
            if (!$is_discussion && $discussion->on_top == 1) {
                $commands[] = html_writer::span('', 'unpin_discussion icon_status', array(
                    'title' => get_string('discussionpinned', 'forumx'),
                    'aria-label' => get_string('discussionpinned', 'forumx')
                ));
            }
        }
    }

    if (!$is_blog_view) {
        // Print post.
        $commands_menu['print'] = array('url' => new moodle_url('/mod/forumx/print.php', array('f' => $forum->id, 'p' => $post->id)),
            'text' => $str->print, 'attributes' => array('class' => 'forumx_print'));

        // Link to post.
        $url = new moodle_url($discussionlink, array('p' => $post->id), 'p' . $post->id);
        $commands_menu['link'] = array('url' => $url, 'text' => $str->linktopost, 'attributes' => array('class' => 'forumx_link'));

        // Email post.
        $commands_menu['forward'] = array('url' => new moodle_url('/mod/forumx/forward.php?f=' . $forum->id . '&postid=' . $post->id),
            'text' => $str->mailmessage, 'attributes' => array('class' => 'forumx_email'));


        if (!$single_and_firstpost) {
            // Move post or discussion.
            if ($cm->cache->caps['mod/forumx:splitdiscussions']) {
                $title = $first_post ? $str->movediscussion : $str->movepost;
                $commands_menu['move'] = array('url' => new moodle_url('/mod/forumx/move.php', array('f' => $forum->id, 'p' => $post->id)),
                    'text' => $title, 'attributes' => array('title' => $str->moveheading));
            }
        }
    }
    /*
		// Portfolio.
		if ($CFG->enableportfolios && ($cm->cache->caps['mod/forumx:exportpost'] || ($ownpost && $cm->cache->caps['mod/forumx:exportownpost']))) {
			$p = array('postid' => $post->id);
			require_once($CFG->libdir.'/portfoliolib.php');
			$button = new portfolio_add_button();
			$button->set_callback_options('forumx_portfolio_caller', array('postid' => $post->id), 'mod_forumx');
			if (empty($attachments)) {
				$button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
			} else {
				$button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
			}

			$porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
			if (!empty($porfoliohtml)) {
				$commands_menu[] = $porfoliohtml;
			}
		}*/
    //}
    // Finished building commands


    // Begin output

    $output = '';
    $d_sub = '';

    if ($istracked) {
        if ($postisread) {
            $forumpostclass = ' read';
        } else {
            $forumpostclass = ' unread';
            $output .= html_writer::tag('a', '', array('name' => 'unread'));
        }
    } else {
        // Ignore tracking status if not tracked or tracked param is missing.
        $forumpostclass = '';
    }

    $topicclass = '';
    if ($displaytype != forumx_DISPLAY_OPEN_CLEAN) {
        if ($first_post) {
            $topicclass = ' firstpost starter';
        }

        if (!empty($post->lastpost)) {
            $forumpostclass .= ' lastpost';
        }
    }
    $postlevel = '';
    if (!isset($post->postlevel)) {
        $post->postlevel = 0;
    } else if ($post->postlevel > 0) {
        if ($post->postlevel == 1) {
            $postlevel = ' postlevel1';
        } else {
            $postlevel = ' postlevel2';
        }
    }
    $postlevel .= ' level' . $post->postlevel;
    $closed_post = $is_opened ? '' : ' closed_post';
    $postbyuser = new \stdClass();
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postuser->fullname;
    $discussionbyuser = get_string('postbyuser', 'forumx', $postbyuser);
    $output .= html_writer::start_tag('div', array('id' => 'post' . $post->id . 'container',
        'class' => 'forumx_post clearfix' . $forumpostclass . $topicclass . $closed_post . $postlevel,
        'role' => 'region',
        'data-postid' => $post->id));
    $output .= html_writer::tag('a', '', array('id' => 'p' . $post->id, 'aria-hidden' => 'true', 'class' => 'post_anchor'));
    if ($post->postlevel >= 1 && $displaytype != forumx_DISPLAY_OPEN_CLEAN) {
        $output .= '<div class="of_post_header_container"><div class="of_post_header_clickable of_toggle_addon" data-postid="' . $post->id . '"></div>';
    }
    $output .= html_writer::start_tag('div', array('class' => 'of_post_header clearfix'));

    static $new_icons = array();
    if (empty($new_icons)) {
        $new_icons = forumx_new_post_icons();
    }
    $new_post_icon = '';
    $new_discussion_icon = '';
    $new_firstpost_icon = '';
    // New post icon.
    if ($first_post) {
        if (!empty($discussion->unread) && $discussion->unread != '-') {
            if ($postisread || $discussion->unread > 1) {
                $new_post_icon = $new_icons['discussion'];
            }
        }
        if ($istracked && !$postisread) {
            $new_firstpost_icon = str_replace('{postid}', $post->id, $new_icons['firstpost']);
        }
    } else if (!$is_blog_view) {
        if ($istracked && !$postisread) {
            $new_post_icon = str_replace('{postid}', $post->id, $new_icons['post']);
        }
    }
    $output .= '<div class="of_post_title"><div class="post_title_clickable of_toggle_addon" data-postid="' . $post->id . '"></div>' . $new_post_icon;
    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    if ($displaytype != forumx_DISPLAY_OPEN_CLEAN &&
        /*!$is_discussion && */ $first_post && (!is_guest($modcontext, $USER) && isloggedin())) {
        // Discussion subscription.
        if (\mod_forumx\subscriptions::is_subscribable($forum)) {
            // Discussion subscription is not available if the user is subscribed to the forum.
            if (!\mod_forumx\subscriptions::is_subscribed($USER->id, $forum)) {
                $d_sub = forumx_print_discussion_subscription_options($forum, $post->discussion);
            }
        }
    }

    $button_div = '';
    // Display the open/close button.
    if ($first_post && !$is_blog_view &&
        ($str->displaymode == forumx_MODE_NESTED || $str->displaymode == forumx_MODE_ALL || $str->displaymode == forumx_MODE_ONLY_DISCUSSION) &&
        $displaytype != forumx_DISPLAY_OPEN_CLEAN) {
        $collapsed = $is_opened ? 'open' : 'close';
        $title = $collapsed . 'discussion';
        $button_class = $is_opened ? ' d_opened' : '';
        $button_div = '<div class="discussion_button_container">' . html_writer::tag('button', '', array(
                'id' => 'd' . $post->discussion,
                'class' => 'discussion_button' . $button_class,
                'title' => $str->${'title'},
                'aria-label' => $str->${'title'},
                'data-discussionid' => $post->discussion)) . '</div>';
    }
    if ($first_post) {
        $output .= '<div class="of_discussion_header">' . $button_div;
    }
    $postsubject = $post->subject;
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    if ($first_post) {
        $output .= '<div class="discussion_subject">';
    }
    $title_level = $first_post ? '3' : '4';
    if ($displaytype != forumx_DISPLAY_OPEN_CLEAN) {
        $post_title = html_writer::tag('h' . $title_level, $postsubject,
            array('id' => 'post' . $post->id,
                'class' => 'post_subject',
                'data-postid' => $post->id,
                'aria-controls' => 'postmain' . $post->id,
                'tabindex' => '0',
                'aria-expanded' => $is_opened ? 'true' : 'false'));
    } else {
        $post_title = '<span class="post_nolink">' . $postsubject . '</span>';
    }
    $output .= html_writer::tag('span', $post_title, array('class' => 'subject'));

    if (!empty($attachments)) {// && $str->displaymode != forumx_MODE_ONLY_DISCUSSION) {
        $icon_text = $first_post ? 'firstpostattachments' : 'postattachments';
        $output .= forumx_print_post_icon('', 'i/attachment', '', get_string($icon_text, 'forumx'));
    }
    if (empty($post->message)) {
        $icon_text = $first_post ? 'firstpostempty' : 'postempty';
        $output .= '<span class="for-sr">' . get_string($icon_text, 'forumx') . '</span><span class="of_nocontent" aria-hidden="true"> ' . get_string('emptyposttitle', 'forumx') . '</span>';
    }

    if (isset($post->replies) && $str->displaymode != forumx_MODE_THREADED) {// Only parent post have this.
        $icon_text = $post->replies > 1 ? get_string('discussionreplies', 'forumx', $post->replies) : get_string('discussionreply', 'forumx');
        $output .= '<span class="for-sr">' . $icon_text . '</span><span aria-hidden="true" class="numbers">(' . $post->replies . ')</span>';
    } else {
        $output .= '<span class="for-sr"></span><span aria-hidden="true" class="numbers"></span>';
    }

    if ($first_post) {
        $output .= '</div></div>';
    }
    $by = new stdClass();
    $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    $by->date = userdate($post->modified);
    $output .= html_writer::end_tag('div'); // Topic.


    $output .= '<div class="of_post_subtitle">' . html_writer::start_div('author');

    if ($forum->hideauthor) {
        $name = $postuser->roleicon;
    } else {
        $name = html_writer::link($postuser->profilelink, $postuser->fullname . $postuser->roleicon, array('class' => 'linkable'));
    }

    // auther by
    $autherby = ($post->parent == 0 ? '<span class="autherby">נפתח על ידי:</span>' : '');

    $output .= ' <span class="of_post_author">' . $autherby . '<span class="for-sr">' . get_string('by', 'forumx') . '</span>' . $name . '</span>';

    // date
    $output .= html_writer::tag('span', '<span class="for-sr">' . $str->publishdate . '</span>' .
        userdate($post->created, $userdate, null, false), array('class' => 'of_posttime'));
    //$output.= html_writer::tag('span', $name, array('class'=>'of_post_author'));

    /*
	$groupoutput = '';
	if ($groups) {
		$groupoutput = print_group_picture($groups, $course->id, false, true, true);
	}
	if (empty($groupoutput)) {
		$groupoutput = '&nbsp;';
	}
	$output.= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));
*/
    $output .= html_writer::end_tag('div'); //author


    if ($first_post && !empty($new_discussion_icon)) {
        $output .= $new_discussion_icon;
    }
    $output .= $new_firstpost_icon;
    $output .= '<div class="of_post_icons float_end">';
    if ($use_commands) {
        $output .= implode('', array_reverse($commands));
    }
    $output .= '</div>';

    $output .= '</div>'; //subtitle

    $output .= html_writer::end_tag('div'); //row
    if ($post->postlevel >= 1) {
        $output .= '</div>';
    }

    $output .= html_writer::start_tag('div', array('class' => 'of_post_main' . $closed_post,
        'id' => 'postmain' . $post->id,
        'aria-hidden' => $is_opened ? 'false' : 'true'));

    $output .= '<div class="of_post_body_side float_start align_end">
	<span class="postauthor">' .
        $OUTPUT->user_picture($postuser, array('courseid' => $course->id, 'size' => 1, 'link' => false)) .
        '</span></div>';
    $output .= html_writer::start_tag('div', array('class' => 'of_post_body'));

    $options = new stdClass;
    $options->para = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;

    if ($shortenpost) {
        // Prepare shortened version by filtering the text then shortening it.
        $postclass = 'shortenedpost';
        $postcontent = format_text($post->message, $post->messageformat, $options);
        $postcontent = shorten_text($postcontent, $CFG->forumx_shortpost);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'forumx'));
        $postcontent .= html_writer::tag('div', '(' . get_string('numwords', 'moodle', count_words($post->message)) . ')',
            array('class' => 'post-word-count'));
    } else {
        // Prepare whole post.
        $postclass = 'fullpost';
        $postcontent = format_text($post->message, $post->messageformat, $options, $course->id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($forum->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class' => 'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class' => 'attachedimages'));
    }

    // replace @user@ to user profile link
    $postcontent = find_user_link($postcontent);

    $output .= html_writer::tag('article', $postcontent, array('id' => 'postcontent' . $post->id, 'class' => 'post_content clearfix'));
    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class' => 'attachments'));
    }

    $output .= html_writer::end_tag('div'); // Close post_body.

    // Clean display has no commands and ratings, so there's no need for the footer element.
    if ($displaytype != forumx_DISPLAY_OPEN_CLEAN) {
        $output .= html_writer::start_tag('div', array('class' => 'of_post_footer'));

        $output .= $reply_button;

        if ($use_commands) {

            $rendered_menu = '';
            if (!empty($commands_menu)) {
                $menu = new \mod_forumx\simpleaction_menu(null, null, $post->id);
                //$menu->set_menu_title('', get_string('moreoptions', 'forumx'));
                $menu->set_menu_attributes(array('title' => get_string('moreoptions', 'forumx'), 'aria-label' => get_string('moreoptions', 'forumx')));
                $menu->set_menu_icon('');
                $menu->add_class('float_end post_menu');
                $menu->flip_side();
                $menu->close_on_click(true);
                foreach ($commands_menu as $key => $command) {
                    if (is_array($command)) {
                        $command['attributes']['data-action'] = $key;
                        $command['attributes']['role'] = 'menuitem';
                        $menu->add_item(html_writer::link($command['url'], $command['text'], $command['attributes']));
                    } else {
                        $menu->add_item($command);
                    }
                }
                $rendered_menu = $menu->render();
            }

            $output .= html_writer::tag('div', $rendered_menu, array('class' => 'commands post_commands float_end'));
            $output .= '<div class="sub_discussion float_end" data-discussionid="' . $discussion->id . '">' . $d_sub . '</div>';
        }
        // Some places, like search results, don't need the rating option.
        if (!empty($post->rating)) {
            $output .= html_writer::tag('div', $OUTPUT->render($post->rating), array('class' => 'post_rating_container align_end'));
        }

        $output .= html_writer::end_tag('div'); // close post_footer
    }
    $output .= html_writer::end_tag('div'); // Close post_main.

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class' => 'of_post_footer_addon'));
    }
    //if (!$first_post)// || ($first_post && $displaymode != forumx_MODE_ONLY_DISCUSSION))
    $output .= html_writer::end_tag('div'); // forumpost

    // Mark the forum post as read if required
    //    if ($istracked && !$CFG->forumx_usermarksread && !$postisread) {
    //        forumx_tp_mark_post_read($USER->id, $post, $forum->id);
    //    }

    if ($return) {
        return $output;
    }
    echo $output;
}

/**
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function forumx_rating_permissions($contextid, $component, $ratingarea)
{
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_forumx' || $ratingarea != 'post') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view' => has_capability('mod/forumx:viewrating', $context),
        'viewany' => has_capability('mod/forumx:viewanyrating', $context),
        'viewall' => has_capability('mod/forumx:viewallratings', $context),
        'rate' => has_capability('mod/forumx:rate', $context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_forumx [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating [required]
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function forumx_rating_validate($params)
{
    global $DB, $USER;

    // Check the component is mod_forumx
    if ($params['component'] != 'mod_forumx') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in forum)
    if ($params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call forumx_user_can_see_post
    $post = $DB->get_record('forumx_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $forum = $DB->get_record('forumx', array('id' => $discussion->forumx), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the forum
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    if ($forum->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($forum->assesstimestart) && !empty($forum->assesstimefinish)) {
        if ($post->created < $forum->assesstimestart || $post->created > $forum->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0 && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($forum->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$forum->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $forum->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion->groupid > 0 && $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) && !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!forumx_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
        throw new rating_exception('nopermissiontorate');
    }

    return true;
}

/**
 * Can the current user see ratings for a given itemid?
 *
 * @param array $params submitted data
 *            contextid => int contextid [required]
 *            component => The component for this module - should always be mod_forumx [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int scale id [optional]
 * @return bool
 * @throws coding_exception
 * @throws rating_exception
 */
function mod_forumx_rating_can_see_item_ratings($params)
{
    global $DB, $USER;

    // Check the component is mod_forumx.
    if (!isset($params['component']) || $params['component'] != 'mod_forumx') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in forum).
    if (!isset($params['ratingarea']) || $params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    if (!isset($params['itemid'])) {
        throw new rating_exception('invaliditemid');
    }

    $post = $DB->get_record('forumx_posts', array('id' => $params['itemid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $forum = $DB->get_record('forumx', array('id' => $discussion->forumx), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id, false, MUST_EXIST);

    // Perform some final capability checks.
    if (!forumx_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
        return false;
    }
    return true;
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 *
 * @param int $id forum id if $forumtype is 'single',
 *              discussion id for any other forum type
 * @param mixed $mode forum layout mode
 * @param bool $isforum is a forum view page or a discussion page
 * @param string $forumtype optional
 */
function forumx_print_mode_form($id, $mode, $isforum = true, $forumtype = '')
{
    global $OUTPUT;
    if ($isforum || $forumtype == 'single') {
        $select = new single_select(new moodle_url("/mod/forumx/view.php", array('f' => $id)),
            'mode', forumx_get_layout_modes(), $mode, null, "mode");
    } else {
        $select = new single_select(new moodle_url("/mod/forumx/discuss.php", array('d' => $id)),
            'mode', forumx_get_layout_modes(false), $mode, null, "mode");
    }
    $select->set_label(get_string('displaymode', 'forumx'), array('class' => 'accesshide'));
    $select->class = "forummode";
    echo $OUTPUT->render($select);
}

/**
 * @param object $course
 * @param string $search
 * @return string
 * @global object
 */
function forumx_search_form($course, $search = '', $show_advanced = true, $placeholder = null)
{
    global $CFG, $OUTPUT;

    $search_placeholder = empty($placeholder) ? '' : ' placeholder="' . $placeholder . '"';

    $output = '<div class="forumxsearch">';
    $output .= '<form action="' . $CFG->wwwroot . '/mod/forumx/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= $OUTPUT->help_icon('search');
    $output .= '<label class="accesshide" for="search" >' . get_string('search', 'forumx') . '</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="' . s($search, true) . '"' . $search_placeholder . ' />';
    $output .= '<label class="accesshide" for="searchforums" >' . get_string('searchforums', 'forumx') . '</label>';
    $output .= '<input id="searchforums" value="' . get_string('searchforums', 'forumx') . '" type="submit" />';
    $output .= '<input name="id" type="hidden" value="' . $course->id . '" />';
    if ($show_advanced) {
        $output .= '<a id="searchadvanced" href="' . $CFG->wwwroot . '/mod/forumx/search.php?id=' . $course->id . '&showform=1" class="nowrap">' . get_string('advancedsearch', 'forumx') . '</a>';
    }
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}


/**
 * This function is not used and kept for backward capability.
 * Use forumx_get_referer() instead.
 *
 * @global object
 * @global object
 */
function forumx_set_return()
{
    global $CFG, $SESSION;

    if (!isset($SESSION->fromouildiscussion)) {
        $referer = get_local_referer(false);
        // If the referer is NOT a login screen then save it.
        if (!strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromouildiscussion = $referer;
        }
    }
}


/**
 * This function is not used and kept for backward capability.
 * Use forumx_get_referer() instead.
 *
 * @param string|\moodle_url $default
 * @return string
 * @global object
 */
function forumx_go_back_to($default)
{
    global $SESSION;

    if (!empty($SESSION->fromouildiscussion)) {
        $returnto = $SESSION->fromouildiscussion;
        unset($SESSION->fromouildiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $forumto,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new forum directory.
 *
 * @param object $discussion
 * @param int $forumfrom source forum id
 * @param int $forumto target forum id
 * @return bool success
 * @global object
 */
function forumx_move_attachments($discussion, $forumfrom, $forumto)
{
    global $DB;

    if ($forumfrom == $forumto) {
        return true; // Do nothing.
    }
    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('forumx', $forumto);
    $oldcm = get_coursemodule_from_instance('forumx', $forumfrom);

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // Loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('forumx_posts', array('discussion' => $discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id,
                $newcontext->id, 'mod_forumx', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id,
                $newcontext->id, 'mod_forumx', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it.
                $post->attachment = '1';
                $DB->update_record('forumx_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it.
                $post->attachment = '';
                $DB->update_record('forumx_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 * @global object
 * @global object
 * @global object
 */
function forumx_print_attachments($post, $cm, $type)
{
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'forumx');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && (has_capability('mod/forumx:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/forumx:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir . '/portfoliolib.php');
    }

    // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
    // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
    $files = $fs->get_area_files($context->id, 'mod_forumx', 'attachment', $post->id, "filename", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot . '/pluginfile.php', '/' . $context->id . '/mod_forumx/attachment/' . $post->id . '/' . $filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">" . s($filename) . "</a>";
                if ($canexport) {
                    $button->set_callback_options('forumx_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_forumx');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br>";

            } else if ($type == 'text') {
                $output .= "$strattachment " . s($filename) . ":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('forumx_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_forumx');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">" . s($filename) . "</a>", FORMAT_HTML, array('context' => $context));
                    if ($canexport) {
                        $button->set_callback_options('forumx_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_forumx');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br>';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir . '/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $cm->course,
                    'forum' => $cm->instance));
                $output .= '<br>';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 * @package  mod_forumx
 * @category files
 */
function forumx_get_file_areas($course, $cm, $context)
{
    return array(
        'attachment' => get_string('areaattachment', 'mod_forumx'),
        'post' => get_string('areapost', 'mod_forumx'),
    );
}

/**
 * File browsing support for forum module.
 *
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 * @package  mod_forumx
 * @category files
 */
function forumx_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename)
{
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that forumx_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda forum type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/forumx:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot . '/mod/forumx/locallib.php');
        return new forumx_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and forum. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('forumx_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['forumx']) && $cached['forumx']->id == $cm->instance) {
        $forum = $cached['forumx'];
    } else if ($forum = $DB->get_record('forumx', array('id' => $cm->instance))) {
        $cached['forumx'] = $forum;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_forumx', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!forumx_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot . '/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the forum attachments. Implements needed access control ;-)
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 * @package  mod_forumx
 * @category files
 */
function forumx_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array())
{
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = forumx_get_file_areas($course, $cm, $context);

    // Filearea must contain a real area.
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB->get_record('forumx_posts', array('id' => $postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion))) {
        return false;
    }

    if (!$forum = $DB->get_record('forumx', array('id' => $cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_forumx/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) && !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!forumx_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // Finally send the file.
    send_stored_file($file, 0, 0, true, $options); // Download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file.
 *
 * @param object $post is a full post record, including course and forum
 * @param object $forum
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @return bool
 * @global object
 */
function forumx_add_attachment($post, $forum, $cm, $mform = null, $unused = null)
{
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true; // Nothing to do.
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount'] > 0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_forumx', 'attachment', $post->id,
        mod_forumx_post_form::attachment_options($forum));

    $DB->set_field('forumx_posts', 'attachment', $present, array('id' => $post->id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused formerly $message, renamed in 2.8 as it was unused.
 * @return int
 * @global object
 * @global object
 * @global object
 */
function forumx_add_new_post($post, $mform, $unused = null)
{
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion));
    $forum = $DB->get_record('forumx', array('id' => $discussion->forumx));
    $cm = get_coursemodule_from_instance('forumx', $forum->id);
    $context = context_module::instance($cm->id);

    $post->created = $post->modified = time();
    $post->mailed = forumx_MAILED_PENDING;
    $post->userid = $USER->id;
    $post->attachment = "";
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }
    if (!isset($post->mailnow)) {
        $post->mailnow = 0;
    }

    $post->id = $DB->insert_record('forumx_posts', $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forumx', 'post', $post->id,
        mod_forumx_post_form::editor_options($context, null), $post->message);
    $DB->set_field('forumx_posts', 'message', $post->message, array('id' => $post->id));
    forumx_add_attachment($post, $forum, $cm, $mform);

    // Update discussion modified date.
    $DB->set_field('forumx_discussions', 'timemodified', $post->modified, array('id' => $post->discussion));
    $DB->set_field('forumx_discussions', 'usermodified', $post->userid, array('id' => $post->discussion));

    if (forumx_tp_can_track_forums($forum) && forumx_tp_is_tracked($forum)) {
        forumx_tp_mark_post_read($post->userid, $post, $post->forum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection).
    forumx_trigger_content_uploaded_event($post, $cm, 'forumx_add_new_post');

    return $post->id;
}

/**
 * Update a post
 *
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return bool
 * @global object
 * @global object
 * @global object
 */
function forumx_update_post($post, $mform, &$message)
{
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion));
    $forum = $DB->get_record('forumx', array('id' => $discussion->forumx));
    $cm = get_coursemodule_from_instance('forumx', $forum->id);
    $context = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('forumx_posts', $post);

    $discussion->timemodified = $post->modified; // Last modified tracking.
    $discussion->usermodified = $post->userid;   // Last modified tracking.

    if (!$post->parent) { // Post is a discussion starter - update discussion title and times too.
        $discussion->name = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend = $post->timeend;
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_forumx', 'post', $post->id,
        mod_forumx_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('forumx_posts', 'message', $post->message, array('id' => $post->id));

    $DB->update_record('forumx_discussions', $discussion);

    forumx_add_attachment($post, $forum, $cm, $mform, $message);

    if (forumx_tp_can_track_forums($forum) && forumx_tp_is_tracked($forum)) {
        forumx_tp_mark_post_read($post->userid, $post, $post->forum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection).
    forumx_trigger_content_uploaded_event($post, $cm, 'forumx_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @return object
 */
function forumx_add_discussion($discussion, $mform = null, $unused = null, $userid = null)
{
    global $USER, $CFG, $DB;

    $timenow = time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $forum = $DB->get_record('forumx', array('id' => $discussion->forumx));
    $cm = get_coursemodule_from_instance('forumx', $forum->id);

    $post = new stdClass();
    $post->discussion = 0;
    $post->parent = 0;
    $post->userid = $userid;
    $post->created = $timenow;
    $post->modified = $timenow;
    $post->mailed = forumx_MAILED_PENDING;
    $post->subject = $discussion->name;
    $post->message = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust = $discussion->messagetrust;
    $post->attachments = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->forum = $forum->id;     // Speedup.
    $post->course = $forum->course; // Speedup.
    $post->mailnow = $discussion->mailnow;

    $post->id = $DB->insert_record("forumx_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_forumx', 'post', $post->id,
            mod_forumx_post_form::editor_options($context, null), $post->message);
        $DB->set_field('forumx_posts', 'message', $text, array('id' => $post->id));
    }

    // Now do the main entry for the discussion, linking to this first post.
    $discussion->firstpost = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid = $userid;
    $discussion->assessed = 0;

    $post->discussion = $DB->insert_record("forumx_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("forumx_posts", "discussion", $post->discussion, array("id" => $post->id));

    if (!empty($cm->id)) {
        forumx_add_attachment($post, $forum, $cm, $mform, $unused);
    }

    if (forumx_tp_can_track_forums($forum) && forumx_tp_is_tracked($forum)) {
        forumx_tp_mark_post_read($post->userid, $post, $post->forum);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection).
    if (!empty($cm->id)) {
        forumx_trigger_content_uploaded_event($post, $cm, 'forumx_add_discussion');
    }

    return $post->discussion;
}

/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire forum
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forum Forum
 * @return bool
 * @global object
 */
function forumx_delete_discussion($discussion, $fulldelete, $course, $cm, $forum)
{
    global $DB, $CFG;
    require_once($CFG->libdir . '/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records('forumx_posts', array('discussion' => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->forum = $discussion->forumx;
            if (!forumx_delete_post($post, 'ignore', $course, $cm, $forum, $fulldelete)) {
                $result = false;
            }
        }
    }

    forumx_tp_delete_read_records(-1, -1, $discussion->id);

    // Discussion subscriptions must be removed before discussions because of key constraints.
    $DB->delete_records('forumx_discussion_sub', array('discussionid' => $discussion->id));
    if (!$DB->delete_records('forumx_discussions', array('id' => $discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
            ($forum->completiondiscussions || $forum->completionreplies || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}

/**
 * Deletes a single forum post.
 *
 * @param object $post Forum post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $forum Forum
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire forum anyway.
 * @return bool
 * @global object
 */
function forumx_delete_post($post, $children, $course, $cm, $forum, $skipcompletion = false)
{
    global $DB, $CFG, $USER;
    require_once($CFG->libdir . '/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('forumx_posts', array('parent' => $post->id)))) {
        if ($children === 'reorder') {
            foreach ($childposts as $childpost) {
                $childpost->parent = $post->parent;
                $DB->update_record('forumx_posts', $childpost);
            }
        } else if ($children) {
            foreach ($childposts as $childpost) {
                forumx_delete_post($childpost, true, $course, $cm, $forum, $skipcompletion);
            }
        } else {
            return false;
        }
    }

    // Delete ratings.
    require_once($CFG->dirroot . '/rating/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_forumx';
    $delopt->ratingarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new rating_manager();
    $rm->delete_ratings($delopt);

    // Delete attachments.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_forumx', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_forumx', 'post', $post->id);

    // Delete cached RSS feeds.
    if (!empty($CFG->enablerssfeeds)) {
        require_once($CFG->dirroot . '/mod/forumx/rsslib.php');
        forumx_rss_delete_file($forum);
    }

    if ($DB->delete_records('forumx_posts', array('id' => $post->id))) {

        forumx_tp_delete_read_records(-1, $post->id);

        // Just in case we are deleting the last post.
        forumx_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing
        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
                ($forum->completiondiscussions || $forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        \mod_forumx\post_actions::remove_flags($post->id);
        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $post->discussion,
                'forumid' => $forum->id,
                'forumtype' => $forum->type,
            )
        );
        if ($post->userid !== $USER->id) {
            $params['relateduserid'] = $post->userid;
        }
        $event = \mod_forumx\event\post_deleted::create($params);
        $event->add_record_snapshot('forumx_posts', $post);
        $event->trigger();

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Forum post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
 */
function forumx_trigger_content_uploaded_event($post, $cm, $name)
{
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_forumx', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'pathnamehashes' => array_keys($files),
            'discussionid' => $post->discussion,
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_forumx\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @param object $post
 * @param bool $children
 * @return int
 * @global object
 */
function forumx_count_replies($post, $children = true)
{
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB->get_records('forumx_posts', array('parent' => $post->id))) {
            foreach ($childposts as $childpost) {
                $count++; // For this child.
                $count += forumx_count_replies($childpost, true);
            }
        }
    } else {
        $count += $DB->count_records('forumx_posts', array('parent' => $post->id));
    }

    return $count;
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @param object $fromform The submitted form
 * @param stdClass $forum The forum record
 * @param stdClass $discussion The forum discussion record
 * @return string
 */
function forumx_post_subscription($fromform, $forum, $discussion)
{
    global $USER;

    if (\mod_forumx\subscriptions::is_forcesubscribed($forum)) {
        return "";
    } else if (\mod_forumx\subscriptions::subscription_disabled($forum)) {
        $subscribed = \mod_forumx\subscriptions::is_subscribed($USER->id, $forum);
        if ($subscribed && !has_capability('moodle/course:manageactivities', context_course::instance($forum->course), $USER->id)) {
            // This user should not be subscribed to the forum.
            \mod_forumx\subscriptions::unsubscribe_user($USER->id, $forum);
        }
        return '';
    }

    $info = new stdClass();
    $info->name = fullname($USER);
    $info->discussion = format_string($discussion->name);
    $info->forum = format_string($forum->name);

    if (isset($fromform->discussionsubscribe) && $fromform->discussionsubscribe) {
        if ($result = \mod_forumx\subscriptions::subscribe_user_to_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnowsubscribed', 'forumx', $info));
        }
    } else {
        if ($result = \mod_forumx\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnownotsubscribed', 'forumx', $info));
        }
    }

    return '';
}

/**
 * Returns true if user has already created a new discussion.
 *
 * @param int $forumid
 * @param int $userid
 * @return bool
 * @global object
 * @global object
 */
function forumx_user_has_posted_discussion($forumid, $userid)
{
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {forumx_discussions} d, {forumx_posts} p
             WHERE d.forumx = ? AND p.discussion = d.id AND p.parent = 0 and p.userid = ?";

    return $DB->record_exists_sql($sql, array($forumid, $userid));
}

/**
 * @param int $forumid
 * @param int $userid
 * @return array
 * @global object
 * @global object
 */
function forumx_discussions_user_has_posted_in($forumid, $userid)
{
    global $CFG, $DB;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {forumx_posts} p,
                            {forumx_discussions} d
                      WHERE p.discussion = d.id
                        AND d.forumx = ?
                        AND p.userid = ?";

    return $DB->get_records_sql($haspostedsql, array($forumid, $userid));
}

/**
 * @param int $forumid
 * @param int $did
 * @param int $userid
 * @return bool
 * @global object
 * @global object
 */
function forumx_user_has_posted($forumid, $did, $userid)
{
    global $DB;

    if (empty($did)) {
        // Posted in any forum discussion?
        $sql = "SELECT 'x'
                  FROM {forumx_posts} p
                  JOIN {forumx_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.forumx = :forumid";
        return $DB->record_exists_sql($sql, array('forumid' => $forumid, 'userid' => $userid));
    } else {
        return $DB->record_exists('forumx_posts', array('discussion' => $did, 'userid' => $userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 * @global object $DB
 */
function forumx_get_user_posted_time($did, $userid)
{
    global $DB;

    $posttime = $DB->get_field('forumx_posts', 'MIN(created)', array('userid' => $userid, 'discussion' => $did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @param object $forum
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 * @global object
 */
function forumx_user_can_post_discussion($forum, $currentgroup = null, $unused = -1, $cm = NULL, $context = NULL)
{
    global $USER;

    // Shortcut - guest and not-logged-in users can not post.
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    // Moved up here to save potentially unnecessary capability check.
    if ($forum->type == 'single') {
        return false;
    }

    if ($forum->type == 'news') {
        $capname = 'mod/forumx:addnews';
    } else if ($forum->type == 'qanda') {
        $capname = 'mod/forumx:addquestion';
    } else {
        $capname = 'mod/forumx:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($forum->type == 'eachuser') {
        if (forumx_user_has_posted_discussion($forum->id, $USER->id)) {
            return false;
        }
    }

    if (!$groupmode || has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // No group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a forum
 * discussion. Use forumx_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @param object $forum forum object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 */
function forumx_user_can_post($forum, $discussion, $user = NULL, $cm = NULL, $course = NULL, $context = NULL)
{
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // Shortcut - guest and not-logged-in users can not post.
    if (isguestuser($user) || empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // Normal users with temporary guest access can not post, suspended users can not post either.
    if (!is_viewing($context, $user->id) && !is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($forum->type == 'news') {
        $capname = 'mod/forumx:replynews';
    } else {
        $capname = 'mod/forumx:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // Allow students to reply to all participants discussions - this was not possible in Moodle <1.8.
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        // Separate groups.
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}

/**
 * Check to ensure a user can view a timed discussion.
 *
 * @param object $discussion
 * @param object $user
 * @param object $context
 * @return boolean returns true if they can view post, false otherwise
 */
function forumx_user_can_see_timed_discussion($discussion, $user, $context)
{
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG->forumx_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time)
            || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/forumx:viewhiddentimedposts', $context, $user->id)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Check to ensure a user can view a group discussion.
 *
 * @param object $discussion
 * @param object $cm
 * @param object $context
 * @return boolean returns true if they can view post, false otherwise
 */
function forumx_user_can_see_group_discussion($discussion, $cm, $context)
{

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @param object $forum
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 * @uses DEBUG_DEVELOPER
 * @global object
 * @global object
 */
function forumx_user_can_see_discussion($forum, $discussion, $context, $user = NULL)
{
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($forum)) {
        debugging('missing full forum', DEBUG_DEVELOPER);
        if (!$forum = $DB->get_record('forumx', array('id' => $forum))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/forumx:viewdiscussion', $context)) {
        return false;
    }

    if (!forumx_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!forumx_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    return true;
}

/**
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 * @global object
 * @global object
 */
function forumx_user_can_see_post($forum, $discussion, $post, $user = NULL, $cm = NULL)
{
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($forum)) {
        debugging('missing full forum', DEBUG_DEVELOPER);
        if (!$forum = $DB->get_record('forumx', array('id' => $forum))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('forumx_posts', array('id' => $post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm->cache->caps['mod/forumx:viewdiscussion']) || has_capability('mod/forumx:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)) {
            return false;
        }
    }

    if (!forumx_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!forumx_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if ($forum->type == 'qanda') {
        $firstpost = forumx_get_firstpost_from_discussion($discussion->id);
        $userfirstpost = forumx_get_user_posted_time($discussion->id, $user->id);

        // Don't let the user wait the editing time in order to view the other posts.
        return (($userfirstpost !== false /*&& (time() - $userfirstpost >= $CFG->maxeditingtime)*/) ||
            $firstpost->id == $post->id || $post->userid == $user->id || $firstpost->userid == $user->id ||
            has_capability('mod/forumx:viewqandawithoutposting', $modcontext, $user->id));
    }
    return true;
}


/**
 * Prints the discussion view screen for a forum.
 *
 * @param object $course The current course object.
 * @param object $forum Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the forum (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 * @param boolean $subscriptionstatus Whether the user is currently subscribed to the discussion in some fashion.
 *
 * @global object
 * @global object
 */
function forumx_print_latest_discussions($course, $forum, $maxdiscussions = -1, $displayformat = 'plain', $sort = '',
                                         $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null)
{
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = forumx_get_default_sort_order();
    }

    $olddiscussionlink = false;

    // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page = -1;
    }

    if ($maxdiscussions == 0) {
        // All discussions - backwards compatibility.
        $page = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default.
        }

    } else if ($maxdiscussions > 0) {
        $page = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }

    // Decide if current user is allowed to see ALL the current discussions or not.

    $groupslist = array();
    // A user cannot post to all groups in a 'eachuser' type forum.
    $can_post_to_groups = $forum->type !== 'eachuser' && forumx_can_post_to_groups($forum, $cm, $course, $context, $groupslist);
    // First check the group stuff.
    if ($currentgroup == -1 || $groupmode == -1) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array();

    // If the user can post discussions, then this is a good place to put the
    // button for it. We do not show the button if we are showing site news
    // and the current user is a guest.

    $canstart = $can_post_to_groups || forumx_user_can_post_discussion($forum, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart && $forum->type !== 'news') {
        if (isguestuser() || !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) && !is_viewing($context)) {
            // Allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here.
            $canstart = enrol_selfenrol_available($course->id);
        }
    }
    $canreply = ($canstart || $forum->type === 'qanda' || $forum->type === 'eachuser') && $forum->type !== 'blog' &&
        !forumx_is_forum_locked($forum) && has_capability('mod/forumx:replypost', $context);

    $button = forumx_print_new_message_button($canstart, $forum, $currentgroup, $groupmode, $context);

    echo '<div class="clearfix forummode_container">' . $button;
    $mode = get_user_preferences('forumx_displaymode', $CFG->forumx_displaymode);
    echo '<div class="selector_wrapper_container float_end">';
    forumx_print_mode_form($forum->id, $mode, true);
    echo '</div>';
    echo '</div>';

    // Get all the recent discussions we're allowed to see.
    $getuserlastmodified = ($displayformat == 'header');

    $emptymessage = '';
    if ($forum->type == 'news') {
        $emptymessage = get_string('nonews', 'forumx');
    } else if ($forum->type == 'qanda') {
        $emptymessage = get_string('noquestions', 'forumx');
    } else {
        $emptymessage = get_string('nodiscussions', 'forumx');
    }

    if (!$discussions = forumx_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage)) {
        echo '<div class="forumnodiscuss">' . $emptymessage . "</div>\n";
        if ($canstart) {
            echo forumx_print_new_message_dialog($forum, $groupmode, $currentgroup, $can_post_to_groups) . '<ul class="discussionslist"></ul>';
        }
        if ($canreply) {
            echo forumx_print_quick_reply_dialog();
        }
        return;
    } else {
        echo '<div class="forumnodiscuss hidden_element">' . $emptymessage . "</div>\n";
    }

    // If we want paging.
    if ($page != -1) {
        // Get the number of discussions found.
        $numdiscussions = forumx_get_discussions_count($cm);

        // Show the paging bar.
        echo \mod_forumx\paging_bar::print_paging_bar($numdiscussions, $page, $perpage, 'view.php?f=' . $forum->id);
        if ($numdiscussions > 1000) {
            // Saves some memory on sites with very large forums.
            $replies = forumx_count_discussion_replies($forum->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = forumx_count_discussion_replies($forum->id);
        }

    } else {
        $replies = forumx_count_discussion_replies($forum->id);

        if ($maxdiscussions > 0 && $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants', $context);
    $canviewhiddentimedposts = has_capability('mod/forumx:viewhiddentimedposts', $context);
    $canupdateflag = has_capability('mod/forumx:updateflag', $context);
    $strdatestring = get_string('strftimerecentfull');


    // Check if the forum is tracked.
    if ($cantrack = forumx_tp_can_track_forums($forum)) {
        $forumtracked = forumx_tp_is_tracked($forum);
    } else {
        $forumtracked = false;
    }

    if ($forumtracked) {
        $unreads = forumx_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    $flagged = \mod_forumx\post_actions::get_discussions_flagged($forum->id);
    $recommended = \mod_forumx\post_actions::get_discussions_recommended($forum->id);
    if ($canstart) {
        echo forumx_print_new_message_dialog($forum, $groupmode, $currentgroup, $can_post_to_groups);
    }
    if ($canreply) {
        echo forumx_print_quick_reply_dialog();
    }
    echo forumx_print_quick_forward_dialog();
    echo '<ul class="discussionslist">';
    foreach ($discussions as $discussion) {
        if ($forum->type == 'qanda' && !has_capability('mod/forumx:viewqandawithoutposting', $context) &&
            !forumx_user_has_posted($forum->id, $discussion->discussion, $USER->id)) {
            $canviewparticipants = false;
        }

        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$forumtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        $discussion->flagged = !empty($flagged[$discussion->discussion]);
        $discussion->recommended = !empty($recommended[$discussion->discussion]);

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost = false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;

        $modcontext = context_module::instance($cm->id);
        $canrate = has_capability('mod/forumx:rate', $modcontext);

        $link = forumx_user_can_see_discussion($forum, $discussion, $modcontext, $USER);
        $discussion->forumx = $forum->id;
        $post = new \stdClass();
        $post->userid = $discussion->userid;
        $post->id = $discussion->id;
        $discussion->id = $discussion->discussion;
        forumx_print_discussion($course, $cm, $forum, $discussion, $post, $mode, $canreply, $canrate);
    }
    echo '</ul>';

    if ($olddiscussionlink) {
        if ($forum->type == 'news') {
            $strolder = get_string('oldertopics', 'forumx');
        } else {
            $strolder = get_string('olderdiscussions', 'forumx');
        }
        echo '<div class="forumxolddiscuss">';
        echo '<a href="' . $CFG->wwwroot . '/mod/forumx/view.php?f=' . $forum->id . '&amp;showall=1">';
        echo $strolder . '</a> ...</div>';
    }

    if ($page != -1) { ///Show the paging bar.
        echo \mod_forumx\paging_bar::print_paging_bar($numdiscussions, $page, $perpage, 'view.php?f=' . $forum->id);
    }
}

/**
 * Check if the user has the ability to post a discussion to all groups in the forum.
 * @param unknown $forum
 * @param unknown $cm
 * @param unknown $course
 * @param unknown $context
 */
function forumx_can_post_to_groups($forum, $cm, $course, $context, &$groups)
{

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return false; // The forum has no groups.
    }
    if (!has_capability('mod/forumx:canposttomygroups', $context)) {
        return false;
    }
    $groupscount = 0;
    $groupdata = groups_get_activity_allowed_groups($cm);
    foreach ($groupdata as $groupid => $groupobj) {
        if (forumx_user_can_post_discussion($forum, $groupid, null, $cm, $context)) {
            $groupscount++;
            $groups[] = $groupid;
        }
    }
    return $groupscount > 1;

}

/**
 * Prints a forum discussion
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $forum
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canrate
 * @param int $displaytype Specific display type at the post level
 * @param int $is_discussion Is called from discussion pave of forum page
 * @param int $active_post The active post to be displayed opened
 * @uses CONTEXT_MODULE
 * @uses forumx_MODE_FLATNEWEST
 * @uses forumx_MODE_FLATOLDEST
 * @uses forumx_MODE_THREADED
 * @uses forumx_MODE_NESTED
 */
function forumx_print_discussion($course, $cm, $forum, $discussion, $post, $mode, $canreply = null, $canrate = false, $displaytype = 0, $is_discussion = false, $active_post = 0, $replys_only = false)
{
    global $USER, $CFG;

    require_once($CFG->dirroot . '/rating/lib.php');

    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === null) {
        $reply = forumx_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for forum functions.
    $cm->cache = new stdClass;
    $cm->cache->groups = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    if ($mode == forumx_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }

    $forumtracked = forumx_tp_is_tracked($forum);

    if ($forumtracked && ($mode == forumx_MODE_ALL || $mode == forumx_MODE_FLATNEWEST || $mode == forumx_MODE_FLATOLDEST)) {
        forumx_tp_mark_discussion_read($USER, $discussion->discussion);
        $forumtracked = false;
        $discussion->unread = 0;
    }

    $limit = null;
    // Limit is active only on view pages.
    if (!$is_discussion && $CFG->forumx_replieslimit > 1) {
        $total_posts = forumx_count_discussion_posts($discussion->id);
        // Limit is reached.
        if ($total_posts > $CFG->forumx_replieslimit + 1 && !$replys_only) {
            $limit = 1;
        }
    }
    $has_active_post = false;
    $posts = forumx_get_all_discussion_posts($discussion->id, $sort, $forumtracked, null, $limit);
    if (!empty($active_post) && isset($posts[$active_post])) {
        $posts[$active_post]->active_post = true;
        $has_active_post = true;
    }
    // Start marking the hirarchy levels.
    $posts[$post->id]->postlevel = 0;
    $post = $posts[$post->id];

    foreach ($posts as $pid => $p) {
        $posters[$p->userid] = $p->userid;
    }

    $amount = count($posts);
    $limit_message = '';
    // If reached a limit, display a message.
    if ($limit && $amount == 1) {
        if ($total_posts > $amount) {
            $amount = $total_posts; // Set this for accurate replies count.
            $limit_message = '<div class="posts_limit forumx_message"><a href="' . $CFG->wwwroot . '/mod/forumx/discuss.php?d=' . $discussion->id . '">- ' .
                get_string('reachedreplieslimit', 'forumx') . ' -</a></div>';
        }
    }
    // Preload all groups of ppl that posted in this discussion.
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach ($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    // Load ratings.
    if ($forum->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $modcontext;
        $ratingoptions->component = 'mod_forumx';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $posts;
        $ratingoptions->aggregate = $forum->assessed;// The aggregation method.
        $ratingoptions->scaleid = $forum->scale;
        $ratingoptions->userid = $USER->id;
        if ($forum->type == 'single' || !$discussion->id) {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/forumx/view.php?id=$cm->id";
        } else {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/forumx/discuss.php?d=$discussion->id";
        }
        $ratingoptions->assesstimestart = $forum->assesstimestart;
        $ratingoptions->assesstimefinish = $forum->assesstimefinish;

        $rm = new rating_manager();
        $posts = $rm->get_ratings($ratingoptions);
    }

    $post->forum = $forum->id;   // Add the forum id to the post object, later used by forumx_print_post.
    $post->forumtype = $forum->type;
    $pinned = $discussion->on_top ? ' pinned' : '';
    if (!$replys_only) {
        echo '<li class="discussionslist' . $pinned . '">';
    }
    $post->subject = format_string($post->subject);

    if (isset($posts[$post->id]->children) || $limit == 1) {
        $post->replies = $amount - 1; // We want to know the number of total replies in the discussion.
    }

    $postread = !empty($post->postread);

    $post_display = $mode == forumx_MODE_THREADED ? forumx_DISPLAY_OPEN : null;
    if (!$replys_only) {
        if ($has_active_post && $mode == forumx_MODE_ONLY_DISCUSSION) {
            $post->active_post = true; // This is to make sure the active post will be visible.
        }
        echo '<div id="discussion' . $discussion->id . '" class="forumx_discussion" data-discussionid="' . $discussion->id . '">';
        forumx_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, false,
            $limit_message, '', $postread, true, $forumtracked, false, $displaytype, $is_discussion);
    }
    $output = '';
    // Forum of type blog will display replies only in discussion page.
    if ($is_discussion || (!$is_discussion && $forum->type !== 'blog')) {
        switch ($mode) {
            /*
			case forumx_MODE_FLATOLDEST :
			case forumx_MODE_FLATNEWEST :
				echo '<div class="discussionslist">';
				forumx_print_posts_flat($course, $cm, $forum, $discussion, $post, $mode, $reply, $forumtracked, $posts, $is_discussion);
				echo '</div>';
				break;
*/
            /*
			// Unused for the moment.
			case forumx_MODE_THREADED :
				forumx_print_posts_threaded($course, $cm, $forum, $discussion, $post, 0, $reply, $forumtracked, $posts, $is_discussion);
				break;
*/
            case forumx_MODE_ONLY_DISCUSSION :
                echo '<div class="discussionslist collapsiblediscussion">';
                forumx_print_posts_nested($course, $cm, $forum, $discussion, $post, $reply, $forumtracked, $posts, $is_discussion);
                echo '</div>';
                break;

            case forumx_MODE_NESTED :
            default:
                echo '<div class="discussionslist">';
                forumx_print_posts_nested($course, $cm, $forum, $discussion, $post, $reply, $forumtracked, $posts, $is_discussion);
                echo '</div>';
                break;
        }
        if (!$replys_only) {
            echo '</div>';
        }
    }
    if (!$replys_only) {
        echo '</li>';
    }
}

/**
 * Unused display option.
 * @param object $course
 * @param object $cm
 * @param object $forum
 * @param object $discussion
 * @param object $post
 * @param object $mode
 * @param bool $reply
 * @param bool $forumtracked
 * @param array $posts
 * @param int $is_discussion Is called from discussion pave of forum page
 * @return void
 * @global object
 * @global object
 * @uses forumx_MODE_FLATNEWEST
 */
function forumx_print_posts_flat($course, &$cm, $forum, $discussion, $post, $mode, $reply, $forumtracked, $posts, $is_discussion = false)
{
    global $USER, $CFG;

    $link = false;

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->postlevel = 1;
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        echo '<div class="discussionslist">';
        forumx_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
            '', '', $postread, true, $forumtracked, null, null, $is_discussion);
        echo '</div>';
    }
}

/**
 * Unused display option.
 * @return void
 * @global object
 * @uses CONTEXT_MODULE
 * @global object
 */
function forumx_print_posts_threaded($course, &$cm, $forum, $discussion, $parent, $depth, $reply, $forumtracked, $posts, $is_discussion = false)
{
    global $USER, $CFG;

    $link = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            $post->postlevel = $posts[$post->parent]->postlevel + 1;
            $indent_level = $post->postlevel === 1 ? ' indentlevel1' : '';
            echo '<div class="indent' . $indent_level . '">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                forumx_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
                    '', '', $postread, true, $forumtracked, null, null, $is_discussion);
            } else {
                if (!forumx_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by->name = fullname($post, $canviewfullnames);
                $by->date = userdate($post->modified);

                if ($forumtracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="forumxthread read">';
                    } else {
                        $style = '<span class="forumxthread unread">';
                    }
                } else {
                    $style = '<span class="forumxthread">';
                }
                echo $style . '<a name="' . $post->id . '"></a>' .
                    '<a href="discuss.php?d=' . $post->discussion . '&amp;parent=' . $post->id . '">' . format_string($post->subject, true) . '</a> ';
                print_string("bynameondate", "forumx", $by);
                echo "</span>";
            }

            forumx_print_posts_threaded($course, $cm, $forum, $discussion, $post, $depth - 1, $reply, $forumtracked, $posts, $is_discussion);
            echo "</div>\n";
        }
    }
}

/**
 *
 * @return void
 * @global object
 * @global object
 */
function forumx_print_posts_nested($course, &$cm, $forum, $discussion, $parent, $reply, $forumtracked, $posts, $is_discussion = false)
{
    global $USER, $CFG;

    $link = false;

    $postlevel = 0;
    if (!empty($posts[$parent->id]->children)) {
        $postlevel = $posts[$parent->id]->postlevel;
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {
            if ($post->parent > 0) {
                $post->postlevel = $postlevel + 1;
            }
            $indent_level = $post->postlevel === 1 ? ' indentlevel1' : '';
            echo '<div class="indent flatindent' . $indent_level . '">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);
            forumx_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
                '', '', $postread, true, $forumtracked, null, null, $is_discussion);
            if ($post->postlevel > 1) {
                echo "</div>\n";
            }
            forumx_print_posts_nested($course, $cm, $forum, $discussion, $post, $reply, $forumtracked, $posts, $is_discussion);
            if ($post->postlevel == 1) {
                echo "</div>\n";
            }
        }
    }
}

/**
 *
 * @return void
 * @global object
 * @global object
 */
function forumx_print_posts_in_discussions($course, &$cm, $forum, $discussion, $parent, $reply, $forumtracked, $posts, $is_discussion = false)
{
    global $USER, $CFG;

    $link = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            echo '<li class="discussionslist">';
            forumx_print_post($post, $discussion, $forum, $cm, $course, $ownpost, $reply, $link,
                '', '', $postread, true, $forumtracked, null, null, $is_discussion);
            echo '</li>';
            forumx_print_posts_nested($course, $cm, $forum, $discussion, $post, $reply, $forumtracked, $posts, $is_discussion);
            echo "</div>\n";
        }
    }
}

/**
 * Returns all forum posts since a given time in specified forum.
 *
 * @global object
 * @global object
 * @global object
 * @global object
 */
function forumx_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0)
{
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = 'AND u.id = ?';
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = 'AND d.groupid = ?';
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS forumtype, d.forumx, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {forumx_posts} p
                                              JOIN {forumx_discussions} d ON d.id = p.discussion
                                              JOIN {forumx} f         ON f.id = d.forumx
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // Order by initial posting date.
        return;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);
    $cm_context = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/forumx:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->forumx_enabletimedposts) && $USER->id != $post->duserid
            && (($post->timestart > 0 && $post->timestart > time()) || ($post->timeend > 0 && $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 || $groupmode == VISIBLEGROUPS || $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name, true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type = 'forumx';
        $tmpactivity->cmid = $cm->id;
        $tmpactivity->name = $aname;
        $tmpactivity->sectionnum = $cm->sectionnum;
        $tmpactivity->timestamp = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject = format_string($post->subject);
        $tmpactivity->content->parent = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 *
 * @global object
 */
function forumx_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames)
{
    global $CFG, $OUTPUT;

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="forumx-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user, array('courseid' => $courseid));
    echo '</td><td class="' . $class . '">';

    if ($activity->content->parent) {
        $class = 'title';
    } else {
        // Bold the title of new discussions so they stand out.
        $class = 'title bold';
    }
    echo '<div class="' . $class . '">';
    if ($detail) {
        $aname = s($activity->name);
        echo '<img src="' . $OUTPUT->image_url('icon', $activity->type) . '" ' .
            'class="icon" alt="' . $aname . '">';
    }
    echo "<a href=\"$CFG->wwwroot/mod/forumx/discuss.php?d={$activity->content->discussion}"
        . "#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
        . "{$fullname}</a> - " . userdate($activity->timestamp);
    echo '</div>';
    echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children.
 * Used when pruning a post.
 *
 * @param int $postid
 * @param int $discussionid
 * @return bool
 * @global object
 */
function forumx_change_discussionid($postid, $discussionid)
{
    global $DB;
    $DB->set_field('forumx_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('forumx_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            forumx_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page.
 *
 * @param int $courseid
 * @param int $forumid
 * @return string
 * @global object
 * @global object
 */
function forumx_update_subscriptions_button($courseid, $forumid)
{
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return '<form method="get" action="' . $CFG->wwwroot . '/mod/forumx/subscribers.php">' .
        '<input type="hidden" name="id" value="' . $forumid . '" />' .
        '<input type="hidden" name="edit" value="' . $edit . '" />' .
        '<input type="submit" value="' . $string . '" /></form>';
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 * @global object
 * @global object
 */
function forumx_tp_mark_posts_read($user, $postids)
{
    global $CFG, $DB;

    if (!forumx_tp_can_track_forums(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->forumx_oldpostdays * DAYSECS);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = $status && forumx_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $postidparams) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'postid');

    $insertparams = array(
        'userid1' => $user->id,
        'userid2' => $user->id,
        'userid3' => $user->id,
        'firstread' => $now,
        'lastread' => $now,
        'cutoffdate' => $cutoffdate,
    );
    $params = array_merge($postidparams, $insertparams);

    if ($CFG->forumx_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = " . forumx_TRACKING_FORCED . "
                        OR (f.trackingtype = " . forumx_TRACKING_OPTIONAL . " AND tf.id IS NULL))";
    } else {
        $trackingsql = "AND ((f.trackingtype = " . forumx_TRACKING_OPTIONAL . "  OR f.trackingtype = " . forumx_TRACKING_FORCED . ")
                            AND tf.id IS NULL)";
    }

    // First insert any new entries.
    $sql = "INSERT INTO {forumx_read} (userid, postid, discussionid, forumxid, firstread, lastread)

            SELECT :userid1, p.id, p.discussion, d.forumx, :firstread, :lastread
                FROM {forumx_posts} p
                    JOIN {forumx_discussions} d       ON d.id = p.discussion
                    JOIN {forumx} f                   ON f.id = d.forumx
                    LEFT JOIN {forumx_track_prefs} tf ON (tf.userid = :userid2 AND tf.forumxid = f.id)
                    LEFT JOIN {forumx_read} fr        ON (
                            fr.userid = :userid3
                        AND fr.postid = p.id
                        AND fr.discussionid = d.id
                        AND fr.forumxid = f.id
                    )
                WHERE p.id $usql
                    AND p.modified >= :cutoffdate
                    $trackingsql
                    AND fr.id IS NULL";

    $status = $DB->execute($sql, $params) && $status;

    // Then update all records.
    $updateparams = array(
        'userid' => $user->id,
        'lastread' => $now,
    );
    $params = array_merge($postidparams, $updateparams);
    $status = $status && $DB->set_field_select('forumx_read', 'lastread', $now, '
            userid = :userid
            AND lastread <> :lastread
            AND postid ' . $usql, $params);

    return $status;
}

/**
 * Mark post as read.
 * @param int $userid
 * @param int $postid
 * @global object
 * @global object
 */
function forumx_tp_add_read_record($userid, $postid)
{
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG->forumx_oldpostdays * DAYSECS);

    if (!$DB->record_exists('forumx_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = 'INSERT INTO {forumx_read} (userid, postid, discussionid, forumxid, firstread, lastread)
    SELECT ?, p.id, p.discussion, d.forumx, ?, ?
    FROM {forumx_posts} p
    JOIN {forumx_discussions} d ON d.id = p.discussion
    WHERE p.id = ? AND p.modified >= ?';
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = 'UPDATE {forumx_read}
    SET lastread = ?
    WHERE userid = ? AND postid = ?';
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function forumx_tp_mark_post_read($userid, $post, $forumid)
{
    if (!forumx_tp_is_post_old($post)) {
        return forumx_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole forum as read, for a given user
 *
 * @param object $user
 * @param int $forumid
 * @param int|bool $groupid
 * @return bool
 * @global object
 * @global object
 */
function forumx_tp_mark_forum_read($user, $forumid, $groupid = false)
{
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->forumx_oldpostdays * DAYSECS);

    $groupsel = "";
    $params = array($user->id, $forumid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {forumx_posts} p
                   LEFT JOIN {forumx_discussions} d ON d.id = p.discussion
                   LEFT JOIN {forumx_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forumx = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return forumx_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @param object $user
 * @param int $discussionid
 * @return bool
 * @global object
 * @global object
 */
function forumx_tp_mark_discussion_read($user, $discussionid)
{
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->forumx_oldpostdays * DAYSECS);

    $sql = "SELECT p.id
              FROM {forumx_posts} p
                   LEFT JOIN {forumx_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id IS NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return forumx_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @param int $userid
 * @param object $post
 * @global object
 */
function forumx_tp_is_post_read($userid, $post)
{
    global $DB;
    return (forumx_tp_is_post_old($post) ||
        $DB->record_exists('forumx_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @param object $post
 * @param int $time Defautls to time()
 * @global object
 */
function forumx_tp_is_post_old($post, $time = null)
{
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->forumx_oldpostdays * DAYSECS)));
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @param int $userid
 * @param int $courseid
 * @return array
 * @global object
 * @global object
 */
function forumx_tp_get_course_unread_posts($userid, $courseid)
{
    global $CFG, $DB;

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->forumx_oldpostdays * DAYSECS);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid);

    if (!empty($CFG->forumx_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->forumx_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = " . forumx_TRACKING_FORCED . "
                            OR (f.trackingtype = " . forumx_TRACKING_OPTIONAL . " AND tf.id IS NULL
                                AND (SELECT trackforums FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = " . forumx_TRACKING_OPTIONAL . " OR f.trackingtype = " . forumx_TRACKING_FORCED . ")
                            AND tf.id IS NULL
                            AND (SELECT trackforums FROM {user} WHERE id = ?) = 1)";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {forumx_posts} p
                   JOIN {forumx_discussions} d       ON d.id = p.discussion
                   JOIN {forumx} f                   ON f.id = d.forumx
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {forumx_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {forumx_track_prefs} tf ON (tf.userid = ? AND tf.forumxid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and forum and [optionally] group.
 *
 * @param object $cm
 * @param object $course
 * @return int
 * @global object
 * @global object
 * @global object
 */
function forumx_tp_count_forum_unread_posts($cm, $course)
{
    global $CFG, $USER, $DB;

    static $readcache = array();

    $forumid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = forumx_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$forumid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$forumid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$forumid];
    }

    require_once($CFG->dirroot . '/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // Add all groups posts.
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->forumx_oldpostdays * DAYSECS);
    $params = array($USER->id, $forumid, $cutoffdate);

    if (!empty($CFG->forumx_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {forumx_posts} p
                   JOIN {forumx_discussions} d ON p.discussion = d.id
                   LEFT JOIN {forumx_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.forumx = ?
                   AND p.modified >= ? AND r.id is NULL
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $forumid
 * @return bool
 * @global object
 */
function forumx_tp_delete_read_records($userid = -1, $postid = -1, $discussionid = -1, $forumid = -1)
{
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '') {
            $select .= ' AND ';
        }
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') {
            $select .= ' AND ';
        }
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') {
            $select .= ' AND ';
        }
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($forumid > -1) {
        if ($select != '') {
            $select .= ' AND ';
        }
        $select .= 'forumxid = ?';
        $params[] = $forumid;
    }
    if ($select == '') {
        return false;
    } else {
        return $DB->delete_records_select('forumx_read', $select, $params);
    }
}

/**
 * Get a list of forums not tracked by the user.
 *
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by forum id, or false.
 * @global object
 * @global object
 */
function forumx_tp_get_untracked_forums($userid, $courseid)
{
    global $CFG, $DB;

    if ($CFG->forumx_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = " . forumx_TRACKING_OFF . "
                            OR (f.trackingtype = " . forumx_TRACKING_OPTIONAL . " AND (ft.id IS NOT NULL
                                OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = " . forumx_TRACKING_OFF . "
                            OR ((f.trackingtype = " . forumx_TRACKING_OPTIONAL . " OR f.trackingtype = " . forumx_TRACKING_FORCED . ")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackforums FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {forumx} f
                   LEFT JOIN {forumx_track_prefs} ft ON (ft.forumxid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($forums = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($forums as $forum) {
            $forums[$forum->id] = $forum;
        }
        return $forums;

    } else {
        return array();
    }
}

/**
 * Get all untracked posts in a list of discussions.
 * @param int $userid
 * @param string $discussions A list of discussions ids
 * @param bool $json Return type
 * @return string
 */
function forumx_tp_get_untracked_posts($userid, $discussions, $json = true)
{
    $unread = array();
    if (!empty($discussions)) {
        global $DB;
        $params = array($userid, $userid);
        list($idssql, $idsparams) = $DB->get_in_or_equal(explode(',', $discussions));
        $params = array_merge($params, $idsparams);
        $sql = 'SELECT p.id, p.modified, p.discussion, fr.id AS postread
		FROM {forumx_posts} p
		LEFT JOIN {forumx_read} fr ON (fr.postid = p.id AND fr.userid = ?)
		WHERE p.userid != ? AND
		p.discussion ' . $idssql;
        if ($posts = $DB->get_records_sql($sql, $params)) {
            $time = time();
            foreach ($posts as $post) {
                if (!empty($post->postread)) {
                    continue;
                }
                if (forumx_tp_is_post_old($post, $time)) {
                    continue;
                }
                if (!isset($unread[$post->discussion])) {
                    $unread[$post->discussion] = array();
                }
                $unread[$post->discussion][] = $post->id;
            }
            // Remove discussions with no new posts.
            foreach ($unread as $key => $value) {
                if (empty($unread[$key])) {
                    unset($unread[$key]);
                }
            }
        }
    }
    if ($json) {
        return json_encode($unread);
    }
    return $unread;
}

/**
 * Return an array of new posts icons.
 * @return array
 */
function forumx_new_post_icons()
{
    return array(
        'discussion' => '<div class="of_new_post">' .
            forumx_print_post_icon(null, 'i/sunbig', 'discussion_new', get_string('discussionunread', 'forumx')) . '</div>',
        'firstpost' => '<span id="unread{postid}" class="post_new firstpost_new">' . get_string('unread', 'forumx') . '</span>',
        'post' => '<div class="of_new_post"><span id="unread{postid}" class="post_new" alt="' .
            get_string('postunread', 'forumx') . '" title="' . get_string('postunread', 'forumx') . '"></span></div>'
    );
}

/**
 * Determine if a user can track forums and optionally a particular forum.
 * Checks the site settings, the user settings and the forum settings (if
 * requested).
 *
 * @param mixed $forum The forum object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 * @global object
 * @global object
 * @global object
 */
function forumx_tp_can_track_forums($forum = false, $user = false)
{
    global $USER, $CFG, $DB;

    // If possible, avoid expensive queries.
    if (empty($CFG->forumx_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) || empty($user->id)) {
        return false;
    }

    if ($forum === false) {
        if ($CFG->forumx_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific forum.
            return true;
        } else {
            return (bool)$user->trackforums;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($forum)) {
        debugging('Better use proper forum object.', DEBUG_DEVELOPER);
        $forum = $DB->get_record('forumx', array('id' => $forum), '', 'id,trackingtype');
    }

    $forumallows = ($forum->trackingtype == forumx_TRACKING_OPTIONAL);
    $forumforced = ($forum->trackingtype == forumx_TRACKING_FORCED);

    if ($CFG->forumx_allowforcedreadtracking) {
        // If we allow forcing, then forced forums takes procidence over user setting.
        return ($forumforced || ($forumallows && (!empty($user->trackforums) && (bool)$user->trackforums)));
    } else {
        // If we don't allow forcing, user setting trumps.
        return ($forumforced || $forumallows) && !empty($user->trackforums);
    }
}

/**
 * Tells whether a specific forum is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @param mixed $forum If int, the id of the forum being checked; if object, the forum object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 * @global object
 * @global object
 * @global object
 */
function forumx_tp_is_tracked($forum, $user = false)
{
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($forum)) {
        debugging('Better use proper forum object.', DEBUG_DEVELOPER);
        $forum = $DB->get_record('forumx', array('id' => $forum));
    }

    if (!forumx_tp_can_track_forums($forum, $user)) {
        return false;
    }

    $forumallows = ($forum->trackingtype == forumx_TRACKING_OPTIONAL);
    $forumforced = ($forum->trackingtype == forumx_TRACKING_FORCED);
    $userpref = $DB->get_record('forumx_track_prefs', array('userid' => $user->id, 'forumxid' => $forum->id));

    if ($CFG->forumx_allowforcedreadtracking) {
        return $forumforced || ($forumallows && $userpref === false);
    } else {
        return ($forumallows || $forumforced) && $userpref === false;
    }
}

/**
 * @param int $forumid
 * @param int $userid
 * @global object
 * @global object
 */
function forumx_tp_start_tracking($forumid, $userid = false)
{
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('forumx_track_prefs', array('userid' => $userid, 'forumxid' => $forumid));
}

/**
 * @param int $forumid
 * @param int $userid
 * @param bool $delete_history Keep history of read posts
 * @global object
 * @global object
 */
function forumx_tp_stop_tracking($forumid, $userid = false, $delete_history = false)
{
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('forumx_track_prefs', array('userid' => $userid, 'forumxid' => $forumid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->forumxid = $forumid;
        $DB->insert_record('forumx_track_prefs', $track_prefs);
    }
    if ($delete_history) {
        return forumx_tp_delete_read_records($userid, -1, -1, $forumid);
    } else {
        return true;
    }
}


/**
 * Clean old records from the forumx_read table.
 * @return void
 * @global object
 * @global object
 */
function forumx_tp_clean_read_records()
{
    global $CFG, $DB;

    if (!isset($CFG->forumx_oldpostdays)) {
        return;
    }
    // Look for records older than the cutoffdate that are still in the forumx_read table.
    $cutoffdate = time() - ($CFG->forumx_oldpostdays * DAYSECS);

    // First get the oldest tracking present - we need tis to speedup the next delete query.
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {forumx_posts} fp
                   JOIN {forumx_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // Nothing to delete;
        return;
    }

    // Now delete old tracking info.
    $sql = 'DELETE
              FROM {forumx_read}
             WHERE postid IN (SELECT fp.id
                                FROM {forumx_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)';
    $DB->execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @param int $discussionid
 * @return bool|int
 **@global object
 * @global object
 */
function forumx_discussion_update_last_post($discussionid)
{
    global $CFG, $DB;

    // Find the last post for this discussion.
    $sql = 'SELECT id, userid, modified
    FROM {forumx_posts}
    WHERE discussion=?
    ORDER BY modified DESC LIMIT 0, 1';

    // Lets go find the last post.
    if (($lastpost = $DB->get_record_sql($sql, array($discussionid)))) {
        $discussionobject = new stdClass();
        $discussionobject->id = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('forumx_discussions', $discussionobject);
        return $lastpost->id;
    }

    return false;
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function forumx_get_view_actions()
{
    return array('view discussion', 'search', 'forum', 'forums', 'subscribers', 'view forum');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function forumx_get_post_actions()
{
    return array('add discussion', 'add post', 'delete discussion', 'delete post', 'move discussion', 'prune post', 'update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $forum the forum id or the forum object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function forumx_check_throttling($forum, $cm = null)
{
    global $CFG, $DB, $USER;

    if (is_numeric($forum)) {
        $forum = $DB->get_record('forumx', array('id' => $forum), '*', MUST_EXIST);
    }

    if (!is_object($forum)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course, false, MUST_EXIST);
    }

    if (empty($forum->blockafter)) {
        return false;
    }

    if (empty($forum->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/forumx:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $forum->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {forumx_posts} p
                                        JOIN {forumx_discussions} d
                                        ON p.discussion = d.id WHERE d.forumx = ?
                                        AND p.userid = ? AND p.created > ?', array($forum->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $forum->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime' . $forum->blockperiod);

    if ($forum->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'forumxblockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/forumx/view.php?f=' . $forum->id;

        return $warning;
    }

    if ($forum->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'forumxblockingalmosttoomanyposts';
        $warning->module = 'forumx';
        $warning->additional = $a;
        $warning->link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function forumx_check_throttling.
 * @since Moodle 2.5
 */
function forumx_check_blocking_threshold($thresholdwarning)
{
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode,
            $thresholdwarning->module,
            $thresholdwarning->link,
            $thresholdwarning->additional);
    }
}


/**
 * Removes all grades from gradebook
 *
 * @param int $courseid
 * @param string $type optional
 * @global object
 * @global object
 */
function forumx_reset_gradebook($courseid, $type = '')
{
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = 'SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {forumx} f, {course_modules} cm, {modules} m
             WHERE m.name="forumx" AND m.id=cm.module AND cm.instance=f.id AND f.course=? ' . $wheresql;

    if ($forums = $DB->get_records_sql($sql, $params)) {
        foreach ($forums as $forum) {
            forumx_grade_item_update($forum, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified forum
 * and clean up any related data.
 *
 * @param $data the data submitted from the reset course.
 * @return array status array
 * @global object
 * @global object
 */
function forumx_reset_userdata($data)
{
    global $CFG, $DB;
    require_once($CFG->dirroot . '/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'forumx');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql = "";
    if (!empty($data->reset_forum_all)) {
        $removeposts = true;
        $typesstr = get_string('resetforumsall', 'forumx');
        $types = array();
    } else if (!empty($data->reset_forum_types)) {
        $removeposts = true;
        $types = array();
        $sqltypes = array();
        $forum_types_all = forumx_get_forum_types_all();
        foreach ($data->reset_forum_types as $type) {
            if (!array_key_exists($type, $forum_types_all)) {
                continue;
            }
            $types[] = $forum_types_all[$type];
            $sqltypes[] = $type;
        }
        if (!empty($sqltypes)) {
            list($typesql, $typeparams) = $DB->get_in_or_equal($sqltypes);
            $typesql = " AND f.type " . $typesql;
            $params = array_merge($params, $typeparams);
        }
        $typesstr = get_string('resetforums', 'forumx') . ': ' . implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {forumx_discussions} fd, {forumx} f
                           WHERE f.course=? AND f.id=fd.forumx";

    $allforumssql = "SELECT f.id
                            FROM {forumx} f
                           WHERE f.course=?";

    $allpostssql = "SELECT fp.id
                            FROM {forumx_posts} fp, {forumx_discussions} fd, {forumx} f
                           WHERE f.course=? AND f.id=fd.forum AND fd.id=fp.ouildiscussion";

    $forumssql = $forums = $rm = null;

    if ($removeposts || !empty($data->reset_forum_ratings)) {
        $forumssql = "$allforumssql $typesql";
        $forums = $forums = $DB->get_records_sql($forumssql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_forumx';
        $ratingdeloptions->ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($forums) {
            foreach ($forums as $forumid => $unused) {
                if (!$cm = get_coursemodule_from_instance('forumx', $forumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_forumx', 'attachment');
                $fs->delete_area_files($context->id, 'mod_forumx', 'post');

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('forumx_read', "forumid IN ($forumssql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('forumx_track_prefs', "forumid IN ($forumssql)", $params);

        // remove posts from queue
        $DB->delete_records_select('forumx_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion forums
        $DB->delete_records_select('forumx_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('forumx_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple

        // finally all discussions except single simple forums
        $DB->delete_records_select('forumx_discussions', "forumx IN ($forumssql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                forumx_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    forumx_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component' => $componentstr, 'item' => $typesstr, 'error' => false);
    }

    // remove all ratings in this course's forums
    if (!empty($data->reset_forum_ratings)) {
        if ($forums) {
            foreach ($forums as $forumid => $unused) {
                if (!$cm = get_coursemodule_from_instance('forumx', $forumid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            forumx_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_forum_digests)) {
        $DB->delete_records_select('forumx_digests', "forumx IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'forumx'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_forum_subscriptions)) {
        $DB->delete_records_select('forumx_subscriptions', "forumx IN ($allforumssql)", $params);
        $DB->delete_records_select('forumx_discussion_subs', "forum IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetsubscriptions', 'forumx'), 'error' => false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_forum_track_prefs)) {
        $DB->delete_records_select('forumx_track_prefs', "forumxid IN ($allforumssql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resettrackprefs', 'forumx'), 'error' => false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('forumx', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'), 'error' => false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function forumx_reset_course_form_definition(&$mform)
{
    $mform->addElement('header', 'forumheader', get_string('modulenameplural', 'forumx'));

    $mform->addElement('checkbox', 'reset_forum_all', get_string('resetforumsall', 'forumx'));

    $mform->addElement('select', 'reset_forum_types', get_string('resetforums', 'forumx'), forumx_get_forum_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_forum_types');
    $mform->disabledIf('reset_forum_types', 'reset_forum_all', 'checked');

    $mform->addElement('checkbox', 'reset_forum_digests', get_string('resetdigests', 'forumx'));
    $mform->setAdvanced('reset_forum_digests');

    $mform->addElement('checkbox', 'reset_forum_subscriptions', get_string('resetsubscriptions', 'forumx'));
    $mform->setAdvanced('reset_forum_subscriptions');

    $mform->addElement('checkbox', 'reset_forum_track_prefs', get_string('resettrackprefs', 'forumx'));
    $mform->setAdvanced('reset_forum_track_prefs');
    $mform->disabledIf('reset_forum_track_prefs', 'reset_forum_all', 'checked');

    $mform->addElement('checkbox', 'reset_forum_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_forum_ratings', 'reset_forum_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function forumx_reset_course_form_defaults($course)
{
    return array('reset_forum_all' => 1, 'reset_forum_digests' => 0, 'reset_forum_subscriptions' => 0, 'reset_forum_track_prefs' => 0, 'reset_forum_ratings' => 1);
}

/**
 * Returns array of forum layout modes
 *
 * @param bool $is_forum Is in forum page or discussion page.
 * @return array
 */
function forumx_get_layout_modes($is_forum = true)
{

    if ($is_forum) {
        return array(
            forumx_MODE_NESTED => get_string('modenested', 'forumx'),
            forumx_MODE_ONLY_DISCUSSION => get_string('modeonlydiscussion', 'forumx'),
            forumx_MODE_ALL => get_string('modeall', 'forumx')
        );
    }
    return array(
        forumx_MODE_NESTED => get_string('modenested', 'forumx'),
        forumx_MODE_ALL => get_string('modeall', 'forumx')
    );
}

/**
 * Make sure the forum layout is valid. Set to default if not.
 * @param int $mode Layout mode
 * @param bool $is_forum Is a forum pade or a discussion page.
 */
function forumx_normalize_layout_mode($mode = 0, $is_forum = true)
{
    if ($mode != forumx_MODE_NESTED) {
        if ($is_forum) {
            if ($mode != forumx_MODE_ONLY_DISCUSSION && $mode != forumx_MODE_ALL) {
                $mode = forumx_MODE_NESTED;
            }
        } else {
            if ($mode != forumx_MODE_ALL) {
                $mode = forumx_MODE_NESTED;
            }
        }
    }
    return $mode;
}

/**
 * Returns array of forum types choosable on the forum editing form.
 *
 * @return array
 */
function forumx_get_forum_types()
{
    return array('general' => get_string('generalforum', 'forumx'),
        'eachuser' => get_string('eachuserforum', 'forumx'),
        'single' => get_string('singleforum', 'forumx'),
        'qanda' => get_string('qandaforum', 'forumx'),
        'blog' => get_string('blogforum', 'forumx'));
}

/**
 * Returns array of all forum layout modes
 *
 * @return array
 */
function forumx_get_forum_types_all()
{
    return array('news' => get_string('namenews', 'forumx'),
        'social' => get_string('namesocial', 'forumx'),
        'general' => get_string('generalforum', 'forumx'),
        'eachuser' => get_string('eachuserforum', 'forumx'),
        'single' => get_string('singleforum', 'forumx'),
        'qanda' => get_string('qandaforum', 'forumx'),
        'blog' => get_string('blogforum', 'forumx'));
}

/**
 * Returns all other caps used in module.
 *
 * @return array
 */
function forumx_get_extra_capabilities()
{
    return array('moodle/site:accessallgroups',
        'moodle/site:viewfullnames',
        'moodle/site:trustcontent',
        'moodle/rating:view',
        'moodle/rating:viewany',
        'moodle/rating:viewall',
        'moodle/rating:rate');
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $forumnode The node to add module settings to
 */
function forumx_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $forumnode)
{
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $forumobject = $DB->get_record('forumx', array('id' => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $params = $PAGE->url->params();
    if (!empty($params['d'])) {
        $discussionid = $params['d'];
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage = has_capability('mod/forumx:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = \mod_forumx\subscriptions::get_subscription_mode($forumobject);
    $cansubscribedigest = !\mod_forumx\subscriptions::is_forcesubscribed($forumobject) &&
        (!\mod_forumx\subscriptions::subscription_disabled($forumobject) || $canmanage);
    $cansubscribe = $activeenrolled && $cansubscribedigest;

    if ($canmanage) {
        $mode = $forumnode->add(get_string('subscriptionmode', 'forumx'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'forumx'), new moodle_url('/mod/forumx/subscribe.php', array('id' => $forumobject->id, 'mode' => forumx_CHOOSESUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        //$forceforever = $mode->add(get_string('subscriptionforced', 'forumx'), new moodle_url('/mod/forumx/subscribe.php', array('id'=>$forumobject->id, 'mode'=>forumx_FORCESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string('subscriptionauto', 'forumx'), new moodle_url('/mod/forumx/subscribe.php', array('id' => $forumobject->id, 'mode' => forumx_INITIALSUBSCRIBE, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        //$disallowchoice = $mode->add(get_string('subscriptiondisabled', 'forumx'), new moodle_url('/mod/forumx/subscribe.php', array('id'=>$forumobject->id, 'mode'=>forumx_DISALLOWSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case forumx_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                $allowchoice->icon = new pix_icon('t/selected', '', 'mod_forumx');
                break;
            case forumx_FORCESUBSCRIBE : // 1
                //yifatsh remove 5764
                //     $forceforever->action = null;
                //    $forceforever->add_class('activesetting');
                break;
            case forumx_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                $forceinitially->icon = new pix_icon('t/selected', '', 'mod_forumx');
                break;
            case forumx_DISALLOWSUBSCRIBE : // 3
                //yifatsh remove 5764
                //     $disallowchoice->action = null;
                //     $disallowchoice->add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case forumx_CHOOSESUBSCRIBE : // 0
                $notenode = $forumnode->add(get_string('subscriptionoptional', 'forumx'));
                break;
            case forumx_FORCESUBSCRIBE : // 1
                $notenode = $forumnode->add(get_string('subscriptionforced', 'forumx'));
                break;
            case forumx_INITIALSUBSCRIBE : // 2
                $notenode = $forumnode->add(get_string('subscriptionauto', 'forumx'));
                break;
            case forumx_DISALLOWSUBSCRIBE : // 3
                $notenode = $forumnode->add(get_string('subscriptiondisabled', 'forumx'));
                break;
        }
    }

    if ($cansubscribedigest) {// && \mod_forumx\subscriptions::is_subscribed($USER->id, $forumobject, null, $PAGE->cm)) {
        $digestmode = \mod_forumx\subscriptions::get_digest_mode($USER->id, $forumobject->id);
        $node = $forumnode->add(get_string('emaildigesttype', 'forumx'), null, navigation_node::TYPE_CONTAINER);
        $digestoff = $node->add(get_string('emaildigestoffshort', 'forumx'), new moodle_url('/mod/forumx/maildigest.php', array('id' => $forumobject->id, 'maildigest' => 0, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $digestcomplete = $node->add(get_string('emaildigestcompleteshort', 'forumx'), new moodle_url('/mod/forumx/maildigest.php', array('id' => $forumobject->id, 'maildigest' => 1, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $digestshort = $node->add(get_string('emaildigestsubjectsshort', 'forumx'), new moodle_url('/mod/forumx/maildigest.php', array('id' => $forumobject->id, 'maildigest' => 2, 'sesskey' => sesskey())), navigation_node::TYPE_SETTING);
        $digestoff->add_class('subscribe_digestmode digestmode_0');
        $digestcomplete->add_class('subscribe_digestmode digestmode_1');
        $digestshort->add_class('subscribe_digestmode digestmode_2');
        if ($digestmode == 2) {
            $digestshort->action = null;
            $digestshort->add_class('activesetting');
            $digestshort->icon = new pix_icon('t/selected', '', 'mod_forumx');
        } else if ($digestmode == 1) {
            $digestcomplete->action = null;
            $digestcomplete->add_class('activesetting');
            $digestcomplete->icon = new pix_icon('t/selected', '', 'mod_forumx');
        } else {
            $digestoff->action = null;
            $digestoff->add_class('activesetting');
            $digestoff->icon = new pix_icon('t/selected', '', 'mod_forumx');
        }
        $node_class = 'block_digest';
        if (!\mod_forumx\subscriptions::is_subscribed($USER->id, $forumobject, null, $PAGE->cm)) {
            $node_class .= ' hidden_element';
        }
        $node->add_class($node_class);
    }

    if ($cansubscribe) {
        if (\mod_forumx\subscriptions::is_subscribed($USER->id, $forumobject, null, $PAGE->cm)) {
            $linktext = get_string('unsubscribe', 'forumx');
        } else {
            $linktext = get_string('subscribe', 'forumx');
        }
        $url = new moodle_url('/mod/forumx/subscribe.php', array('id' => $forumobject->id, 'sesskey' => sesskey()));
        $node = $forumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        $node->add_class('block_subscribe');

        if (isset($discussionid)) {
            if (\mod_forumx\subscriptions::is_subscribed($USER->id, $forumobject, $discussionid, $PAGE->cm)) {
                $linktext = get_string('unsubscribediscussion', 'forumx');
            } else {
                $linktext = get_string('subscribediscussion', 'forumx');
            }
            $url = new moodle_url('/mod/forumx/subscribe.php', array(
                'id' => $forumobject->id,
                'sesskey' => sesskey(),
                'd' => $discussionid,
                'returnurl' => $PAGE->url->out(),
            ));
            $forumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (has_capability('mod/forumx:viewsubscribers', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/forumx/subscribers.php', array('id' => $forumobject->id));
        $forumnode->add(get_string('showsubscribers', 'forumx'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && forumx_tp_can_track_forums($forumobject)) { // keep tracking info for users with suspended enrolments
        if ($forumobject->trackingtype == forumx_TRACKING_OPTIONAL
            || ((!$CFG->forumx_allowforcedreadtracking) && $forumobject->trackingtype == forumx_TRACKING_FORCED)) {
            if (forumx_tp_is_tracked($forumobject)) {
                $linktext = get_string('notrackforum', 'forumx');
            } else {
                $linktext = get_string('trackforum', 'forumx');
            }
            $url = new moodle_url('/mod/forumx/settracking.php', array(
                'id' => $forumobject->id,
                'sesskey' => sesskey(),
            ));
            $forumnode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->forumx_enablerssfeeds);

    if ($enablerssfeeds && $forumobject->rsstype && $forumobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($forumobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions', 'forumx');
        } else {
            $string = get_string('rsssubscriberssposts', 'forumx');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, 'mod_forumx', $forumobject->id));
        $forumnode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function forumx_cm_info_view(cm_info $cm)
{
    global $CFG;

    // Get tracking status, once per request in order to avoid multiple queries.
    static $usetracking;
    if (!isset($usetracking)) {
        $usetracking = forumx_tp_can_track_forums();
    }

    if ($usetracking) {
        if ($unread = forumx_tp_count_forum_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'forumx');
            } else {
                $out .= get_string('unreadpostsnumber', 'forumx', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function forumx_page_type_list($pagetype, $parentcontext, $currentcontext)
{
    $forum_pagetype = array(
        'mod-forumx-*' => get_string('page-mod-forumx-x', 'forumx'),
        'mod-forumx-view' => get_string('page-mod-forumx-view', 'forumx'),
        'mod-forumx-discuss' => get_string('page-mod-forumx-discuss', 'forumx')
    );
    return $forum_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a forum.
 *
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 * @global moodle_database $DB The database connection
 */
function forumx_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null)
{
    global $DB;

    // If we are only after discussions we need only look at the forumx_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the forumx_posts table.
    if (!$discussionsonly) {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {forumx_discussions} fd
                         JOIN {forumx_posts} fp ON fp.discussion = fd.id
                        WHERE fp.userid = :userid )";
    } else {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {forumx_discussions} fd
                        WHERE fd.userid = :userid )";
    }

    $params = array('userid' => $user->id);

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a forum will be returned.
    $sql = "SELECT c.* $ctxselect
            FROM {course} c
            $ctxjoin
            WHERE c.id IN ($subquery)";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
    }
    return $courses;
}

/**
 * Gets all of the forums a user has posted in for one or more courses.
 *
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only forums where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of forums the user has posted within in the provided courses
 * @global moodle_database $DB
 */
function forumx_get_forums_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null)
{
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course ' . $coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
    $params['forum'] = 'forumx';

    if ($discussionsonly) {
        $join = 'JOIN {forumx_discussions} ff ON ff.forumx = f.id';
    } else {
        $join = 'JOIN {forumx_discussions} fd ON fd.forumx = f.id
                 JOIN {forumx_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {forumx} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {forumx} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :forum
                 {$coursewhere}";

    $courseforums = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $courseforums;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and forum is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->forums: An array of forums relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 * @global moodle_database $DB
 */
function forumx_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50)
{
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->forums = array();  // The forums that the current user can access that contain posts
    $return->posts = array();   // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'forumx');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'forumx');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user) && !is_enrolled($coursecontext, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'forumx');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B forum. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
                && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin || $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot . "/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the forum accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the forums that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which forums we can search by testing accessibility.
    $forums = forumx_get_forums_user_posted_in($user, array_keys($return->courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $forumsearchwhere = array();
    // Will be used to store the where condition params for the search
    $forumsearchparams = array();
    // Will record forums where the user can freely access everything
    $forumsearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the forums the user has posted in
    // and providing the current user can access the forum create a search condition
    // for the forum to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the forums
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['forumx'])) {
            // hmmm, no forums? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
            if (!$cm->uservisible || !isset($forums[$forumid])) {
                continue;
            }
            // Get the forum in question
            $forum = $forums[$forumid];

            // This is needed for functionality later on in the forum code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link forumx_print_post()}.
            $forum->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $forum->cm->$key = $value;
            }

            // Check that either the current user can view the forum, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/forumx:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/forumx:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain forum specific where clauses
            $forumsearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps' . $forumid . '_');
                    $forumsearchparams = array_merge($forumsearchparams, $groupid_params);
                    $forumsearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->forumx_enabletimedposts) && !has_capability('mod/forumx:viewhiddentimedposts', $cm->context)) {
                    $forumsearchselect[] = "(d.userid = :userid{$forumid} OR (d.timestart < :timestart{$forumid} AND (d.timeend = 0 OR d.timeend > :timeend{$forumid})))";
                    $forumsearchparams['userid' . $forumid] = $user->id;
                    $forumsearchparams['timestart' . $forumid] = $now;
                    $forumsearchparams['timeend' . $forumid] = $now;
                }

                // qanda access
                if ($forum->type == 'qanda' && !has_capability('mod/forumx:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda forum.
                    $discussionspostedin = forumx_discussions_user_has_posted_in($forum->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        $forumonlydiscussions = array();  // Holds discussion ids for the discussions the user is allowed to see in this forum.
                        foreach ($discussionspostedin as $d) {
                            $forumonlydiscussions[] = $d->id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($forumonlydiscussions, SQL_PARAMS_NAMED, 'qanda' . $forumid . '_');
                        $forumsearchparams = array_merge($forumsearchparams, $discussionid_params);
                        $forumsearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $forumsearchselect[] = "p.parent = 0";
                    }

                }

                if (count($forumsearchselect) > 0) {
                    $forumsearchwhere[] = "(d.forumx = :forum{$forumid} AND " . implode(" AND ", $forumsearchselect) . ")";
                    $forumsearchparams['forum' . $forumid] = $forumid;
                } else {
                    $forumsearchfullaccess[] = $forumid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $forumsearchfullaccess[] = $forumid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any forums where
    // the user has full access then we just return the default.
    if (empty($forumsearchwhere) && empty($forumsearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access forums.
    if (count($forumsearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($forumsearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $forumsearchparams = array_merge($forumsearchparams, $fullidparams);
        $forumsearchwhere[] = "(d.forumx $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we forumx_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.forumx, d.name AS discussionname, ' . $userfields . ' ';
    $wheresql = implode(" OR ", $forumsearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND (' . $wheresql . ')';
        }
    }

    $sql = "FROM {forumx_posts} p
            JOIN {forumx_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $forumsearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql . $sql, $forumsearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql . $sql . $orderby, $forumsearchparams, $limitfrom, $limitnum);

    // We need to build an array of forums for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these forums posts. Given we have the forums already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->forumx, $return->forums)) {
            $return->forums[$post->forumx] = $forums[$post->forumx];
        }
    }

    return $return;
}

/**
 * Set the per-forum maildigest option for the specified user.
 *
 * @param stdClass $forum The forum to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function forumx_set_user_maildigest($forum, $maildigest, $user = null)
{
    global $DB, $USER;

    if (is_number($forum)) {
        $forum = $DB->get_record('forumx', array('id' => $forum));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this forum.
    require_capability('mod/forumx:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = forumx_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_forumx');
    }

    // Attempt to retrieve any existing forum digest record.
    $subscription = $DB->get_record('forumx_digests', array(
        'userid' => $user->id,
        'forumx' => $forum->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('forumx_digests', array('forumx' => $forum->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('forumx_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->forumx = $forum->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('forumx_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified forum.
 *
 * @param Array $digests An array of forums and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $forumid The ID of the forum to check.
 * @return int The calculated maildigest setting for this user and forum.
 */
function forumx_get_user_maildigest_bulk($digests, $user, $forumid)
{
    if (isset($digests[$forumid]) && isset($digests[$forumid][$user->id])) {
        $maildigest = $digests[$forumid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function forumx_get_user_digest_options($user = null)
{
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0'] = get_string('emaildigestoffshort', 'mod_forumx');
    $digestoptions['1'] = get_string('emaildigestcompleteshort', 'mod_forumx');
    $digestoptions['2'] = get_string('emaildigestsubjectsshort', 'mod_forumx');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_forumx',
        $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}

/**
 * Determine the current context if one was not already specified.
 *
 * If a context of type context_module is specified, it is immediately
 * returned and not checked.
 *
 * @param int $forumid The ID of the forum
 * @param context_module $context The current context.
 * @return context_module The context determined
 */
function forumx_get_context($forumid, $context = null)
{
    global $PAGE;

    if (!$context || !($context instanceof context_module)) {
        // Find out forum context. First try to take current page context to save on DB query.
        if ($PAGE->cm && $PAGE->cm->modname === 'forumx' && $PAGE->cm->instance == $forumid
            && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id) {
            $context = $PAGE->context;
        } else {
            $cm = get_coursemodule_from_instance('forumx', $forumid);
            $context = \context_module::instance($cm->id);
        }
    }

    return $context;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param stdClass $forum forum object
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @since Moodle 2.9
 */
function forumx_view($forum, $course, $cm, $context)
{

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger course_module_viewed event.

    $params = array(
        'context' => $context,
        'objectid' => $forum->id
    );

    $event = \mod_forumx\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('forumx', $forum);
    $event->trigger();
}

/**
 * Trigger the discussion viewed event
 *
 * @param stdClass $modcontext module context object
 * @param stdClass $forum forum object
 * @param stdClass $discussion discussion object
 * @since Moodle 2.9
 */
function forumx_discussion_view($modcontext, $forum, $discussion)
{
    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
    );

    $event = \mod_forumx\event\discussion_viewed::create($params);
    $event->add_record_snapshot('forumx_discussions', $discussion);
    $event->add_record_snapshot('forumx', $forum);
    $event->trigger();
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function mod_forumx_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course)
{
    if (isguestuser($user)) {
        // The guest user cannot post, so it is not possible to view any posts.
        // May as well just bail aggressively here.
        return false;
    }
    $postsurl = new moodle_url('/mod/forumx/user.php', array('id' => $user->id));
    if (!empty($course)) {
        $postsurl->param('course', $course->id);
    }
    $string = get_string('forumposts', 'mod_forumx');
    $node = new core_user\output\myprofile\node('miscellaneous', 'forumxposts', $string, null, $postsurl);
    $tree->add_node($node);

    $discussionssurl = new moodle_url('/mod/forumx/user.php', array('id' => $user->id, 'mode' => 'discussions'));
    if (!empty($course)) {
        $discussionssurl->param('course', $course->id);
    }
    $string = get_string('myprofileotherdis', 'mod_forumx');
    $node = new core_user\output\myprofile\node('miscellaneous', 'forumxdiscussions', $string, null,
        $discussionssurl);
    $tree->add_node($node);

    return true;
}

/**
 * Select specific roles in a course that can be subscribed into a forum.
 * Unused. Use  \mod_forumx\subscriptions::get_potential_subscribers() instead.
 * @param int $contextid Course context id
 * @return array
 */
function forumx_get_users_allow_subscribe($contextid)
{
    global $DB;
    $sql = 'SELECT DISTINCT userid AS id FROM {role_assignments} WHERE contextid = ? AND roleid IN (12,87,22,17)';
    return $DB->get_records_sql($sql, array($contextid));
}

/**
 * Return users from a group of a certain name in a course.
 * @param int $courseid
 * @param string $groupname
 * @return array
 */
function forumx_get_users_in_groupname($courseid, $groupname = null)
{
    if (empty($groupname)) {
        return array();
    }
    global $DB;
    $sql = 'SELECT userid FROM {groups_members} m, {groups} g
	WHERE m.groupid = g.id
	AND g.courseid = ?
	AND g.name = ?';
    return $DB->get_records_sql($sql, array($courseid, $groupname));
}

/**
 * Adds a subscription to a new user.
 * @param int $userid
 * @param Context $context
 * @return boolean
 */
function forumx_add_user_default_subscriptions($userid, $context)
{

    //mtrace ("forumx_add_user_default_subscriptions");
    global $DB;
    if (empty($context->contextlevel)) {
        return false;
    }

    switch ($context->contextlevel) {

        case CONTEXT_SYSTEM:   // For the whole site.
            $rs = $DB->get_recordset('course', null, null, 'id');
            foreach ($rs as $course) {
                $subcontext = context_course::instance($course->id);
                forumx_add_user_default_subscriptions($userid, $subcontext);
            }
            $rs->close();
            break;

        case CONTEXT_COURSECAT:   // For a whole category.
            $rs = $DB->get_recordset('course', array('category' => $context->instanceid), null, 'id');
            foreach ($rs as $course) {
                $subcontext = context_course::instance($course->id);
                forumx_add_user_default_subscriptions($userid, $subcontext);
            }
            $rs->close();
            if ($categories = $DB->get_records('course_categories', array('parent' => $context->instanceid))) {
                foreach ($categories as $category) {
                    $subcontext = context_coursecat::instance($category->id);
                    forumx_add_user_default_subscriptions($userid, $subcontext);
                }
            }
            break;

        case CONTEXT_COURSE:   // For a whole course.
            if ($course = $DB->get_record('course', array('id' => $context->instanceid))) {
                if ($forums = get_all_instances_in_course('forumx', $course, $userid, false)) {
                    foreach ($forums as $forum) {
                        if ($forum->forcesubscribe != forumx_INITIALSUBSCRIBE) {
                            continue;
                        }
                        if ($modcontext = context_module::instance($forum->coursemodule)) {
                            if (has_capability('mod/forumx:viewdiscussion', $modcontext, $userid)) {
                                //yifatsh add  $is_users_in_exam
                                $is_users_in_exam = forumx_is_user_in_groupname($context->instanceid, $userid, 'exam');

                                if (false == $is_users_in_exam) {

                                    \mod_forumx\subscriptions::subscribe_user($userid, $forum);
                                }
                            }
                        }
                    }
                }
            }
            break;

        case CONTEXT_MODULE:   // Just one forumx.
            if ($cm = get_coursemodule_from_id('forumx', $context->instanceid)) {
                if ($forum = $DB->get_record('forumx', array('id', $cm->instance))) {
                    if ($forum->forcesubscribe != forumx_INITIALSUBSCRIBE) {
                        continue;
                    }
                    if (has_capability('mod/forumx:viewdiscussion', $context, $userid)) {
                        \mod_forumx\subscriptions::subscribe_user($userid, $forum);
                    }
                }
            }
            break;
    }

    return true;
}

/**
 * Recursively sets the discussion field to $discussionid on $postid and all its children
 * in forumx_post_read. used when moving a post
 * @param int $postid
 * @param int $discussionid
 */
function forumx_change_read_discussionid($postid, $discussionid)
{
    global $DB;
    $DB->set_field('forumx_read', 'discussionid', $discussionid, array('postid' => $postid));
    if ($posts = $DB->get_records('forumx_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            forumx_change_read_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Process images in message sent in notification email.
 * @param string $content the post's message
 * @return string $content the post's message after image handling
 */
function forumx_handle_images_mail($content)
{
    $imgregex = "/<\s*img.*?>/i";
    if (preg_match($imgregex, $content)) {
        $content = preg_replace($imgregex, '', $content);
        $content .= get_string('imageshandlednotification', 'forumx');
    }
    return $content;
}

/**
 * Copy attachments from one post to another.
 * Since there is no built in function for this we have to do it manually
 * @param int $oldcontextid context id of original post
 * @param int $newcontextid context id of new post
 * @param int $oldpostid id of original post
 * @param int $newpostid id of new post
 */
function forumx_copy_attachments($oldcontextid, $newcontextid, $oldpostid, $newpostid)
{
    $fs = get_file_storage();
    if ($files = $fs->get_area_files($oldcontextid, 'mod_forumx', 'attachment', $oldpostid, 'timemodified', false)) {
        foreach ($files as $file) {
            $newfile = new stdClass();
            $newfile->contextid = $newcontextid;
            $newfile->component = 'mod_forumx';
            $newfile->filearea = 'attachment';
            $newfile->itemid = $newpostid;
            $fs->create_file_from_storedfile($newfile, $file);
        }
    }
}

/**
 * Return number of all posts in a discussion
 * @param int $discussionid
 * @return number
 */
function forumx_count_discussion_posts($discussionid)
{
    global $DB;
    return $DB->count_records('forumx_posts', array('discussion' => $discussionid));
}

/**
 * Counts the amount of unread posts in the discussion, while ignoring old posts
 * @param stdClass $cm
 * @param int $discussionid
 * @return int
 */
function forumx_count_discussion_posts_unread($cm, $discussionid)
{
    global $CFG, $USER, $DB;

    $cutoffdate = time() - ($CFG->forumx_oldpostdays * DAYSECS);
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    $params = array('user' => $USER->id, 'instance' => $cm->instance, 'discussion' => $discussionid, 'date' => $cutoffdate);

    if (!empty($CFG->forumx_enabletimedposts)) {
        $timedsql = 'AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)';
        $time = round(time(), -2);
        $params['now1'] = $time;
        $params['now2'] = $time;
    } else {
        $timedsql = '';
    }

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :currentgroup OR d.groupid = -1)';
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = '';
            }
        } else {
            // Separate groups without access all.
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :currentgroup OR d.groupid = -1)';
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    } else {
        $groupselect = '';
    }

    $sql = 'SELECT COUNT(p.id) AS unread
	FROM {forumx_discussions} d
	JOIN {forumx_posts} p ON p.discussion = d.id
	LEFT JOIN {forumx_read} r ON (r.postid = p.id AND r.userid = :user)
	WHERE d.forumx = :instance AND d.id = :discussion
	AND p.modified >= :date AND r.id IS NULL
	' . $timedsql . '
	' . $groupselect;

    return $DB->count_records_sql($sql, $params);
}

/**
 * This function is used to extend the global navigation by adding forum nodes if there
 * is relevant content.
 *
 * @param navigation_node $navref
 * @param stdClass $course
 * @param stdClass $module
 * @param stdClass $cm
 */
function forumx_extend_navigation($navref, $course, $module, $cm)
{
    global $CFG, $OUTPUT, $USER;

    $limit = 5;

    $discussions = forumx_get_discussions($cm, 'd.timemodified DESC', false, -1, $limit);
    $discussioncount = forumx_get_discussions_count($cm);
    if (!is_array($discussions) || count($discussions) == 0) {
        return;
    }
    $discussionnode = $navref->add(get_string('discussions', 'forumx') . ' (' . $discussioncount . ')');
    $discussionnode->mainnavonly = true;
    $discussionnode->display = false; // Do not display on navigation (only on navbar)

    foreach ($discussions as $discussion) {
        $icon = new pix_icon('i/feedback', '');
        $url = new moodle_url('/mod/forumx/discuss.php', array('d' => $discussion->discussion));
        $discussionnode->add($discussion->subject, $url, navigation_node::TYPE_SETTING, null, null, $icon);
    }

    if ($discussioncount > count($discussions)) {
        if (!empty($navref->action)) {
            $url = $navref->action;
        } else {
            $url = new moodle_url('/mod/forumx/view.php', array('id' => $cm->id));
        }
        $discussionnode->add(get_string('viewalldiscussions', 'forumx'), $url, navigation_node::TYPE_SETTING, null, null, $icon);
    }

    $index = 0;
    $recentposts = array();
    $lastlogin = time() - COURSE_MAX_RECENT_PERIOD;
    if (!isguestuser() && !empty($USER->lastcourseaccess[$course->id])) {
        if ($USER->lastcourseaccess[$course->id] > $lastlogin) {
            $lastlogin = $USER->lastcourseaccess[$course->id];
        }
    }
    forumx_get_recent_mod_activity($recentposts, $index, $lastlogin, $course->id, $cm->id);

    if (is_array($recentposts) && count($recentposts) > 0) {
        $recentnode = $navref->add(get_string('recentactivity') . ' (' . count($recentposts) . ')');
        $recentnode->mainnavonly = true;
        $recentnode->display = false;
        foreach ($recentposts as $post) {
            $icon = new pix_icon('i/feedback', '');
            $url = new moodle_url('/mod/forumx/discuss.php', array('d' => $post->content->discussion));
            $title = $post->content->subject . "\n" . userdate($post->timestamp, get_string('strftimerecent', 'langconfig')) . "\n" . $post->user->firstname . ' ' . $post->user->lastname;
            $recentnode->add($title, $url, navigation_node::TYPE_SETTING, null, null, $icon);
        }
    }
}

/**
 * Return display detailed of the author of the discussion.
 * @param stdClass $discussion
 * @param stdClass $forumx
 * @param stdClass $modcontext
 * @return stdClass
 */
function forumx_get_discussion_user_data($discussion, $forumx, $modcontext)
{

    $by = new stdClass();
    if (!$forumx->hideauthor) {
        $by->name = fullname($discussion, has_capability('moodle/site:viewfullnames', $modcontext));
    } else {
        $by->name = get_string('forumauthorhidden', 'forumx');
    }
    $by->date = userdate($discussion->modified);
    $by->userdate = get_string('publishedby:namedate', 'forumx', $by);
    $by->postid = $discussion->postid;
    return $by;
}

/**
 * Return a list of discussions in forumx, including minimal data from first post and its author
 * @param stdClass $cm
 * @param int $forumx id of the forum
 * @return array array of discussions
 */
function forumx_get_discussions_top($cm, $forumx)
{
    global $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $modcontext = context_module::instance($cm->id);
    if (!has_capability('mod/forumx:viewdiscussion', $modcontext)) { // User must have permission to view discussions.
        return array();
    }

    if (!empty($CFG->forumx_enabletimedposts)) { // Users must fulfill timed posts.
        if (!has_capability('mod/forumx:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))';
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = ?';
                $params[] = $USER->id;
            }
            $timelimit .= ')';
        }
    } else
        $timelimit = '';

    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        if (($groupmode == VISIBLEGROUPS) || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = ? OR d.groupid = -1)';
                $params[] = $currentgroup;
            } else {
                $groupselect = '';
            }
        } else {
            // Separate groups without access all.
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = ? OR d.groupid = -1)';
                $params[] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    } else {
        $groupselect = '';
    }
    $allnames = get_all_user_name_fields(true, 'u');
    $sql = 'SELECT d.id, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, d.firstpost,
	p.id AS postid, p.subject, p.modified, p.userid,
	' . $allnames . ', u.email, u.picture, u.imagealt
		FROM {forumx_discussions} d
                   JOIN {forumx_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
		WHERE d.forumx = ? AND p.parent = 0 ' .
        $timelimit . ' ' . $groupselect .
        ' ORDER BY d.timemodified DESC';
    return $DB->get_records_sql($sql, $params);
}

function forumx_get_overview_title()
{
    return '<i class="fa fa-comments"></i>' . get_string('activityoverview', 'forumx');
}

/**
 * Return the role of the author of the post
 * @param int $courseid course id
 * @param int $forumid forum id
 * @param int $postid post id
 * @param stdClass $cm course module
 * @return int|null role type
 */
function forumx_get_post_role($courseid, $forumid, $postid, $cm)
{
    static $posting_roles = array();

    if (!isset($posting_roles[$forumid])) {
        $posting_roles[$forumid] = forumx_get_posting_roles($cm);
    }
    if (isset($posting_roles[$forumid][$postid])) {
        return $posting_roles[$forumid][$postid];
    }
    return null;
}

/**
 * Return the highest roles of users that posted in the forum.
 * The array structure is postid => roleid.
 * @param cm_info $cm
 * @return array
 */
function forumx_get_posting_roles($cm)
{
    global $DB, $USER;

    $context = context_module::instance($cm->id);
    if (!has_capability('mod/forumx:viewdiscussion', $context)) { // User must have perms to view discussions.
        return array();
    }

    $sql = 'SELECT p.id, ra.roleid AS role
	FROM {forumx_discussions} d
	JOIN {forumx_posts} p ON p.discussion = d.id
	JOIN {role_assignments} ra ON (ra.userid = p.userid)
	JOIN {role} r ON ra.roleid = r.id
	JOIN {context} t ON (ra.contextid = t.id)
	WHERE d.forumx = ?
	AND t.contextlevel = ' . CONTEXT_COURSE . '
	AND t.instanceid = d.course
	AND r.sortorder = (
		SELECT MIN( r2.sortorder )
		FROM {role_assignments} ra2, {context} c, {role} r2
		WHERE ra2.userid = p.userid AND ra2.contextid = c.id
		AND c.instanceid = d.course AND c.contextlevel = ' . CONTEXT_COURSE . '
		AND c.contextlevel = ' . CONTEXT_COURSE . ' AND ra2.roleid = r2.id)';

    return $DB->get_records_sql_menu($sql, array($cm->instance));
}

/**TODO: add to class?
 * Returns a post message content.
 * @param stdClass $post
 * @param stdClass $course
 * @param stdClass $forumx
 * @param stdClass $userfrom
 * @param stdClass $cm
 * @param bool $viewfullnames
 * @param int|string $timezone when sending email to a user, convert post date to the user's time zone
 */
function forumx_get_textonly_postmessage($post, $course, $forumx, $userfrom, $cm, $viewfullnames, $timezone = false, $strpostfix = null)
{
    global $CFG, $USER;

    $hideauthor = $forumx->hideauthor;

    // Format the post body.
    $options = new stdClass();
    $options->para = true;
    $formattedtext = format_text(trusttext_strip($post->message), $post->messageformat, $options, $course->id);

    $output = '<br>';
    $output .= get_string('strsubject' . $strpostfix, 'forumx') . ' ';
    $output .= format_string($post->subject) . ', ';
    $output .= '<br>';

    $timezone = $timezone !== false ? $timezone : $CFG->timezone;
    $date = userdate($post->modified, '', $timezone);
    if (!$hideauthor) {
        $by = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $post->userid . '">' . fullname($userfrom, $viewfullnames) . '</a>';
    } else {
        $by = get_string('forumauthorhidden', 'forumx') . ' ';
    }

    $output .= get_string('strpostby', 'forumx') . ' ';
    $output .= $by . ' ';
    $output .= get_string('date', 'forumx') . ' ';
    $output .= $date;
    $output .= '<br><br>';
    $output .= get_string('strmsgtitle', 'forumx') . ':<br><br>';
    $output .= $formattedtext . '<br><br>';

    return $output;
}

/**
 * Return the highest visible level role this user have in the course.
 * A hidden role is ignored.
 * @param int $userid
 * @param int $courseid
 * @param bool $index return the role index or the role name
 * @return int|string
 */
function forumx_get_user_main_role($userid, $courseid, $index = false)
{
    global $CFG, $DB;

    static $course_roles = array();

    if (!isset($course_roles[$courseid]))
        $course_roles[$courseid] = array();
    if (isset($course_roles[$courseid][$userid]))
        return $index ? $course_roles[$courseid][$userid]->roleid : $course_roles[$courseid][$userid]->rolename;

    $ouil_role_table = '';
    $ouil_role_condition = '';
    if (forumx_OUIL_TABLES == 1) {
        $ouil_role_table = ', {ouil_role_assignments} oura';
        $ouil_role_condition = 'AND oura.role_assignmentsid = ra.id AND oura.hidden = 0';
    }
    $sql = "SELECT r.id AS roleid, r.shortname AS rolename
	FROM {role_assignments} ra, {context} c, {role} r $ouil_role_table
	WHERE ra.userid = ?
	AND ra.contextid = c.id
	AND c.instanceid
	IN (?, 0)
	AND c.contextlevel IN (" . CONTEXT_COURSE . ", " . CONTEXT_SYSTEM . ")
	AND ra.roleid = r.id
	$ouil_role_condition
	ORDER BY c.contextlevel DESC, r.sortorder ASC
	LIMIT 1";

    if (!$role = $DB->get_record_sql($sql, array($userid, $courseid))) {
        $role = new \stdClass();
        $role->roleid = 0;
        $role->rolename = '';
    }
    $course_roles[$courseid][$userid] = $role;
    return $index ? $role->roleid : $role->rolename;
}

/**
 * Returns an HTML image element from pix directory.
 * @param string $name Image file name
 * @param string $source For use when the image is in another location other than forumx directory
 * @param array $attributes other image attributes
 * @return string the HTML element
 */
function forumx_image($name, $source = 'forumx', $attributes)
{
    global $OUTPUT;

    if (!$name) {
        return;
    }
    if (isset($attributes['title']) && !isset($attributes['alt'])) {
        $attributes['alt'] = $attributes['title']; // Make sure the screen reader will get something.
    }
    $image = '<img src="' . $OUTPUT->image_url($name, $source) . '"';
    foreach ($attributes as $name => $value)
        $image .= ' ' . $name . '="' . $value . '"';
    return $image . '>';
}

/**
 * Check if the forum is locked, or if unlocked for a period of time.
 * @param stdClass $forumx
 * @return boolean
 */
function forumx_is_forum_locked($forum)
{
    if ($forum->locked == forumx_LOCKED) {
        return true;
    } else if ($forum->locked == forumx_UNLOCKED) {
        if ($forum->unlocktimestart && $forum->unlocktimefinish) {
            $now = time();
            $starttime = $forum->unlocktimestart;
            $endtime = $forum->unlocktimefinish;
            if (($now > $starttime) && ($now < $endtime)) {
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }
    return false;
}

/**
 * Check if a user is in a group with a certain name within a given course.
 * @param int $courseid
 * @param int $userid
 * @param string $groupname
 * @return bool
 */
function forumx_is_user_in_groupname($courseid, $userid, $groupname)
{
    global $DB;

    $sql = 'SELECT COUNT(userid) AS counter FROM {groups_members} m, {groups} g
		WHERE m.groupid = g.id
		AND g.courseid = ?
		AND userid = ?
		AND g.name = ?';

    $useringroups = $DB->count_records_sql($sql, array($courseid, $userid, $groupname));
    return $useringroups > 0;
}

/**
 * Move a post or a discussion into a different forum.
 * @param stdClass $post
 * @param stdClass $source_forumx
 * @param stdClass $source_discussion
 * @param int|stdClass $target_forumx
 * @param stdClass $cm
 */
function forumx_move_post($post, $source_forumx, $source_discussion, $target_forumx, $cm)
{
    global $DB, $CFG, $OUTPUT;

    // Make sure we have the target forum.
    if (!is_object($target_forumx)) {
        if (!$target_forumx = $DB->get_record('forumx', array('id' => $target_forumx))) {
            print_error('invalidforumid', 'forumx');
        }
    }
    if ($target_forumx->type == 'single') { // Just for safety measures. should be checked before calling this function.
        print_error('cannotmovetosingleforum', 'forumx');
    }
    // A whole discussion is moved.
    if (empty($post->parent)) {
        //$discussionid  = $post->parent;
        $newdiscussion = $source_discussion;
        $DB->set_field('forumx_read', 'forumxid', $target_forumx->id, array('discussionid' => $source_discussion->id));
        $DB->set_field('forumx_discussions', 'name', $post->subject, array('id' => $source_discussion->id));
        $DB->set_field('forumx_posts', 'subject', $post->subject, array('id' => $post->id));
    } // A post is moved. Create a new discussion for this post.
    else {
        // Don't duplicate the original discussion, copy only the selected fields.
        $newdiscussion = new stdClass();
        $newdiscussion->course = $source_discussion->course;
        $newdiscussion->forumx = $source_discussion->forumx;
        $newdiscussion->name = $post->subject;
        $newdiscussion->firstpost = $post->id;
        $newdiscussion->userid = $source_discussion->userid;
        $newdiscussion->groupid = $source_discussion->groupid;
        $newdiscussion->assessed = $source_discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart = $source_discussion->timestart;
        $newdiscussion->timeend = $source_discussion->timeend;

        if (!$discussionid = $DB->insert_record('forumx_discussions', $newdiscussion)) {
            print_error('cannotcreatediscussion', 'forumx');
        }
        $newdiscussion->id = $discussionid;
        $newpost = new stdClass();
        $newpost->id = $post->id;
        $newpost->parent = 0;
        $newpost->subject = $post->subject;

        if (!$DB->update_record('forumx_posts', $newpost)) {
            $DB->delete_records('forumx_discussions', array('id' => $discussionid)); // Revert changes.
            print_error('couldnotupdateoriginalpost', 'forumx');
        }
        forumx_change_discussionid($post->id, $discussionid);
        forumx_discussion_update_last_post($source_discussion->id);    // Previous discussion.
        forumx_discussion_update_last_post($discussionid);        // New discussion.

        forumx_change_read_discussionid($post->id, $discussionid);
        // If a whole discussion is moved, remove all of its subscriptions.
        if (empty($post->parent)) {
            \mod_forumx\subscriptions::unsubscribe_all_users_from_discussion($source_discussion->id);
        }
    }

    // Now move the discussion.
    if (!forumx_move_attachments($newdiscussion, $source_forumx->id, $target_forumx->id)) {
        $OUTPUT->notification('Errors occurred while moving attachment directories - check your file permissions');
    }
    // Update the forumx id for the discussion.
    $DB->set_field('forumx_discussions', 'forumx', $target_forumx->id, array('id' => $newdiscussion->id));

    require_once($CFG->libdir . '/rsslib.php');
    require_once('rsslib.php');
    // Delete the RSS files for the 2 forumxs because we want to force
    // the regeneration of the feeds since the discussions have been moved.
    forumx_rss_delete_file($source_forumx);
    forumx_rss_delete_file($target_forumx);
    $context = context_module::instance($cm->id);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'fromdiscussionid' => $source_discussion->id,
            'todiscussionid' => $newdiscussion->id,
        )
    );
    $event = \mod_forumx\event\post_moved::create($params);
    $event->trigger();
}

/**
 * Move a post in the same forum.
 * @param stdClass $post
 * @param int $targetpostid
 * @param stdClass $source_forumx
 * @param stdClass $cm
 */
function forumx_move_post_sameforum($post, $targetpostid, $source_forumx, $cm)
{
    global $CFG, $DB;

    if ($target_forumx->type == 'single') { // A single discussion forum cannot have another discussion.
        print_error('cannotmovetosingleforum', 'forumx');
    }

    if (!$targetpost = $DB->get_record('forumx_posts', array('id' => $targetpostid))) {
        print_error('destinationpostnotexist', 'forumx');
    }

    if (!$target_discussion = $DB->get_record('forumx_discussions', array('id' => $targetpost->discussion))) {
        print_error('destinationpostdiscussionnotexist', 'forumx');
    }
    // Make sure target discussion is in the source forum.
    if ($target_discussion->forumx != $source_forumx->id)
        print_error('destinationnotsameforum', 'forumx');

    $source_discussionid = $post->discussion;
    $DB->set_field('forumx_posts', 'parent', $targetpostid, array('id' => $post->id));

    forumx_change_discussionid($post->id, $target_discussion->id);
    require_once($CFG->libdir . '/rsslib.php');
    require_once('rsslib.php');
    // Delete the RSS file for the forum because we want to force
    // the regeneration of the feed since the post have been moved.
    forumx_rss_delete_file($source_forumx);
    $context = context_module::instance($cm->id);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'fromdiscussionid' => $source_discussionid,
            'todiscussionid' => $target_discussion->id,
        )
    );
    $event = \mod_forumx\event\post_moved::create($params);
    $event->trigger();
}

/**
 * Returns latest posts from the news forum, if exists.
 * This function should operate as fastest as possible because it's called every time from the course page
 * @param stdClass $course
 * @param stdClass $coursecontext the course context
 * @param int $amount amount of last posts to display
 * @return string
 */
function forumx_print_forum_news_new_items($course, $coursecontext = null, $amount = -1)
{
    global $CFG, $OUTPUT, $USER;
    if (!$forumx = forumx_get_course_forum($course->id, 'news')) {
        return '';
    }
    $modinfo = get_fast_modinfo($course);

    if (empty($modinfo->instances['forumx'][$forumx->id])) {
        return '';
    }
    $cm = $modinfo->instances['forumx'][$forumx->id];

    $context = context_module::instance($cm->id);

    if (!has_capability('mod/forumx:viewdiscussion', $context)) {
        return '';
    }
    $now = time();
    $returnto = '';
    $footer = '';
    $new_period = $CFG->forumx_duration_new_message;
    $strftimerecent = '%d/%m/%Y';
    $stredit = get_string('editpost', 'forumx');
    $strdelete = get_string('delete', 'forumx');
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm, true);
    $header = "";

    // RSS button
    // First check if the forum has any rss to start with.
    // Also, the capability check 'moodle/course:view' was removed, because this function is called from whithin the course
    if ($forumx->rsstype == 1 && $forumx->rssarticles &&
        /*	 !isguestuser() && */
        isset($CFG->enablerssfeeds) && isset($CFG->forumx_enablerssfeeds) &&
        $CFG->enablerssfeeds && $CFG->forumx_enablerssfeeds) {
        $footer .= '<span class="newsfooter">' .
            rss_get_link($context->id, $USER->id, 'forumx', $forumx->id, get_string('rsssubscriberssdiscussions', 'forumx')) .
            '</span>';

        $header .= '<span class="newsHeader">' .
            rss_get_link($context->id, $USER->id, 'forumx', $forumx->id, get_string('rsssubscriberssdiscussions', 'forumx')) .
            '</span>';


    }
    $output = $header;
    if ($discussions = forumx_get_discussions($cm, 'p.modified DESC', true, $currentgroup, $amount)) {
        if (!$coursecontext)
            $coursecontext = context_course::instance($course->id);
        $canupdate = has_capability('moodle/course:update', $coursecontext);

        foreach ($discussions as $discussion) {
            $discussion->subject = $discussion->name;
            $discussion->subject = format_string($discussion->subject, true, $forumx->course);

            if ($discussion->userid == $USER->id)
                continue;

            $days_difference = floor(($now - $discussion->modified) / DAYSECS);
            $bulletin = 'bulletin';
            if ($days_difference > $new_period)
                break;


            $postdate = userdate($discussion->modified, $strftimerecent);
            $output .= '<span class="' . $bulletin . '">' . $postdate . '&nbsp;' .
                format_text($discussion->subject, $discussion->messageformat, NULL, $forumx->course) . '</span>';
            $discussion->message = file_rewrite_pluginfile_urls($discussion->message, 'pluginfile.php',
                $context->id, 'mod_forumx', 'post', $discussion->firstpost);
            $output .= '<li><span class="inplaceeditable inplaceeditable-text" data-inplaceeditable="1" data-component="format_topcoll" data-itemtype="sectionname" data-itemid="673673" data-value="מידע כללי" data-editlabel="New name for section מידע כללי" data-type="text" data-options="" id="yui_3_17_2_1_1482237349852_3234"  >' . format_text($discussion->message, $discussion->messageformat, NULL, $forumx->course) . "</span>";
            if ($discussion->attachment) {
                $attachements = forumx_print_attachments($discussion, $cm, 'html');
                $output .= $attachements;
            }

            $output .= '</li>';
        }//end foreach
    }
    if ($output != '') {
        $output = '<div id="newsareadiscussions" class="container-fluid" role="region" aria-label="' . get_string('landmark6', 'theme_ouil_elegance') . '"><ul>' . $output . '</ul></div>';
    }
    return $output;
}

function forumx_print_index($forums, $type, $course, $show_sections = true)
{
    if (empty($forums)) {
        return;
    }
}

/**TODO: move to class?
 * Print lock icon.
 * @return string HTML image element
 */
function forumx_print_lock()
{
    static $lock_icon = '';
    if (empty($lock_icon)) {
        $lock_icon = forumx_image('locked', 'forumx', array('title' => get_string('forumislocked', 'forumx')));
    }
    return $lock_icon;
}


/**TODO: move to class?
 * Print the Add Post button
 * @param bool &$canstart
 * @param stdClass $forumx
 * @param int $currentgroup
 * @param int $groupmode
 * @param stdClass $context
 * @return void|string
 */
function forumx_print_new_message_button(&$canstart, $forum, $currentgroup, $groupmode, $context)
{
    global $CFG, $OUTPUT;
    $button = '';
    if (forumx_is_forum_locked($forum)) {
        $button = '<span class="float_start">' .
            forumx_image('locked', 'forumx', array('alt' => '', 'title' => get_string('forumislocked', 'forumx'), 'class' => 'icon_image')) .
            get_string('forumislocked', 'forumx') . '</span>';
        $canstart = false;
    } else if ($canstart) {
        switch ($forum->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'forumx');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'forumx');
                break;
            default:
                $buttonadd = get_string('adddiscussion', 'forumx');
                break;
        }
        $button = '<button id="addquickdiscussion" class="clean_button">' .
            '<img src="' . $OUTPUT->image_url('i/pluswhite', 'forumx') . '" class="float_start" alt="">' . $buttonadd . '</button>';
    } else if (isguestuser() || !isloggedin() || $forum->type == 'news') {
        // No button and no info.
        return;
    } else if ($groupmode && has_capability('mod/forumx:startdiscussion', $context)) {
        // Inform users why they can not post new discussion.
        if (!$currentgroup) {
            $button = $OUTPUT->notification(get_string('cannotadddiscussionall', 'forumx'));
        } else if (!groups_is_member($currentgroup)) {
            $button = $OUTPUT->notification(get_string('cannotadddiscussion', 'forumx'));
        }
    }
    return $button;
}

/**
 * Print the dialog for a new discussion.
 * @param stdClass $forum
 * @param int $groupmode
 * @param int $currentgroup
 */
function forumx_print_new_message_dialog($forum, $groupmode, $currentgroup, $post_all_groups = false)
{
    global $CFG, $OUTPUT;

    $allgroups = $post_all_groups ? '<div>
			<label for="posttomygroups">' . get_string('posttomygroups', 'forumx') . '</label>
			<input type="checkbox" id="posttomygroups" name="posttomygroups"></div>' : '';
    $groupnotice = '';
    $groupinfo = '';
    $groupid = $groupmode > 0 && $currentgroup ? $currentgroup : -1;
    $groupinfo = '<input id="quickdiscussiongroupid" type="hidden" name="groupinfo" value="' . $groupid . '">';
    $quick_editor = '<div id="quickdiscussioncontainer" role="dialog" class="quickdialog dialogcontainer closed_dialog hidden_element">
		<div id="quicknewdiscussion" class="quicknewdialog">
		<form class="newdiscussionform" method="post" action="' . $CFG->wwwroot . '/mod/forumx/post.php?forumx=' . $forum->id . '">
				' . $groupinfo . '
				<button id="cancelquickdiscussion" class="close_dialog float_start" data-action="cancel" title="' .
        get_string('cancel', 'forumx') . '"><i aria-hidden="true" class="fa fa-close"></i>
				</button>
				<div id="newdiscussiontop" class="quickdialogtop"><span>' . get_string('addnewdiscussiontitle', 'forumx') . '</span></div>
				<div id="newdiscussionbody" class="quickdialogbody">
					<input type="text" autocomplete="off" name="quicksubject" data-required="true" aria-label="' .
        get_string('placeholderdiscussionsubject', 'forumx') . '" placeholder="' .
        get_string('placeholderdiscussionsubject', 'forumx') . '">
					<textarea name="quickmessage" data-required="true" aria-label="' .
        get_string('placeholderdiscussioncontent', 'forumx') . '" placeholder="' .
        get_string('placeholderdiscussioncontent', 'forumx') . '"></textarea>
					' . $allgroups . '
				</div>
				<div id="newdiscussionfooter" class="quickdialogfooter">
					<div class="quick_dialog_notice"></div>
					<div class="quick_dialog_alert" aria-live="assertive"></div>
					<button id="sendquickdiscussion" class="clean_button float_start">' .
        get_string('send', 'forumx') . '</button>
					<button id="advancededitdiscussion" class="clean_button float_end"><img src="' .
        $OUTPUT->image_url('i/edit', 'forumx') . '" aria-hidden="true">' .
        get_string('advancededit', 'forumx') . '</button>
					<div class="clearfix"></div>
				</div>
		</form>
		<div id="quickdiscussionmask" class="waitmask hidden_element">
			<span class="sr-only">SENDING</span>
		</div>
	</div></div>';
    return $quick_editor;
}

/**
 * Print the dialog for a new reply.
 * @param bool $is_discussion Is the parent the first post
 */
function forumx_print_quick_reply_dialog($is_discussion = false)
{
    global $CFG, $OUTPUT;
    $discussion = $is_discussion ? '<input type="hidden" name="returnto" value="discussion">' : '';
    $quick_editor = '<div id="quickreplycontainer" role="dialog" class="quickdialog dialogcontainer closed_dialog hidden_element">
		<div id="quicknewpost" class="quicknewdialog">
		<form class="newdiscussionform" method="post" action="' . $CFG->wwwroot . '/mod/forumx/post.php?reply=">
				<input type="hidden" name="replyto" value="">' . $discussion . '
				<button id="cancelquickpost" class="close_dialog float_start" data-action="cancel" title="' .
        get_string('cancel', 'forumx') . '"><i aria-hidden="true" class="fa fa-close"></i></button>
				<div id="newposttop" class="quickdialogtop">
						<div id="quickreplytitle">
							<span>' . get_string('replyprefix', 'forumx') . '</span>
							<button id="quickedittitle" class="quickdialogbutton" title="' .
        get_string('editsubjectstart', 'forumx') . '"><i class="fa fa-pencil"></i></button>
						</div>
						<div id="quickreplytitleedit">
							<input type="text" id="quickreplysubject" autocomplete="off" name="quicksubject" data-required="true" placeholder="' .
        get_string('placeholderreplysubject', 'forumx') . '">
							<button id="quickclosetitle" class="quickdialogbutton" title="' .
        get_string('editsubjectcancel', 'forumx') . '"><i class="fa fa-close"></i></button>
						</div>
				</div>
				<div id="newpostbody" class="quickdialogbody">
					<textarea name="quickmessage" data-required="true" aria-label="' .
        get_string('placeholderreplycontent', 'forumx') . '" placeholder="' .
        get_string('placeholderreplycontent', 'forumx') . '"></textarea>
				</div>
				<div id="newpostfooter" class="quickdialogfooter">
					<div class="quick_dialog_alert" aria-live="assertive"></div>
					<button id="sendquickpost" class="clean_button float_start conditional_button">' .
        get_string('send', 'forumx') . '</button>
					<button id="advancededitpost" class="clean_button float_end conditional_button"><img src="' .
        $OUTPUT->image_url('i/edit', 'forumx') . '" aria-hidden="true">' .
        get_string('advancededit', 'forumx') . '</button>
					<div class="clearfix"></div>
				</div>
		</form>
		<div id="quickpostmask" class="waitmask hidden_element">
			<span class="sr-only">SENDING</span>
		</div>
	</div></div>';
    return $quick_editor;
}

/**
 * Print the dialog for a post forward.
 */
function forumx_print_quick_forward_dialog()
{
    global $CFG, $OUTPUT;
    $quick_editor = '<div id="quickforward" role="dialog" class="quickdialog quickforward closed_dialog hidden_element">
		<form class="forwardform" method="post" action="' . $CFG->wwwroot . '/mod/forumx/forward.php" novalidate>
				<div id="forwardtop" class="quickdialogtop"></div>
				<div id="forwardbody" class="quickdialogbody">
					<label for="forwardemail">' . get_string('forwardemailaddress', 'forumx') .
        '</label><input type="email" name="quickemail" dir="ltr" data-required="true" id="forwardemail"><br>
					<label for="forwardsubject">' . get_string('subject', 'forumx') .
        '</label><input type="text" autocomplete="off" name="quicksubject" id="forwardsubject" data-required="false"><br>
					<label for="ccme">' . get_string('forwardccme', 'forumx') .
        '</label> <input name="quickccme" type="checkbox" value="1" id="ccme"><br><br>
					<label for="content">' . get_string('forwardintro', 'forumx') .
        '</label><textarea name="quickmessage" id="forwardcontent" data-required="true"></textarea>
				</div>
				<div id="forwardfooter" class="quickdialogfooter">
					<div class="quick_dialog_alert" aria-live="assertive"></div>
					<button id="advancedforward" class="clean_button float_start conditional_button"><img src="' .
        $OUTPUT->image_url('i/edit', 'forumx') . '" aria-hidden="true">' .
        get_string('advancededit', 'forumx') . '</button>
					<div class="float_end"><button id="sendforward" class="clean_button conditional_button">' .
        get_string('send', 'forumx') . '</button>
					<button id="cancelforward" class="clean_button" data-action="cancel">' .
        get_string('cancel', 'forumx') . '</button>
					</div>
					<div class="clearfix"></div>
				</div>
		</form>
		<div id="forwardmask" class="waitmask hidden_element">
			<span class="sr-only">SENDING</span>
		</div>
	</div>';
    return $quick_editor;
}

/**
 * Returns latest posts from the news forum, if exists.
 * This function should operate as fastest as possible because it's called every time from the course page
 * @param stdClass $course
 * @param stdClass $coursecontext the course context
 * @param int $amount amount of last posts to display
 * @return string
 */
function forumx_print_news_items_orig($course, $coursecontext = null, $showallnews = 0, $amount = -1)
{
    global $CFG, $OUTPUT, $USER, $PAGE;
    $forumx = 0;
    if (!$forumx = forumx_get_course_forum($course->id, 'news')) {
        return '';
    }


    $PAGE->requires->js_init_call('M.theme_ouil_elegance_news.init', array(
        $forumx->id,
        $PAGE->url->get_path() . "?id=" . $course->id
    ));
    //required
    $PAGE->requires->strings_for_js(array('required'), 'moodle');


    $modinfo = get_fast_modinfo($course);

    if (empty($modinfo->instances['forumx'][$forumx->id]))
        return '';

    $cm = $modinfo->instances['forumx'][$forumx->id];

    $context = context_module::instance($cm->id);

    if (!has_capability('mod/forumx:viewdiscussion', $context))
        return '';

    $now = time();
    $returnto = '';
    $footer = '';
    $new_period = $CFG->forumx_duration_new_message;
    $strftimerecent = '%d/%m/%Y';
    $stredit = get_string('editpost', 'forumx');
    $strdelete = get_string('delete', 'forumx');
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm, true);
    $header = "";
    $has_new_items = false;
    $output = '';

    if (forumx_user_can_post_discussion($forumx, $currentgroup, $groupmode, $cm, $context)) {
        $returnto = '&returnto=' . $CFG->wwwroot . '/course/view.php?id=' . $course->id;
        $footer = '<div class="newsfooter hidden-xs"><a href="' . $CFG->wwwroot . '/mod/forumx/post.php?forumx=' . $forumx->id . $returnto . '">' .
            get_string('addnewpost', 'forumx') . '</a>...</div>';
        $header = '<div class="newsheader visible-xs " id ="add_news"><div id ="add_news_item"><div id="add_news_item_bullet"><span class="fa  fa-plus-square"></span></div><div class="newsbody add_news">' . get_string('addnewpost', 'forumx') . '...</div></div></div>';
    }
    // RSS button
    // First check if the forum has any rss to start with.
    // Also, the capability check 'moodle/course:view' was removed, because this function is called from whithin the course
    if ($forumx->rsstype == 1 && $forumx->rssarticles &&
        /*	 !isguestuser() && */
        isset($CFG->enablerssfeeds) && isset($CFG->forumx_enablerssfeeds) &&
        $CFG->enablerssfeeds && $CFG->forumx_enablerssfeeds) {
        $footer .= '<span class="newsfooter hidden-xs ">' .
            rss_get_link($context->id, $USER->id, 'forumx', $forumx->id, get_string('rsssubscriberssdiscussions', 'forumx')) .
            '</span>';
    }
    if (!$discussions = forumx_get_discussions($cm, 'p.modified DESC', true, $currentgroup, $amount)) {
        $output = get_string('nonews', 'forumx') . '<br>' . $header . "<br>" . $footer;
    } else {

        $scroll = "newsareascroll";
        if ($showallnews == true) {
            $scroll = "";
        }
        $output = $header . '<div id="newsareadiscussions" class="container-fluid ' . $scroll . '" role="region" aria-label="' . get_string('landmark6', 'theme_ouil_elegance') . '">';

        if (!$coursecontext)
            $coursecontext = context_course::instance($course->id);
        $canupdate = has_capability('moodle/course:update', $coursecontext);

        $discussion_counter = 0;
        foreach ($discussions as $discussion) {
            $discussion_counter++;
            $actions = "";
            $discussion->subject = $discussion->name;
            $discussion->subject = format_string($discussion->subject, true, $forumx->course);


            $can_edit = false;
            if ($canupdate) {
                $actions = '<div  class="newscontrols" >';
                if (($now - $discussion->created) < $CFG->maxeditingtime) {
                    $actions .= '<span class="editpost hidden-xs">
								<a href="' . $CFG->wwwroot . '/mod/forumx/post.php?edit=' . $discussion->id . $returnto . '">' . $stredit . '</a>
								</span>';
                    $can_edit = true;
                }
                $actions .= '<span class="news_post_action" >
							<a href="' . $CFG->wwwroot . '/mod/forumx/post.php?delete=' . $discussion->id . $returnto . '"><i class="fa fa-close"></i>' . $strdelete . '</a>
							</span>';
                $actions .= '</div>';
            }

            $days_difference = floor(($now - $discussion->modified) / DAYSECS);
            $bulletin = 'bulletin';
            $bulletin = 'fa fa-square';
            //$bulletin = '<i class="fa fa-square"></i>';

            $action = '';
            if ($can_edit) {
                //$bulletin='';
                $bulletin = 'fa fa-edit';
                $has_new_items = false;
                $action = 'edit';
            } else if ($days_difference < $new_period) {
                $bulletin = 'new';
                $has_new_items = true;
                $action = 'new';
            }
            $news_item_status = '';
            $news_item = '';
            $postdate = userdate($discussion->modified, $strftimerecent);

            if ($action == '') {
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="' . $bulletin . ' "></span>';
            } else if ($action == 'new') {
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="' . $bulletin . ' mobilehidden "></span>';
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="fa fa-square  desktophidden "></span>';
            } else if ($action == 'edit') {
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="' . $bulletin . ' desktophidden "></span>';
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="fa fa-square  mobilehidden "></span>';
            }

            $news_body = '<span class="posttitle" ><span class="postdate" >' . $postdate . ' - </span>' .
                format_text($discussion->subject, $discussion->messageformat, NULL, $forumx->course) . '</span>';

            $discussion->message = file_rewrite_pluginfile_urls($discussion->message, 'pluginfile.php',
                $context->id, 'mod_forumx', 'post', $discussion->firstpost);
            $li_class = '';
            if ($showallnews == false) {
                $li_class = "news_item hidden-xs";
                if ($discussion_counter == 1) {
                    $li_class = "news_item1";
                } else if (($discussion_counter == 2) || ($discussion_counter == 3)) {
                    $li_class = "news_item2 mobilehidden";
                }
            }


            $news_item = '<div class ="' . $li_class . '"   id ="newsbody' . $discussion->id . '" >';
            $news_item .= '<div   id ="newsbody' . $discussion->id . '_item"  >';
            $news_item .= '<div class="bullet">' . $news_item_status . '</div>';
            $news_item .= '<div class="newsbody" id ="newsbody' . $discussion->id . '"  >' . $news_body . format_text($discussion->message, $discussion->messageformat, NULL, $forumx->course);
            if ($discussion->attachment) {
                $attachements = forumx_print_attachments($discussion, $cm, 'html');
                $news_item .= $attachements;
            }


            $news_item .= $actions . '</div></div></div>';

            $output .= $news_item;


        }//end foreach

        $output .= '</div>' . $footer;
        if (!$showallnews) {
            $output .= '<div  id ="get_all_message" class="get_all_message  mobilehidden visible-xs"><a href="' . $CFG->wwwroot . '/mod/forumx/news_items.php?id=' . $course->id . '">' . get_string('to_all_news', 'forumx') . '</a></div>';
            $output .= '<div id="mobile_close_news" class="visible-xs"><i  id ="mobile_news_action" class="fa fa-chevron-circle-down"></i></div>';
        }
    }//else has news

    /*
	$output='<div id="newsarea" tabindex="0">
	<h2 class="quicklinksheader"><i class="fa fa-bullhorn"></i>'. get_string('news_area', 'format_topcoll').'
			</h2>'.$output.'</div>';
	*/
    $container = '<div id="newsarea" tabindex="0"><div id="newsarea_border" >
				<h2 class="quicklinksheader"><i class="fa fa-bullhorn"></i>' . get_string('news_area', 'format_topcoll') . '</h2>';
    if ($has_new_items) {
        $container .= '<div class="new_items_header visible-xs"><span> ' . get_string('unread', 'forumx') . '</span></div>';
    }
    $container .= $output . '</div></div>';


    return $container;
}

/**TODO: replace with class
 * Return a post in a print and email friendly format
 * @param stdClass $post post data with extended information, filled from forumx_get_post_full()
 * @param stdClass $cm
 * @param stdClass $course
 * @param stdClass $forumx
 * @param stdClass $data form information
 * @param string $preface optional preview
 * @param bool $add_header if true, add <head> and stylesheets links
 * @param int|string $timezone when sending email to a user, convert post date to the user's time zone
 * @param bool $is_email is the function used for email output
 * @return array the post in two formats, plain and html
 */
function forumx_print_post_plain($post, $cm, $course, $forumx, $data = null, $preface = null, $add_header = true, $timezone = false, $is_email = true)
{
    // we need all this
    if (!$post || !$cm || !$course || !$forumx || !$data)
        return array();

    global $CFG, $DB;
    $message_text = $message_html = '';

    require_once($CFG->libdir . '/filelib.php');
    $modcontext = context_module::instance($cm->id);
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_forumx', 'post', $post->id);

    if ($is_email) {
        if ($add_header) {
            $message_html = '<head>';
            $message_html .= "</head>\n<html><body id='email' dir='" . get_string('thisdirection', 'langconfig') . "'>\n";
        }

        if (!empty($preface)) {
            $message_html .= $preface;
            $message_text = format_text_email($preface, $data->format);
        }

        // sanity check
        if (!is_array($data->message))
            $data->message = array('text' => $data->message, 'format' => $data->format);

        // Include intro if specified
        if (!empty($data->message['text'])) {
            $message_text .= "\n" . forumx_EMAIL_DIVIDER . "\n";
            if ($is_email) $message_html .= '<hr size="1" noshade="noshade" />';

            // Add intro
            $message = trusttext_strip(stripslashes($data->message['text']));
            $message_html .= format_text($message, $data->format);
            $message_text .= format_text_email($message, $data->format);
        }
    }
    //now add the post
    $message_text .= "\n" . forumx_EMAIL_DIVIDER . "\n";
    if ($is_email) $message_html .= '<hr size="1" noshade="noshade" />';

    // Build an object that represents the posting user
    $postuser = $DB->get_record('user', array('id' => $post->userid));
    $post_message = forumx_get_textonly_postmessage($post, $course, $forumx, $postuser, $cm, true, $timezone, 'print');
    $message_html .= $post_message;
    $message_text .= format_text_email($post_message, $data->format);

    if ($post->attachment) {
        list($attachments, $attachedimages) = forumx_print_attachments($post, $cm, 'separateimages');
        $message_html .= '<div class="attachedimages">' . $attachedimages . '</div>';
        $message_text .= "\n" . $attachedimages . "\n";
    }

    if ($is_email) {
        $do_not_reply_email = '----- ' . get_string('donotreplyemail', 'forumx') . ' -----';
        $post_link = '<a target="_blank" href="' . $CFG->wwwroot . '/mod/forumx/discuss.php?d=' . $post->discussion . '&amp;postid=' . $post->id . '">' .
            get_string('postincontext', 'forumx') . '</a> | <a target="_blank" href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '">' .
            get_string('tocoursesite', 'forumx') . '</a>';
        $message_html .= '<div>' . $post_link . '</div><br>' . $do_not_reply_email;
        $message_text .= "\n" . $post_link . "\n\n" . $do_not_reply_email;

        if ($add_header)
            $message_html .= "</body></html>";
    }
    return array(FORMAT_HTML => $message_html, FORMAT_PLAIN => $message_text);
}


/**
 * Removes user's tracking from forum(s)
 * @param int $userid
 * @param Context $context Defines the level in which to work.
 * It can be a single module, a whole course, a whole category and so on
 * @return boolean
 */
function forumx_remove_user_tracking($userid, $context)
{
    global $CFG, $DB;

    if (empty($context->contextlevel))
        return false;

    switch ($context->contextlevel) {

        case CONTEXT_SYSTEM:   // For the whole site
            // find all courses in which this user has tracking info
            $allcourses = array();
            if ($courses = $DB->get_records_sql('SELECT c.id
                                              FROM {course} c,
                                                   {forumx_read} fr,
                                                   {forumx} f
                                                   WHERE c.id = f.course AND f.id = fr.forumxid AND fr.userid = ?
                                                   GROUP BY c.id', array($userid))) {
                $allcourses = $allcourses + $courses;
            }
            if ($courses = $DB->get_records_sql('SELECT c.id
                                              FROM {course} c,
                                                   {forumx_track_prefs} ft,
                                                   {forumx} f
                                             WHERE c.id = f.course AND f.id = ft.forumxid AND ft.userid = ?', array($userid))) {
                $allcourses = $allcourses + $courses;
            }
            foreach ($allcourses as $course) {
                $subcontext = context_course::instance($course->id);
                forumx_remove_user_tracking($userid, $subcontext);
            }
            break;

        case CONTEXT_COURSECAT:   // For a whole category
            if ($courses = $DB->get_records('course', array('category' => $context->instanceid), '', 'id')) {
                foreach ($courses as $course) {
                    $subcontext = context_course::instance($course->id);
                    forumx_remove_user_tracking($userid, $subcontext);
                }
            }
            if ($categories = $DB->get_records('course_categories', array('parent' => $context->instanceid), '', 'id')) {
                foreach ($categories as $category) {
                    $subcontext = context_coursecat::instance($category->id);
                    forumx_remove_user_tracking($userid, $subcontext);
                }
            }
            break;

        case CONTEXT_COURSE:   // For a whole course
            if ($course = $DB->get_record('course', array('id' => $context->instanceid), 'id')) {
                // find all forumxs in which this user has reading tracked
                if ($forumxs = $DB->get_records_sql('SELECT f.id, cm.id as coursemodule
                                                 FROM {forumx} f,
                                                      {modules} m,
                                                      {course_modules} cm,
                                                      {forumx_read} fr
                                                WHERE fr.userid = ? AND f.course = ?
                                                      AND fr.forumxid = f.id AND cm.instance = f.id
                                                      AND cm.module = m.id AND m.name = "forumx"', array($userid, $context->instanceid))) {
                    foreach ($forumxs as $forumx) {
                        if ($modcontext = context_module::instance($forumx->coursemodule)) {
                            if (!has_capability('mod/forumx:viewdiscussion', $modcontext, $userid)) {
                                forumx_tp_delete_read_records($userid, -1, -1, $forumx->id);
                            }
                        }
                    }
                }

                // find all forumxs in which this user has a disabled tracking
                if ($forumxs = $DB->get_records_sql('SELECT f.id, cm.id as coursemodule
                                                 FROM {forumx} f,
                                                      {modules} m,
                                                      {course_modules} cm,
                                                      {forumx_track_prefs} ft
                                                WHERE ft.userid = ? AND f.course = ?
                                                      AND ft.forumxid = f.id AND cm.instance = f.id
                                                      AND cm.module = m.id AND m.name = "forumx"', array($userid, $context->instanceid))) {
                    foreach ($forumxs as $forumx) {
                        if ($modcontext = context_module::instance($forumx->coursemodule)) {
                            if (!has_capability('mod/forumx:viewdiscussion', $modcontext, $userid)) {
                                $DB->delete_records('forumx_track_prefs', array('userid' => $userid, 'forumxid' => $forumx->id));
                            }
                        }
                    }
                }
            }
            break;

        case CONTEXT_MODULE:   // Just one forumx
            if ($cm = get_coursemodule_from_id('forumx', $context->instanceid)) {
                if ($forumx = $DB->get_record('forumx', array('id', $cm->instance))) {
                    if (!has_capability('mod/forumx:viewdiscussion', $context, $userid)) {
                        $DB->delete_records('forumx_track_prefs', array('userid' => $userid, 'forumxid' => $forumx->id));
                        forumx_tp_delete_read_records($userid, -1, -1, $forumx->id);
                    }
                }
            }
            break;
    }
    return true;
}

/**
 * Called when a role is assigned to a user
 * @param int $userid
 * @param Context $context
 * @param int $roleid
 * @return boolean
 */
function forumx_role_assign($userid, $context, $roleid)
{
    // check to see if this role comes with mod/forumx:initialsubscriptions
    $cap = role_context_capabilities($roleid, $context, 'mod/forumx:initialsubscriptions');
    $cap1 = role_context_capabilities($roleid, $context, 'moodle/course:view');
    // we are checking the role because has_capability() will pull this capability out
    // from other roles this user might have and resolve them, which is no good
    // the role needs course view to
    if (isset($cap['mod/forumx:initialsubscriptions']) && $cap['mod/forumx:initialsubscriptions'] == CAP_ALLOW &&
        isset($cap1['moodle/course:view']) && $cap1['moodle/course:view'] == CAP_ALLOW) {
        return forumx_add_user_default_subscriptions($userid, $context);
    } else {
        // MDL-8981, do not subscribe to forumx
        return true;
    }
}

/**
 * Remove subscription and tracking of user from forum(s) and discussion(s)
 * @param int $userid
 * @param Context $context
 * @return boolean
 */
function forumx_role_unassign($userid, $context)
{
    if (empty($context->contextlevel)) {
        return false;
    }
    $forums = get_fast_modinfo($context->instance)->get_instances_of('forumx');
    foreach ($forums as $id => $cm) {
        $forum = new \stdClass();
        $forum->id = $id;
        \mod_forumx\subscriptions::unsubscribe_user($userid, $forum, null, true);
    }
    forumx_remove_user_tracking($userid, $context);

    return true;
}

/**TODO: update target events
 * Add or update the subscription to a discussion. Called after posting in a forum.
 * @param int $subscription The new subscription mode.
 * @param stdClass $discussionid
 * @param int $forumid
 * @param int $userid The $USER id. Needed for the actual subscription functions.
 * @param int $postuser The id of the owner of the post.
 * @param context_module $context
 */
function forumx_set_post_subscription($subscription, $discussion, $forumid, $userid, $postuser, $context)
{

    // Subscribe to discussion.
    if ($subscription == 1) {
        if (\mod_forumx\subscriptions::subscribe_user_to_discussion($userid, $discussion)) {
            $params = array(
                'context' => $context,
                'objectid' => $discussionid,
                'relateduserid' => $postuser,
                'other' => array(
                    'forumxid' => $forumid,
                )
            );
            $event = \mod_forumx\event\subscription_discussion_created::create($params);
            $event->trigger();
        }
    } // Unsubscribe from discussion.
    else {
        if (\mod_forumx\subscriptions::unsubscribe_user_from_discussion($userid, $discussion)) {
            $params = array(
                'context' => $context,
                'objectid' => $discussionid,
                'relateduserid' => $postuser,
                'other' => array(
                    'forumxid' => $forumid,
                )
            );
            $event = \mod_forumx\event\subscription_discussion_deleted::create($params);
            $event->trigger();
        }
    }
}

/**
 * Returns a role icon, if the role is in known list
 * @param string $role role name
 * @return null|string role icon or null
 */
function forumx_user_role_icon($role = null)
{
    if (empty($role)) {
        return;
    }
    if (is_object($role)) {
        $role = $role->roleid;
    }
    static $role_icons = array();
    if (empty($role_icons)) {
        $role_icons = array('teacher' => 'teacher', 'editingteacher' => 'editingteacher', 'manager' => 'teacher');
    }
    if (isset($role_icons[$role])) {
        return forumx_image('i/' . $role_icons[$role], 'forumx', array(
            'title' => get_string('publishedby:' . $role_icons[$role], 'forumx'),
            'class' => 'role_icon'));
    }
}

/**
 * Subscribe a user to all forums in the same groupings.
 * @param unknown $courseid
 * @param unknown $userid
 * @param unknown $groupid
 */
function forumx_forcesubscribe_user_by_groupid($courseid, $userid, $groupid)
{
    //mtrace (" forumx_forcesubscribe_user_by_groupid 8");

    global $CFG, $DB;
    $forumsids = array();
    $is_users_in_exam = forumx_is_user_in_groupname($courseid, $userid, 'exam');
    //if ($is_users_in_exam){ 	mtrace ("<br> is_users_in_exam  true"); 	}else{	mtrace ("<br> is_users_in_exam  false");	}
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
        $forumsids[] = $forumid;
    }
    if (empty($forumsids)) {
        return;
    }

    list($query_sql, $query_params) = $DB->get_in_or_equal($forumsids);
    $query_params[] = forumx_FORCESUBSCRIBE;
    $query_params[] = forumx_INITIALSUBSCRIBE;
    $sql = 'SELECT * FROM {forumx} WHERE id ' . $query_sql . '
		AND (forcesubscribe = ? OR forcesubscribe = ?)';

    if (!$target_forums = $DB->get_records_sql($sql, $query_params)) {
        return;
    }
    foreach ($target_forums as $forum) {
        if ($is_users_in_exam) {
            \mod_forumx\subscriptions::unsubscribe_user($userid, $forum);
        }//$is_users_in_exam
        else {
            $cm = get_coursemodule_from_instance('forumx', $forum->id, $courseid);
            $context = context_module::instance($cm->id);
            $allowforcesubscribe = has_capability('mod/forumx:allowforcesubscribe', $context, $userid);
            //if (!$allowforcesubscribe) { mtrace ("<BR>  no mod/forumx:allowforcesubscribe");	}
            if ($allowforcesubscribe) {
                $uservisible = \core_availability\info_module::is_user_visible($cm, $userid, false);
                if ($uservisible) {
                    \mod_forumx\subscriptions::subscribe_user($userid, $forum);
                }
            }
        }
    }
}

function forumx_forceunsubscribe_user($courseid, $userid)
{
    //mtrace (" forumx_force_unsubscribe_user ");

    global $CFG, $DB;
    $forumsids = array();
    $is_users_in_exam = forumx_is_user_in_groupname($courseid, $userid, 'exam');
    //if ($is_users_in_exam){ 	mtrace ("<br> is_users_in_exam  true"); 	}else{	mtrace ("<br> is_users_in_exam  false");	}
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
        $forumsids[] = $forumid;
    }
    if (empty($forumsids)) {
        return;
    }

    list($query_sql, $query_params) = $DB->get_in_or_equal($forumsids);
    $query_params[] = forumx_FORCESUBSCRIBE;
    $query_params[] = forumx_INITIALSUBSCRIBE;
    $sql = 'SELECT * FROM {forumx} WHERE id ' . $query_sql . '
	AND (forcesubscribe = ? OR forcesubscribe = ?)';

    if (!$target_forums = $DB->get_records_sql($sql, $query_params)) {
        return;
    } else {
        foreach ($target_forums as $forum) {
            if ($is_users_in_exam) {
                \mod_forumx\subscriptions::unsubscribe_user($userid, $forum);
            }//$is_users_in_exam
            else {
                $cm = get_coursemodule_from_instance('forumx', $forum->id, $courseid);
                $context = context_module::instance($cm->id);
                $uservisible = \core_availability\info_module::is_user_visible($cm, $userid, false);
                if (!$uservisible) {
                    \mod_forumx\subscriptions::unsubscribe_user($userid, $forum);
                }
            }
        }
    }
}

function forumx_forceunsubscribe_user_role($courseid, $userid)
{
    //mtrace (" forumx_force_unsubscribe_user _ role ");

    global $CFG, $DB;
    $forumsids = array();
    $is_users_in_exam = forumx_is_user_in_groupname($courseid, $userid, 'exam');
    //if ($is_users_in_exam){ 	mtrace ("<br> is_users_in_exam  true"); 	}else{	mtrace ("<br> is_users_in_exam  false");	}
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
        $forumsids[] = $forumid;
    }
    if (empty($forumsids)) {
        return;
    } else {
        list($query_sql, $query_params) = $DB->get_in_or_equal($forumsids);
        $query_params[] = forumx_FORCESUBSCRIBE;
        $query_params[] = forumx_INITIALSUBSCRIBE;
        $sql = 'SELECT * FROM {forumx} WHERE id ' . $query_sql . '
		AND (forcesubscribe = ? OR forcesubscribe = ?)';

        if (!$target_forums = $DB->get_records_sql($sql, $query_params)) {
            return;
        } else {
            foreach ($target_forums as $forum) {
                if ($is_users_in_exam) {
                    \mod_forumx\subscriptions::unsubscribe_user($userid, $forum);
                }//$is_users_in_exam
                else {
                    $cm = get_coursemodule_from_instance('forumx', $forum->id, $courseid);
                    $context = context_module::instance($cm->id);

                    $allowforcesubscribe = has_capability('mod/forumx:allowforcesubscribe', $context, $userid);
                    $uservisible = \core_availability\info_module::is_user_visible($cm, $userid, false);
                    if ((!$uservisible) || (!$allowforcesubscribe)) {
                        \mod_forumx\subscriptions::unsubscribe_user($userid, $forum);
                    }
                }
            }
        }
    }
}

/**
 * Removes subscriptions for a user in a context.
 * @param int $forumxid
 * @param int $courseid
 */
function forumx_clear_subscribe($forumid = null, $courseid = null, $deletePermission = true, $dbug = false)
{
    global $CFG, $DB;

    $forum = $DB->get_record('forumx', array('id' => $forumid), '*', MUST_EXIST);
    //$cm = get_coursemodule_from_id('forumx', $forumid);

    $cm = get_coursemodule_from_instance('forumx', $forumid, $courseid);

    $context = context_module::instance($cm->id);
    $exam_users = forumx_get_users_in_groupname($courseid, 'exam');

    // We need the unfiltered subscribed users.
    // Therefore we cannot use \mod_forumx\subscriptions::fetch_subscribed_users()
    $visible_for_users = array();

    // Check all subscribers to the forum.


    //if ($rs_subscriber = $DB->get_records('forumx_subscriptions', array('forumx'=>$forumid), '', 'id, userid')) {

    //for each from check all subscriber
    $query_subscriber = "SELECT s.id, userid, firstname, lastname FROM {forumx_subscriptions} AS s , {user} AS u
						WHERE forumx = ? AND u.id=userid ORDER BY userid";

    if ($rs_subscriber = $DB->get_records_sql($query_subscriber, array($forumid))) {

        foreach ($rs_subscriber as $subscriber) {
            $remove_user = false;
            $userid = $subscriber->userid;
            $exam_user = "  ";
            $user_key = "$userid";
            $firstname = $subscriber->firstname;
            $lastname = $subscriber->lastname;

            $uservisible = \core_availability\info_module::is_user_visible($cm, $userid, false);

            if ($uservisible) {
                // Is exam.
                if (isset($exam_users[$userid])) {
                    $remove_user = true;
                    if ($dbug) {
                        $exam_user = " exam_user ";
                    }
                }
            } else {
                $remove_user = true;
            }
            if ($remove_user === true) {
                if ($dbug) {
                    echo '<BR>userid' . $userid . ' ' . $firstname . '  ' . $lastname . '  does not  have capability  ' . $exam_user . '    -- remove from  mail!!!';
                }
                if ($deletePermission) {
                    \mod_forumx\subscriptions::unsubscribe_user($userid, $forum, $context, true);
                }
            } else {
                if ($dbug) {
                    echo '<BR>userid' . $userid . ' ' . $firstname . '  ' . $lastname . '  has capability  ';
                }
            }
        }
    }
    // Check all subscribers to the discussions.
    if ($dbug) {
        echo ' <BR><B> handle forumx_discussion_sub </B>';
    }
    $query_subscriber = 'SELECT sub.id as sub_id, d.id, sub.userid, sub.discussionid, d.forumx, u.firstname, u.lastname
							FROM {forumx_discussion_sub} AS sub, {forumx_discussions} AS d, {user} AS u
								WHERE d.id = sub.discussionid and d.forumx = ? and u.id=sub.userid ORDER BY sub.userid';

    //if ($rs_subscriber = $DB->get_records('forumx_discussion_sub', array('forumx'=>$forumid), '', 'id, userid, discussionid')) {
    if ($rs_subscriber = $DB->get_records_sql($query_subscriber, array($forumid))) {

        foreach ($rs_subscriber as $subscriber) {
            $userid = $subscriber->userid;
            $discussionid = $subscriber->discussionid;
            $remove_user = false;
            $firstname = $subscriber->firstname;
            $lastname = $subscriber->lastname;

            $found_exam = false;
            $user_key = "$userid";
            //if (!isset($visible_for_users[$user_key])) // Save queries for known results.
            //	$visible_for_users[$user_key] = \core_availability\info_module::is_user_visible($cm->id, $userid) &&
            //								has_capability('mod/forumx:viewdiscussion', $context, $userid);

            $uservisible = \core_availability\info_module::is_user_visible($cm, $userid, false);

            $exam_user = "";
            if ($uservisible) {
                if (isset($exam_users[$userid])) {
                    $remove_user = true;
                    if ($dbug) {
                        $exam_user = " exam_user ";
                    }
                }
            } else {
                $remove_user = true;
            }
            if ($remove_user == true) {
                if ($dbug) {
                    echo '<BR>userid' . $userid . ' ' . $firstname . '  ' . $lastname . '  does not  have capability  ' . $exam_user . '    -- remove from  mail!!!';
                }
                if ($deletePermission) {
                    $discussion_obj = new stdClass();

                    $discussion_obj->id = $discussionid;
                    $discussion_obj->forumx = $forumid;
                    $discussion_obj->userid = $userid;
                    \mod_forumx\subscriptions::unsubscribe_user_from_discussion($userid, $discussion_obj, $context);
                }
            } else {
                if ($dbug) {
                    echo '<BR>userid' . $userid . ' ' . $firstname . '  ' . $lastname . '  has capability  ';
                }
            }
        }
    }
}

/**
 * Force subscribe users to a forum.
 * @param int $fourmid
 * @param int $courseid
 * @param int $forcesubscribe
 */
function forumx_forcesubscribe_users($fourmid, $courseid, $forcesubscribe)
{
    if (!$cm = get_coursemodule_from_instance('forumx', $fourmid, $courseid)) {
        return;
    }
    $context = context_module::instance($cm->id);
    global $DB;

    $forum = $DB->get_record('forumx', array('id' => $fourmid));
    if ($forcesubscribe == forumx_INITIALSUBSCRIBE) {
        /// all users should be subscribed initially
        /// Note: forumx_get_potential_subscribers should take the forumx context,
        /// but that does not exist yet, becuase the forumx is only half build at this
        /// stage. However, because the forumx is brand new, we know that there are
        /// no role assignments or overrides in the forumx context, so using the
        /// course context gives the same list of users.


        $course_cm = context_course::instance($courseid);
        //$users = forumx_get_users_allow_subscribe($course_cm->id);
        $users = \mod_forumx\subscriptions::get_potential_subscribers($course_cm, 0, 'u.id, u.email');

        $exam_users = array();
        //we will not subscribe the user if he/she is a member the exam group
        $exam_users = forumx_get_users_in_groupname($courseid, 'exam');
        $index = 0;
        if ($users) {
            foreach ($users as $user) {
                if (isset($exam_users[$user->id])) {
                    continue;
                }

                $uservisible = \core_availability\info_module::is_user_visible($cm, $user->id, false);
                if ($uservisible) {
                    \mod_forumx\subscriptions::subscribe_user($user->id, $forum);
                }
            }
        }
    }
}

/**
 * Check the subscription state of the user in a discussion.
 * The return value depends on the definition of the forum as well.
 * If the forum does not allow subscriptions, the discussion subscription is not available.
 * If the user is subscribed to the forum, then discussion subscription is disabled.
 * If the forum allows subscription, check current subscription state for the discussion.
 * @param stdClass $forum The forum object. Can be null if $ignore_forum is true.
 * @param int $suerid
 * @param int $discussionid Discussion id, or 0 for a new discussion.
 * @param bool $ignore_forum If the forum level checking is known, use this to skip straight to the discussion level check.
 * @param stdClass $cm
 * @param context_module $context
 * @return int
 */
function forumx_get_discussion_subscription_status($forum, $userid, $discussionid = 0, $ignore_forum = false, $cm = null, $context = null)
{

    if (!$ignore_forum) {
        // Check if subscription is allowed for this forum.
        if ($forum->forcesubscribe == forumx_DISALLOWSUBSCRIBE) {
            return forumx_SUBSACRIBE_DISCUSSION_DIALLOWED;
        }
        // Check if the user is already subscribed to the forum.
        if (\mod_forumx\subscriptions::is_subscribed($userid, $forum, null, $cm)) {
            return forumx_SUBSACRIBE_DISCUSSION_SUBSCRIBED_TO_FORUM;
        }
    }
    // The user can subscribe to the discussion. Return current subscription state.
    if (empty($discussionid)) {// A new discussion.
        return forumx_SUBSACRIBE_DISCUSSION_ALLOWED_NOT_SUBSCRIBED;
    }
    // Check if the user is already subscribed to the discussion.
    if (\mod_forumx\subscriptions::is_subscribed($userid, $forum, $discussionid, $cm)) {
        return forumx_SUBSACRIBE_DISCUSSION_ALLOWED_SUBSCRIBED;
    }
    return forumx_SUBSACRIBE_DISCUSSION_ALLOWED_NOT_SUBSCRIBED;
}

/**
 * Return a post icon inside a dedicated span element
 * @param string $id id of the image element
 * @param string $img image name of the image element
 * @param string $class class name the image element
 * @param string $title title and alt of the image element
 * @return string the span with the image element
 */
function forumx_print_post_icon($id = '', $img = '', $class = '', $title = '')
{
    global $OUTPUT;
    if ($class) {
        $class .= ' ';
    }
    if ($id) {
        $id = ' id="' . $id . '"';
    }
    return '<span' . $id . ' class="' . $class . 'post_icon"><img src="' . $OUTPUT->image_url($img, 'forumx') . '" alt="' . $title . '" title="' . $title . '"></span>';
}

/**
 * Check if the value is a valid url.
 * @param string $url
 */
function forumx_is_url($url = null)
{
    static $match = '/^https?:\/\/[a-z0-9\-]+\.([a-z0-9\-]+\.)?[a-z]+/i';
    // How short can a valid url be? Let's go for 10. Less than that might be a suspicious url.
    return (strlen($url) >= 10 && preg_match($match, $url) === 1);
}

/**
 * Get the referer url of the current page.
 * There are only four options to return to: dashboard, course, forum or discussion.
 * This function is used instead of get_local_referer() that relies on 'HTTP_REFERER' which is unreliable.
 * @param string $type Return value (course, forum, discussion)
 * @param string $courseid
 * @param string $forumid
 * @param string $discussionid
 * @return string
 */
function forumx_get_referer($type = 'forum', $courseid = null, $forumid = null, $discussionid = null)
{
    global $CFG;

    if ($type === 'dashboard') {
        return $CFG->wwwroot . '/my';
    } else if ($type === 'course') {
        return $CFG->wwwroot . '/course/view.php?id=' . $courseid;
    } else if ($type === 'discussion' && !empty($discussionid)) {
        return $CFG->wwwroot . '/mod/forumx/discuss.php?d=' . $discussionid;
    } else {
        return $CFG->wwwroot . '/mod/forumx/view.php?f=' . $forumid;
    }
}

/**
 * Redirect to another page, with or without a notice page beteen, depends on the plugin settings.
 * @param moodle_url|string $url A moodle_url to redirect to
 * @param string $message The message to display to the user
 * @param int $delay The delay before redirecting
 */
function forumx_redirect($url, $message = '', $delay = -1)
{
    $redirect = (bool)get_config('forumx', 'forumx_enableredirectpage');
    if ($redirect) {
        redirect($url, $message, $delay);
    } else {
        redirect($url);
    }
}

/**
 * Set the return parameter in the post commands.
 * @param array|null $params Current parameters on the url
 * @param bool $is_discussion Is the return url is into the discussion
 * @return array
 */
function forumx_set_return_in_url($params = null, $is_discussion = false)
{
    if (!$params) {
        $params = array();
    }
    if ($is_discussion) {
        $params['returnto'] = 'discussion';
    }
    return $params;
}

/**
 * Extract value from the course shortname.
 * In order to return a value, make sure $CFG->forumx_splitshortname and $CFG->forumx_shortnamedelimiter are set.
 * @param string $shortname The course shortname value
 * @return string Extracted value or empty if delimiter not found or this option is disabled in the settings.
 */
function forumx_extract_course_shortname($shortname = null)
{
    global $CFG;
    if (empty($shortname) || $CFG->forumx_splitshortname == forumx_EXTRACT_SHORTNAME_NONE || empty($CFG->forumx_shortnamedelimiter)) {
        return '';
    }
    $extracted = '';
    if ($CFG->forumx_splitshortname == forumx_EXTRACT_SHORTNAME_PRE) {
        $extracted = trim(substr($shortname, 0, strpos($shortname, $CFG->forumx_shortnamedelimiter)));
    } else if ($CFG->forumx_splitshortname == forumx_EXTRACT_SHORTNAME_POST) {
        $extracted = trim(substr($shortname, strrpos($shortname, $CFG->forumx_shortnamedelimiter) + 1));
        if ($extracted == $shirtname) {
            $extracted = ''; // Delimiter not found, return empty string.
        }
    }
    return $extracted;
}

/**
 * Return the content of the post in a formatted display.
 * @param stdClass $post The post to display
 * @param int $courseid Id of the course
 * @param context_module $modcontext
 * @param stdClass $cm
 * @param string|array $attributes Attributes for the div container
 * @param bool $preview Display a short preview of the content
 * @return void|string the formatted output
 */
function forumx_display_post_content($post = null, $courseid = null, $modcontext = null, $cm, $attributes = null, $preview = false)
{

    if (!$post || !$courseid) {
        return;
    }
    $options = new stdClass;
    $options->para = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;

    list($attachments, $attachedimages) = forumx_print_attachments($post, $cm, 'separateimages');
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php',
        $modcontext->id, 'mod_forumx', 'post', $post->id);
    $postcontent = format_text($post->message, $post->messageformat, $options, $courseid);
    if (empty($postcontent)) {
        $postcontent = '<span class="for-sr">' . get_string('postempty', 'forumx') . '<span>';
    } else if ($preview) {
        $postcontent = shorten_text($postcontent) . '<span class="for-sr">' . get_string('partialtext', 'forumx') . '<span>';
    }
    $attr = '';
    if (!empty($attributes)) {
        if (!is_array($attributes)) {
            $attr = ' ' . $attributes;
        } else {
            foreach ($attributes as $key => $value) {
                $attr .= ' ' . $key . '="' . $value . '"';
            }
        }
    }
    $postcontent .= html_writer::tag('div', $attachedimages, array('class' => 'attachedimages'));
    return '<div' . $attr . '>' . $postcontent . '</div>';
}

/**
 * Builds an array of the discussion's posts in hierarchical order
 *
 * @param stdClass $cm
 * @param stdClass $modcontext
 * @param stdClass $forumx
 * @param stdClass $discussion
 * @param int $parent
 * @param int $depth
 * @param array $posts
 * @param array $threaded
 * @param int $ignore_childrens ignore children of selected post and mark it as bold without a link
 * @return boolean
 */
function forumx_get_posts_threaded(&$cm, $modcontext, $forumx, $discussion, $parent, $depth, $posts, &$threaded, $ignore_childrens = 0)
{
    global $USER, $DB;
    $hideauthor = $forumx->hideauthor;

    $continue = false;
    if (!empty($posts[$parent]->children) && $parent != $ignore_childrens) {
        $posts = $posts[$parent]->children;

        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {
            $postobj = new stdClass;
            $postobj->id = $post->id;
            $postobj->html = '<div class="indent">';

            if (!forumx_user_can_see_post($forumx, $discussion, $post, NULL, $cm)) {
                $postobj->html .= '</div>\n';
                continue;
            }
            $by = new stdClass();
            if (!$hideauthor) {
                $by->name = fullname($post, $canviewfullnames);
            } else {
                $by->name = forumx_get_post_role($cm->course, $forumx->id, $post->id, $cm);
            }
            $by->date = userdate($post->modified);

            // The content of this post is already displayed elswhere, so we don't need to add a link here.
            if ($postobj->id == $ignore_childrens) {
                $postobj->text = '<b aria-labelledby="post_placeholder" tabindex="0">' . format_string($post->subject, true) . '</b>&nbsp;';
            } else {
                $postobj->text = '<a class="post_link" id="post' . $postobj->id . '" href="#">' . format_string($post->subject, true) . '</a>&nbsp;';
                $postobj->message = $post->message;
            }
            $postobj->nameanddate = get_string("bynameondate", "forumx", $by);
            $postobj->messagetrust = $post->messagetrust;
            $postobj->messageformat = $post->messageformat;

            $threaded[$post->id] = $postobj;

            if (forumx_get_posts_threaded($cm, $modcontext, $forumx, $discussion, $post->id, $depth - 1, $posts, $threaded, $ignore_childrens)) {
                $continue = true;
            }
            $threaded[$post->id . -1] = "</div>";
        }  //end for each post
    }  //!empty posts children

    return $continue;
}

/**
 * Return all visible forums in the selected course
 * @param int $courseid
 * @return multitype:
 */
function forumx_get_forums_in_course($courseid)
{
    global $DB;

    $instances = get_fast_modinfo($courseid)->get_instances_of('forumx');
    $ids = array();
    foreach ($instances as $id => $cm) {
        if ($cm->uservisible) {
            $ids[] = $id;
        }
    }
    if (empty($ids)) {
        return array();
    }
    list($ids_sql, $ids_params) = $DB->get_in_or_equal($ids);
    /*
	$sql = "SELECT f.id, f.name
			FROM {modules} m, {course_modules} cm, {forumx} f
			WHERE cm.course = ?
			AND cm.module = m.id AND cm.visible = 1
			AND m.name = 'forumx'
			AND cm.instance = f.id
			AND f.type in ('general', 'qanda', 'news', 'eachuser')
			ORDER BY f.name asc";
*/
    $sql = 'SELECT id, name
FROM {forumx} WHERE id ' . $ids_sql . '
AND type in ("general", "qanda", "news", "eachuser")
ORDER BY name ASC';

    return $DB->get_records_sql_menu($sql, $ids_params);
}

/**
 * Get an option list of all available forums in a course.
 * Based on code from /mod/forum/discuss.php
 * @param int $courseid
 * @param int $ignore_forum Forum to ignore in the results.
 */
function forumx_get_target_forums_options($courseid, $ignore_forum = 0)
{
    global $DB;

    $forummenu = array();
    $modinfo = get_fast_modinfo($courseid);

    if (isset($modinfo->instances['forumx'])) {

        $forums = $DB->get_records_menu('forumx', array('course' => $courseid), '', 'id, type');
        foreach ($modinfo->instances['forumx'] as $cm) {
            if ($cm->instance == $ignore_forum || $forums[$cm->instance] === 'single') {
                continue;
            }
            if (!$cm->uservisible || !has_capability('mod/forumx:startdiscussion', context_module::instance($cm->id))) {
                continue;
            }
            $section = $cm->sectionnum;
            $sectionname = get_section_name($courseid, $section);
            if (empty($forummenu[$sectionname])) {
                $forummenu[$sectionname] = array();
            }
            $forummenu[$sectionname][$cm->instance] = format_string($cm->name);
        }
    }
    return $forummenu;
}

/**
 * Return the top panel with the search forn and index button (optional)
 * @param stdClass $course
 * @param string $search
 * @param bool $use_index
 */
function forumx_print_top_panel($course, $search, $use_index = true)
{
    global $CFG, $OUTPUT;
    $dir = right_to_left() ? '_rtl' : '_ltr';
    $index = '';;
    if ($use_index) {
        $index = '<a id="indexbutton" class="link_button float_end" href="' . $CFG->wwwroot . '/mod/forumx/index.php?id=' .
            $course->id . '">
				<img src="' . $OUTPUT->image_url('i/forums', 'forumx') . '" aria-hidden="true"> ' . get_string('forumslist', 'forumx') .
            ' <img src="' . $OUTPUT->image_url('i/buttonarrow' . $dir, 'forumx') . '" aria-hidden="true"></a>';
    }
    $output = '<div id="forumx_top_panel">' .
        $index . forumx_search_form($course, $search, true, get_string('searchplaceholder', 'forumx')) . '</div>';
    return $output;
}

/**
 * Print forum top buttons.
 * @param int $userid
 * @param stdClass $forum
 */
function forumx_print_top_buttons($userid, $forum)
{
    $subscribe = forumx_print_subscribe_options($forum, $userid, null, 'clean_button enhanced subscribebutton pulse');
    $track = forumx_print_tracking_options($forum, null, 'clean_button enhanced trackbutton pulse');
    $output = '<div id="forumx_top_buttons" class="align_end">
	' . $track . $subscribe . '</div>';
    if ($subscribe) {
        $output .= '<div id="d_sub_container" style="display:none;">' . forumx_print_discussion_subscription_options(null, null, false) . '</div>';
    }
    return $output;
}

function forumx_print_top_buttons_index_menu($can_subscribe = true, $can_track = true)
{
    $sub = '';
    $track = '';
    if ($can_subscribe) {
        $menu = new \mod_forumx\simpleaction_menu(null, null, 'subscribeall');
        $menu->set_menu_attributes(array(
            'id' => 'subscribealltrigger',
            'title' => get_string('subscribeforums', 'forumx'),
            'aria-label' => get_string('subscribeforums', 'forumx')
        ));
        $menu->select_menu_icon('right');
        $menu->add_class('float_end subscribeallmenu');
        $menu->flip_side();
        $menu->close_on_click(true);
        $menu->add_item('<a href="#" role="menuitem" data-action="subscribe"><span class="float_start"></span>' . get_string('subscribeforumindex:yeslabel', 'forumx') . '</a>');
        $menu->add_item('<a href="#" role="menuitem" data-action="unsubscribe"><span class="float_start"></span>' . get_string('subscribeforumindex:nolabel', 'forumx') . '</a>');
        $sub = $menu->render();
    }
    return '<div id="forums_buttons_index" class="float_end nowrap forumlist_buttons">' . $track . $sub . '</div>';
}

/**
 * Print a single forum tracking options.
 * @param stdClass $forum
 * @param int $id
 * @param string $classes Optional extra classes
 * @param bool $compact_mode Set button compact mode
 * @param bool $skip_check Skip checking if user can track the forum
 */
function forumx_print_tracking_options($forum, $id = null, $classes = null, $compact_mode = false, $skip_check = false)
{
    $button = '';
    $text = get_string('tracking:yeslabel', 'forumx');
    $params = array(
        'id' => 'trackforum' . $id,
        'data-forumid' => $forum->id,
        'class' => 'stage_button ' . $classes);
    if ($compact_mode) {
        $params['class'] .= ' compact_button';
    }
    if (!$skip_check && !forumx_tp_can_track_forums($forum)) {
        return;
    } else {
        if ($forum->trackingtype == forumx_TRACKING_OFF) {
            return;
        } else {
            if ($forum->trackingtype == forumx_TRACKING_FORCED) {
            } else {
                if (forumx_tp_is_tracked($forum)) {
                    //$text = get_string('tracking:nolabel', 'forumx');
                    $params['aria-label'] = $params['title'] = $text . ', ' . get_string('enabled', 'forumx');
                    $params['data-actiontype'] = 'untrack';
                    $params['data-buttonstate'] = 'on';
                } else {
                    //$text = get_string('tracking:yeslabel', 'forumx');
                    $params['aria-label'] = $params['title'] = $text . ', ' . get_string('disabled', 'forumx');
                    $params['data-actiontype'] = 'track';
                    $params['data-buttonstate'] = 'off';
                }
                $button_text = $compact_mode ? '' : '<span class="button_text">' . $text . '</span>';
                $button = \html_writer::tag('button', '<span class="float_start icon"></span>' . $button_text, $params);
                $button = '<div id="trackforum_options" class="stage_button_container">' .
                    $button . '<span class="stage_label hidden_element">' . get_string('tracking:yeslabel', 'forumx') . '</span></div>';

            }
        }
    }
    return $button;
}

/**
 * Print a single forum subscribe options.
 * @param stdClass $forum
 * @param int $userid
 * @param int $id
 * @param string $classes Optional extra classes
 * @param bool $compact_mode Set button compact mode
 * @param int $digest The user's digest mode in the forum. Use -1 to ignore check
 */
function forumx_print_subscribe_options($forum, $userid, $id = null, $classes = null, $compact_mode = false, $only_icon = false, $digest = null)
{
    $params = array(
        'id' => 'subscribeforum' . $id,
        'data-forumid' => $forum->id,
        'class' => 'stage_button ' . $classes);
    if ($compact_mode) {
        $params['class'] .= ' compact_button';
    }
    $text = $text_compact = $text_button = get_string('subscribeforum:yeslabel', 'forumx');
    $button = $digest_text = '';
    if (!\mod_forumx\subscriptions::is_subscribable($forum)) {
        if ($forum->forcesubscribe == forumx_DISALLOWSUBSCRIBE) {
            return;
        }
    } else {
        if ($digest !== -1) {
            if ($digest === null) {
                $digest = \mod_forumx\subscriptions::get_digest_mode($userid, $forum->id);
            }
            if ($digest > 0) {
                $str = $digest == 1 ? 'emaildigestcompleteshort' : 'emaildigestsubjectsshort';
                $digest_text = '(' . get_string('emaildigeststatusshort', 'forumx') . ': ' . get_string($str, 'forumx') . ')';
                $text .= ' ' . $digest_text;
                $text_compact .= '<br>' . $digest_text;
            }
        }
        if (\mod_forumx\subscriptions::is_subscribed($userid, $forum)) {
            $params['aria-label'] = $params['title'] = $text . ', ' . get_string('enabled', 'forumx');
            $params['data-actiontype'] = 'unsubscribe';
            $params['data-buttonstate'] = 'on';
        } else {
            $params['aria-label'] = $params['title'] = $text . ', ' . get_string('disabled', 'forumx');
            $params['data-actiontype'] = 'subscribe';
            $params['data-buttonstate'] = 'off';
        }
        $button_text = ($compact_mode || $only_icon) ? '' : '<span class="button_text">' . $text_button . '</span>';
        $button = \html_writer::tag('button', '<span class="float_start icon"></span>' . $button_text, $params);
        $button = '<div id="subscribeforum' . $id . '_options" class="stage_button_container">' .
            $button . '<span class="stage_label hidden_element">' . $text_compact . '</span></div>';
    }
    return $button;
}


/**
 * Return reply button, according to the locked state of the discussion.
 * @param bool $locked Is discussion locked
 */
function forumx_print_post_reply_button($locked = false)
{
    static $buttons = array();
    if (empty($buttons)) {
        $buttons['locked'] = '<span class="for-sr post_reply_button">' . get_string('discussionislocked', 'forumx') . '</span>';
        $buttons['unlocked'] = '<button class="of_reply clean_button post_reply_button">
<span class="of_reply_icon float_start"></span><span class="of_reply_text">' . get_string('reply', 'forumx') . '</span></button>';
    }
    if ($locked) {
        return $buttons['locked'];
    } else {
        return $buttons['unlocked'];
    }
}

/**
 * Convert content to 'atto friendly'.
 * @param string $text
 */
function forumx_format_quick_message($text)
{
    if (empty(trim($text))) {
        return '';
    } else {
        $text = forumx_filter_post($text);
        // Change into something less likely to be made by the user.
        $text = str_replace(array("\r\n", "\n\r", "\n", "\r"), '{_br}_', $text);
        $array = explode('{_br}_', $text);
        foreach ($array as $string) {
            $string = htmlspecialchars($string);
        }
        return '<p>' . implode('</p><p>', $array) . '</p>';
    }
}

/**
 * Filter posting data from any unwanted values.
 * @param string $text Source text
 * @param bool $replace Replace any filtered value with a default char
 * @return string
 */
function forumx_filter_post($text, $replace = false)
{
    if (empty($text)) {
        return '';
    }
    static $has_filter;
    static $filters_list = array();
    if ($has_filter === false) { // There are no filters to use.
        return $text;
    }
    global $CFG;
    $filters = $CFG->forumx_filterpost;
    if (empty($filters)) {
        $has_filters = false;
        return $text;
    } else {
        // Prepare filters.
        $has_filters = true;
        $filters = explode("\n", $filters);
        foreach ($filters as $fl) {
            $filters_list[] = '/' . $fl . '/i';
        }
    }
    $replace_string = $replace ? '-' : '';
    $text = preg_replace($filters_list, $replace_string, $text);
    return $text;
}

/**
 * Return the markup for the discussion subscription toggling icon.
 *
 * @param stdClass $forum The forum object.
 * @param int $discussionid The discussion to create an icon for.
 * @param bool $check_subscription Set to false to generate the button for future use
 * @return string The generated markup.
 */
function forumx_print_discussion_subscription_options($forum = null, $discussionid = 0, $check_subscription = true)
{
    global $USER;

    $button = '';
    $forumid = null;
    if ($check_subscription) {
        $subscriptionstatus = \mod_forumx\subscriptions::is_subscribed($USER->id, $forum, $discussionid);
        $forumid = $forum->id;
    } else {
        $subscriptionstatus = false;
        $forumid = '{{f}}';
        $discussionid = '{{d}}';
    }
    static $params = array();
    $text = get_string('subscribediscussion:yeslabel', 'forumx');
    if (empty($params)) {
        $classes = 'subscribediscussion clean_button enhanced stage_button subscribebutton pulse';
        $params = array(
            'on' => array(
                'aria-label' => $text . ', ' . get_string('enabled', 'forumx'),
                'title' => $text . ', ' . get_string('enabled', 'forumx'),
                'data-actiontype' => 'unsubscribe',
                'data-buttonstate' => 'on',
                'class' => $classes
            ),
            'off' => array(
                'aria-label' => $text . ', ' . get_string('disabled', 'forumx'),
                'title' => $text . ', ' . get_string('disabled', 'forumx'),
                'data-actiontype' => 'subscribe',
                'data-buttonstate' => 'off',
                'class' => $classes
            )
        );
    }
    $params['on']['data-forumid'] = $params['off']['data-forumid'] = $forumid;
    $params['on']['data-discussionid'] = $params['off']['data-discussionid'] = $discussionid;
    $status = $subscriptionstatus ? 'on' : 'off';
    $button = \html_writer::tag('button',
        '<span class="float_start icon"></span><span class="button_text">' . $text . '</span>', $params[$status]);
    return '<div id="subscribediscussion_options_' . $discussionid . '" class="subscribediscussion stage_button_container" data-discussionid="' .
        $discussionid . '">' . $button . '<span class="stage_label hidden_element">' . $text . '</span></div>';
}

/**
 * Print a list of the forums for the index page.
 * @param array $forums
 * @param string $title
 */
function forumx_print_forums_list($forums, $title = null)
{
    global $USER;
    if (empty($forums)) {
        return '';
    }
    $strdiscussions = get_string('discussions', 'forumx');
    $strsubscribed = get_string('subscribed', 'forumx');
    $strunreadposts = get_string('unreadposts', 'forumx');
    static $userdate = '';
    if (empty($userdate)) {
        $userdate = get_string('strftimedatetimeshort', 'langconfig');

        $dateregex = "/\s+([%:hHmMsS]+)/";
        if (preg_match($dateregex, $userdate)) {
            $userdate = preg_replace_callback(
                $dateregex,
                function ($matches) {
                    return ' <span class="clean_userdate">' . $matches[1] . '</span>';
                }, $userdate);
        }
    }
    $output = $output_title = '';
    if (!empty($title)) {
        $output_title = '<div class="forum_list_header">
			<button class="forum_header_button discussion_button d_closed" title="' .
            get_string('foruminfoshowall', 'forumx') . '" aria-live="polite"></button>
			<span class="forum_header_title">' . $title . '</span></div>';
    }
    foreach ($forums as $id => $forum) {
        $forum_button = html_writer::tag('button', '', array(
            'id' => 'fbtn' . $id,
            'class' => 'forum_button discussion_button',
            'title' => get_string('foruminfoshow', 'forumx'),
            'aria-live' => 'polite',
            'data-forumid' => $id,
            'aria-controls' => 'forumbody' . $id,
            'aria-expanded' => 'false'
        ));
        $class = $forum['visible'] == 1 ? '' : ' class="dimmed"';
        $lock = $forum['locked'] ? ' ' . forumx_print_lock() : '';

        $buttons = '';
        if (/*$forum['cantrack'] || */ $forum['cansubscribe']) {
            $buttons = '<div class="float_end nowrap forumlist_buttons">' .
                /*forumx_print_tracking_options($forum['forum'], $id, 'clean_icon_button trackforumbutton', true).*/
                forumx_print_subscribe_options($forum['forum'], $USER->id, $id, 'clean_icon_button subscribeforumbutton', false, true) . '</div>';
        }
        if ($forum['istracked']) {
            $unread_marker = '';
            if ($forum['unread'] == 0) {
                $unreadlink = $forum['unread'];
            } else {
                $unread_marker = '<span id="unreadmarker' . $id . '" class="unread_marker"></span>';
                $unreadlink = '<a href="view.php?f=' . $id . '">' . $forum['unread'] . '</a>';
            }
            $track = $unread_marker . $strunreadposts . ': <span class="forumlist_number">' . $unreadlink . '</span>';
        } else {
            $track = '';
        }
        if ($forum['unread'] > 0) {
            $track .= '<button id="markread' . $id . '" class="clean_button" title="' .
                get_string('markallread', 'forumx') . '" data-forumid="' . $id . '">' . get_string('markallreadtext', 'forumx') . '</button>';
        }

        $lastupdate = userdate($forum['lastupdate'], $userdate, null, false);
        $output .= '<li class="forumslist" data-forumid="' . $id . '"><div class="forum_container">
			<div class="forum_top">
				' . $forum_button . '
				<span class="forum_list_title">' . '<a href="view.php?f=' . $id . '"' . $class . '>' . $forum['name'] . '</a>' . $lock . '</span>' . $buttons . '
			</div>
			<div id="forumbody' . $id . '" class="forum_body hidden_element" aria-hidden="true"><div class="forum_body_content">' . $forum['intro'] . '</div></div>
			<div class="forum_footer">
				<div class"forum_discussions">' . $strdiscussions . ': <span class="forumlist_number">' . $forum['discussions'] . '</span></div>
				<div class="forum_lastupdate">' . get_string('lastupdate', 'forumx') . ': <span class="forum_clean_date">' . $lastupdate . '</span></div>
				<div id="forumunread' . $id . '" class="forum_unread">' . $track . '</div>
			</div>
		</div></li>';
    }
    return $output_title . '<ul class="forumslist">' . $output . '</ul>';
}

/**
 * Get last update date form a forum.
 * @param stdClass $cm
 * @param stdClass $course
 */
function forumx_get_last_forum_update($cm, $course)
{
    global $CFG, $USER, $DB;

    $forumid = $cm->instance;
    $sql = 'forumx=?';
    $params = array($forumid);
    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS && has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        $modinfo = get_fast_modinfo($course);
        $mygroups = $modinfo->get_groups($cm->groupingid);

        // Add all groups posts.
        $mygroups[-1] = -1;
        list($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);
        $params = array_merge($params, $groups_params);
        $sql .= 'AND groupid ' . $groups_sql;
    }
    $sql = 'SELECT timemodified FROM {forumx_discussions}
	WHERE ' . $sql . ' ORDER BY timemodified DESC LIMIT 0,1';

    if ($discussion = $DB->get_record_sql($sql, $params)) {
        return $discussion->timemodified;
    }
    return 0;
}

/**
 * Print element for the AMD module that will pick it up after page load.
 */
function forumx_print_elements_for_js()
{
    $icons = forumx_new_post_icons();
    $output = '';
    foreach ($icons as $id => $value) {
        $output .= '<div class="for_js" id="' . $id . '">' . $value . '</div>';
    }
    return '<div id="for_js" class="hidden_element">' . $output . '</div>';
}

/**
 * Add styles for desktop devices.
 * This is used to avoid unwanted hover events on mobile devices.
 * Make sure to call this function before calling $OUTPUT->header().
 */
function forumx_add_desktop_styles()
{
    global $PAGE;
    $devicetype = \core_useragent::get_device_type();
    $is_mobile = $devicetype === "mobile" || $devicetype === "tablet";
    if (!$is_mobile) {
        $PAGE->requires->css('/mod/forumx/desktop.css');
    }
}

function forumx_print_overview($courses, &$htmlarray)
{
    global $USER, $CFG, $DB, $SESSION, $OUTPUT;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }
    if (!$forumxs = get_all_instances_in_courses('forumx', $courses)) {
        return;
    }

    // Courses to search for new posts
    $params = array();
    /*
	 $coursessqls = array();
	 $params 	 = array();
	 foreach ($courses as $course) {
		// If the user has never entered into the course all posts are pending
		$coursessqls[] = '(f.course = ?)';
		$params[] = $course->id;

		}
		$params[] = $USER->id;
		$coursessql = implode(' OR ', $coursessqls);


		$sql = "SELECT f.id, COUNT(*) as count "
		.'FROM {forumx} f '
		.'JOIN {forumx_discussions} d ON d.forumx  = f.id '
		.'JOIN {forumx_posts} p ON p.discussion = d.id '
		."WHERE  ($coursessql) "
		.'AND p.userid != ? '
		.'GROUP BY f.id';

		if (!$new = $DB->get_records_sql($sql, $params)) {
		$new = array(); // avoid warnings
		}
		*/
    $new = array(); // avoid warnings
    // also get all forum tracking stuff ONCE.
    $trackingforums = array();
    $viewallgroups = array();
    $ind = 0;
    foreach ($forumxs as $forumx) {
        if (($forumx->type !== 'news') && (forumx_tp_is_tracked($forumx))) {
            if (forumx_tp_can_track_forums($forumx)) {
                $trackingforums[$forumx->id] = $forumx;
                // If the forum has separete groups, check if the user has capability to view all groups.
                $modcontext = context_course::instance($forumx->course);
                $groupmode = groups_get_activity_groupmode($forumx);
                $viewallgroups[$forumx->id] = $groupmode == NOGROUPS || $groupmode == VISIBLEGROUPS ||
                    has_capability('moodle/site:accessallgroups', $modcontext);
            }
        } else {
            unset($forumxs[$ind]);
        }
        $ind++;
    }
    if (count($trackingforums) > 0) {
        $cutoffdate = isset($CFG->forumx_oldpostdays) ? (time() - ($CFG->forumx_oldpostdays * 24 * 60 * 60)) : 0;
        $sql = 'SELECT d.forumx,d.course,COUNT(p.id) AS count ' .
            ' FROM {forumx_posts} p ' .
            ' JOIN {forumx_discussions} d ON p.discussion = d.id ' .
            ' LEFT JOIN {forumx_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingforums as $track) {
            $params[] = $track->id;
            // Can the user view all groups in the forum.
            if ($viewallgroups[$track->id]) {
                $sql .= '(d.forumx = ?) OR ';
            } else {
                $sql .= '(d.forumx = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
                if (isset($SESSION->currentgroup[$track->course])) {
                    $groupid = $SESSION->currentgroup[$track->course];
                } else {
                    // Get first groupid.
                    $groupids = groups_get_all_groups($track->course, $USER->id);
                    if ($groupids) {
                        reset($groupids);
                        $groupid = key($groupids);
                        $SESSION->currentgroup[$track->course] = $groupid;
                    } else {
                        $groupid = 0;
                    }
                    unset($groupids);
                }
                $params[] = $groupid;
            }
        }
        $sql = substr($sql, 0, -3); // Take off the last OR.
        $sql .= ') AND p.modified >= ? AND r.id is NULL GROUP BY d.forumx,d.course';
        $params[] = $cutoffdate;

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($new)) {
        return;
    }

    $strforum = get_string('modulename', 'forumx');

    foreach ($forumxs as $forumx) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // Either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($forumx->id, $new) && !empty($new[$forumx->id])) {
            $count = $new[$forumx->id]->count;
        }
        if (array_key_exists($forumx->id, $unread)) {
            $thisunread = $unread[$forumx->id]->count;
            $showunread = true;
        }
        //if ($count > 0 || $thisunread > 0) {
        if (($thisunread > 0) && ($forumx->type != 'news')) {
            //$str .= '<div class="overview forumx"><div class="name">'.$strforum.': <a title="'.$strforum.'" href="'.$CFG->wwwroot.'/mod/forumx/view.php?f='.$forumx->id.'">'.
            //	$forumx->name.'( count = '.$count  .' unread = '. $thisunread.   ' )</a></div>';

            $icontext = $OUTPUT->pix_icon('icon', 'forumx', 'forumx', array('class' => 'iconlarge'));

            $str .= '<div class="overview forumx"><div class="name">' . $icontext . get_string('overview_unread_messages', 'forumx', $thisunread) .
                '<a title="' . $strforum . '" href="' . $CFG->wwwroot . '/mod/forumx/view.php?f=' . $forumx->id . '">' .
                $forumx->name . '</a></div>';


            //<#4847
            //            $str .= '<div class="info"><span class="postsincelogin">';
            //            $str .= get_string('overviewnumpostssince', 'forumx', $count)."</span>";
            //            if (!empty($showunread)) {
            //                $str .= '<div class="unreadposts">'.get_string('overviewnumunread', 'forumx', $thisunread).'</div>';
            //            }
            //            $str .= '</div></div>';
            $str .= '</div>';
            //#4847>
        }
        if (!empty($str)) {
            if (!array_key_exists($forumx->course, $htmlarray)) {
                $htmlarray[$forumx->course] = array();
            }
            if (!array_key_exists('forumx', $htmlarray[$forumx->course])) {
                $htmlarray[$forumx->course]['forumx'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$forumx->course]['forumx'] .= $str;
        }
    }
}

function forumx_print_news_items($course, $coursecontext = null, $showallnews = 0, $amount = -1)
{
    global $CFG, $OUTPUT, $USER, $PAGE;
    $forumx = 0;
    if (!$forumx = forumx_get_course_forum($course->id, 'news')) {
        return '';
    }
    $PAGE->requires->js_init_call('M.theme_ouil_elegance_news.init', array(
        $forumx->id,
        $PAGE->url->get_path() . "?id=" . $course->id
    ));
    // Required.
    $PAGE->requires->strings_for_js(array('required'), 'moodle');
    $container = '';

    $modinfo = get_fast_modinfo($course);

    if (empty($modinfo->instances['forumx'][$forumx->id])) {
        return '';
    }

    $cm = $modinfo->instances['forumx'][$forumx->id];
    $context = context_module::instance($cm->id);

    if (!has_capability('mod/forumx:viewdiscussion', $context)) {
        return '';
    }

    $now = time();
    $returnto = '';
    $footer = '';
    $new_period = $CFG->forumx_duration_new_message;
    $strftimerecent = '%d/%m/%Y';
    $stredit = get_string('editpost', 'forumx');
    $strdelete = get_string('delete', 'forumx');
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm, true);
    $header = "";
    $has_new_items = false;
    $output = '';

    if (forumx_user_can_post_discussion($forumx, $currentgroup, $groupmode, $cm, $context)) {
        //$returnto = '&returnto='.$CFG->wwwroot.'/course/view.php?id='.$course->id;
        $returnto = '&returnto=course';
        $footer = '<div class="newsfooter hidden-xs"><a href="' . $CFG->wwwroot . '/mod/forumx/post.php?forumx=' . $forumx->id . $returnto . '">' .
            get_string('addnewpost', 'forumx') . '</a>...</div>';
        //	$header = '<li class="newsheader visible-xs " id ="add_news"><div id ="add_news_item" class="newsbody"  ><span id="add_news_item_bullet"><span class="fa  fa-plus-square"></span></span><span class="newsbody add_news">'.get_string('addnewpost', 'forumx').'...</span></div></li>';

        //$header = '<li class="newsheader visible-xs " id ="add_news"><span id ="add_news_item" class="newsbody"  ><span id ="add_news_item_circle"  class="fa  fa-plus"></span> '.get_string('addnewpost', 'forumx').'</span></li>';
        $header = "";
        if (!$showallnews) {
            $header = '<li class="newsheader visible-xs " id ="add_news"><a id ="add_news_item" class="newsbody"  > <span id ="add_news_item_span" >  ' . get_string('addnewpost', 'forumx') . '</span></a></li>';
        }
    }

    // RSS button
    // First check if the forum has any rss to start with.
    // Also, the capability check 'moodle/course:view' was removed, because this function is called from whithin the course
    if ($forumx->rsstype == 1 && $forumx->rssarticles &&
        isset($CFG->enablerssfeeds) && isset($CFG->forumx_enablerssfeeds) &&
        $CFG->enablerssfeeds && $CFG->forumx_enablerssfeeds) {
        $footer .= '<span class="newsfooter hidden-xs ">' .
            rss_get_link($context->id, $USER->id, 'forumx', $forumx->id, get_string('rsssubscriberssdiscussions', 'forumx')) .
            '</span>';
    }
    $discussion_counter = 0;
    if (!$discussions = forumx_get_discussions($cm, 'p.modified DESC', true, $currentgroup, $amount)) {
        $output = get_string('nonews', 'forumx') . '<br>' . $header . "<br>" . $footer;
    } else {
        $scroll = "newsareascroll";
        if ($showallnews == true) {
            $scroll = "";
        }
        $output = '<div id="newsareadiscussions" class="container-fluid ' . $scroll . '" role="region" aria-label="' .
            get_string('landmark6', 'theme_ouil_elegance') . '">';
        if (!$coursecontext) {
            $coursecontext = context_course::instance($course->id);
        }
        $canupdate = has_capability('moodle/course:update', $coursecontext);
        $output .= '<ul>' . $header;
        foreach ($discussions as $discussion) {
            $discussion_counter++;
            $actions = "";
            $discussion->subject = $discussion->name;
            $discussion->subject = format_string($discussion->subject, true, $forumx->course);
            $can_edit = false;
            if ($canupdate) {
                $actions = '<div  class="newscontrols" >';
                if (($now - $discussion->created) < $CFG->maxeditingtime) {
                    $actions .= '<span class="editpost hidden-xs">
								<a href="' . $CFG->wwwroot . '/mod/forumx/post.php?edit=' . $discussion->id . $returnto . '">' . $stredit . '</a>
								</span>';
                    $can_edit = true;
                }
                $actions .= '<span class="news_post_action"><a href="' . $CFG->wwwroot . '/mod/forumx/post.php?delete=' . $discussion->id . $returnto . '"><i class="fa fa-close"></i>' . $strdelete . '</a>
							</span>';
                $actions .= '</div>';
            }
            $days_difference = floor(($now - $discussion->modified) / DAYSECS);
            $bulletin = 'bulletin';
            $bulletin = 'fa fa-square';
            $action = '';
            $is_new_message = false;
            if ($can_edit) {
                //$bulletin='';
                $bulletin = 'fa fa-pencil';
                $has_new_items = true;
                $action = 'edit';
                $is_new_message = true;
            } else if ($days_difference < $new_period) {
                $bulletin = 'new';
                $has_new_items = true;
                $action = 'new';
                $is_new_message = true;
            }
            $news_item_status = '';
            $news_item = '';
            $postdate = userdate($discussion->modified, $strftimerecent);
            if ($action == '') {
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="' . $bulletin . ' "></span>';
            } else if ($action == 'new') {
                //$news_item_status.= '<span  id ="discussion_'.$discussion->id.'"  class="'.$bulletin.' mobilehidden "></span>';
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="fa fa-square   "></span>';
            } else if ($action == 'edit') {
                $news_item_status .= '<span   class="' . $bulletin . ' desktophidden "></span>';
                $news_item_status .= '<span  id ="discussion_' . $discussion->id . '"  class="fa fa-square  mobilehidden "></span>';
                //$news_item_status.= '<span  id ="discussion_'.$discussion->id.'"  class="fa fa-square  mobilehidden "></span>';
            }
            $news_body = '';
            $new_message = '';
            if ($is_new_message) {
                $new_message = '<span   class=" postdate_new mobilehidden "> ' . get_string('forum_news_new', 'forumx') . '</span>';
            }

            $postdate_str = '<span class="postdate" >' . $postdate . ' - </span>';
            $news_body = '<span class="posttitle" >' . $new_message .
                format_text($postdate_str . $discussion->subject, $discussion->messageformat, NULL, $forumx->course) . '</span>';

            $discussion->message = '<span >' . file_rewrite_pluginfile_urls($discussion->message, 'pluginfile.php',
                    $context->id, 'mod_forumx', 'post', $discussion->firstpost) . '</span>';

            $li_class = '';
            $li_class = "news_item ";
            if ($showallnews == false) {
                $li_class .= "hidden-xs";
            }
            if ($discussion_counter == 1) {
                $li_class = "news_item1";
            } else if (($discussion_counter == 2) || ($discussion_counter == 3)) {
                $li_class = "news_item2 ";
                if ($showallnews == false) {
                    $li_class .= "mobilehidden";
                }
            }

            $news_item = '<li   id ="newsbody' . $discussion->id . '"  class ="' . $li_class . '"   id ="newsbody' . $discussion->id . '" >';
            $news_item .= '<div class="newsbody"  id ="newsbody' . $discussion->id . '_item" >
					<div    id ="discussion_' . $discussion->id . '"  class="news_message_title action_' . $action . '" >';

            if ($action == 'edit') {
                $news_item .= '<span class="bullet_edit_news">';
            } else {
                $news_item .= '<span class="bullet">';
            }
            $news_item .= $news_item_status . '</span>' . $news_body . "</div>";
            $news_item .= '<div class="post_message" >' . format_text($discussion->message, $discussion->messageformat, NULL, $forumx->course);
            if ($discussion->attachment) {
                $attachements = forumx_print_attachments($discussion, $cm, 'html');
                $news_item .= $attachements;
            }
            $news_item .= "</div>";
            $news_item .= $actions . '</div></li>';
            $output .= $news_item;
        } // End foreach.
        $output .= '</ul>';
        if (!$showallnews) {
            $output .= '<div  id ="get_all_message" class="mobilehidden visible-xs"><a   href="' . $CFG->wwwroot . '/mod/forumx/news_items.php?id=' .
                $course->id . '"   class="news_post_action">' . get_string('to_all_news', 'forumx') . '</a></div>';
        }
        $output .= "</div>" . $footer;
    } // Else has news.

    $container = '<div id="newsarea_border" ><div id="newsarea" tabindex="0">
				<h2 class="quicklinksheader"><i class="fa fa-bullhorn"></i>' . get_string('news_area', 'format_topcoll') . '</h2>';
    if ($has_new_items) {
        $container .= '<div class="new_items_header visible-xs"><span> ' . get_string('unread', 'forumx') . '</span></div>';
    }
    $container .= $output . '</div>';
    if ($discussion_counter > 1) {
        if (!$showallnews) {
            $container .= '<div id="mobile_close_news" class="visible-xs"><i id ="mobile_news_action" class="fa fa-chevron-circle-down"></i></div>';
        }
    }
    $container .= '</div>';

    return $container;
}

function forumx_post_availability_changes($forumx_id, $clear_subscribe)
{
    global $DB;

    $cm = get_coursemodule_from_instance('forumx', $forumx_id);
    $modcontext = context_module::instance($cm->id);
    $forum = $DB->get_record('forumx', array('id' => $forumx_id));
    $exam_users = forumx_get_users_in_groupname($forum->course, 'exam');

    if ($clear_subscribe) {
        forumx_clear_subscribe($forum->id, $forum->course, true, false);
    }
    if (($forum->forcesubscribe == forumx_INITIALSUBSCRIBE)) {
        $users = \mod_forumx\subscriptions::get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            if (isset($exam_users[$user->id])) {
                continue;
            }
            $uservisible = \core_availability\info_module::is_user_visible($cm, $user->id, false);
            if ($uservisible) {
                \mod_forumx\subscriptions::subscribe_user($user->id, $forum, $modcontext);
            }
        }
    }
}


function forumx_subscribe_grouping_update($courseid, $groupingid)
{

    global $DB;
    $param = array();

    $param['groupingid'] = $groupingid;
    $param['courseid'] = $courseid;

    $grouping_forums_query = "SELECT instance, course   , availability FROM mdl_course_modules  , mdl_modules AS m
		WHERE  m.name ='forumx' AND m.id=module  and course = :courseid AND availability LIKE '%\"type\":\"grouping\",\"id\":" . $groupingid . "%'";


    $recs = $DB->get_records_sql($grouping_forums_query, $param);

    foreach ($recs as $rec) {
        echo "<BR>forum : " . $rec->instance;
        forumx_post_availability_changes($rec->instance, true);
    }

}

/**
 * Get icon mapping for font-awesome.
 *
 * @return  array
 */
function mod_forumx_get_fontawesome_icon_map()
{
    return [
        'mod_forumx:i/pinned' => 'fa-map-pin',
        'mod_forumx:t/selected' => 'fa-check',
        'mod_forumx:t/subscribed' => 'fa-envelope-o',
        'mod_forumx:t/unsubscribed' => 'fa-envelope-open-o',
    ];
}

function find_user_link($content)
{

    preg_match_all('/{(.*?)}/', $content, $matches);
    foreach ($matches[0] as $user) {
        $raw = substr($user, 1, -1);
        $data = explode(', ', $raw);
        $content = str_replace($user, '<a href="../../user/view.php?id=' . $data[1] . '">' . $data[0] . '</a>', $content);
    }

    return $content;
}

function count_likes($postid, $discussionId)
{
    global $DB, $USER;

    $userstatus = $DB->get_record('forumx_posts_likes', array('userid' => $USER->id, 'discussion' => $discussionId, 'post' => $postid));
    $countlike = $DB->count_records('forumx_posts_likes', array('discussion' => $discussionId, 'post' => $postid));

    $tmp = new stdClass();
    $tmp->status = ($userstatus ? true : false);
    $tmp->count = $countlike;

    return $tmp;
}

function users_likes($postid, $discussionId)
{
    global $DB, $USER;

    $sql = 'SELECT  us.id, us.firstname, us.lastname FROM {forumx_posts_likes} pl, {user} us WHERE pl.discussion = ? AND pl.post = ? AND pl.userid = us.id';
    $users = $DB->get_records_sql($sql, [$discussionId, $postid]);

    return $users;
}