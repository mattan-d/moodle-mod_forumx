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
 * External forum API
 *
 * @package   mod_forumx
 * @copyright 2012 Mark Nelson <markn@moodle.com>
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');

class mod_forumx_external extends external_api
{

    /**
     * Describes the parameters for get_forum.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses_parameters()
    {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                    VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of forums in a provided list of courses,
     * if no list is provided all forums that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the forum details
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses($courseids = array())
    {
        global $CFG;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::get_forums_by_courses_parameters(), array('courseids' => $courseids));

        if (empty($params['courseids'])) {
            $params['courseids'] = array_keys(enrol_get_my_courses());
        }

        // Array to store the forums to return.
        $arrforums = array();
        $warnings = array();

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids']);

            // Get the forums in this course. This function checks users visibility permissions.
            $forums = get_all_instances_in_courses('forumx', $courses);
            foreach ($forums as $forum) {

                $course = $courses[$forum->course];
                $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id);
                $context = context_module::instance($cm->id);

                // Skip forums we are not allowed to see discussions.
                if (!has_capability('mod/forumx:viewdiscussion', $context)) {
                    continue;
                }

                $forum->name = external_format_string($forum->name, $context->id);
                // Format the intro before being returning using the format setting.
                list($forum->intro, $forum->introformat) = external_format_text($forum->intro, $forum->introformat,
                    $context->id, 'mod_forumx', 'intro', 0);
                // Discussions count. This function does static request cache.
                $forum->numdiscussions = forumx_count_discussions($forum, $cm, $course);
                $forum->cmid = $forum->coursemodule;
                $forum->cancreatediscussions = forumx_user_can_post_discussion($forum, null, -1, $cm, $context);

                // Add the forum to the array to return.
                $arrforums[$forum->id] = $forum;
            }
        }

        return $arrforums;
    }

    /**
     * Describes the get_forum return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function get_forums_by_courses_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The forum type'),
                    'name' => new external_value(PARAM_RAW, 'Forum name'),
                    'intro' => new external_value(PARAM_RAW, 'The forum intro'),
                    'introformat' => new external_format_value('intro'),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'numdiscussions' => new external_value(PARAM_INT, 'Number of discussions in the forum', VALUE_OPTIONAL),
                    'cancreatediscussions' => new external_value(PARAM_BOOL, 'If the user can create discussions', VALUE_OPTIONAL),
                ), 'forumx'
            )
        );
    }

    /**
     * Describes the parameters for get_forum_discussions.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.5
     * @deprecated Moodle 2.8 MDL-46458 - Please do not call this function any more.
     * @see get_forum_discussions_paginated
     */
    public static function get_forum_discussions_parameters()
    {
        return new external_function_parameters (
            array(
                'forumids' => new external_multiple_structure(new external_value(PARAM_INT, 'forum ID',
                    VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Forum IDs', VALUE_REQUIRED),
                'limitfrom' => new external_value(PARAM_INT, 'limit from', VALUE_DEFAULT, 0),
                'limitnum' => new external_value(PARAM_INT, 'limit number', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Returns a list of forum discussions as well as a summary of the discussion
     * in a provided list of forums.
     *
     * @param array $forumids the forum ids
     * @param int $limitfrom limit from SQL data
     * @param int $limitnum limit number SQL data
     *
     * @return array the forum discussion details
     * @since Moodle 2.5
     * @deprecated Moodle 2.8 MDL-46458 - Please do not call this function any more.
     * @see get_forum_discussions_paginated
     */
    public static function get_forum_discussions($forumids, $limitfrom = 0, $limitnum = 0)
    {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        // Validate the parameter.
        $params = self::validate_parameters(self::get_forum_discussions_parameters(),
            array(
                'forumids' => $forumids,
                'limitfrom' => $limitfrom,
                'limitnum' => $limitnum,
            ));
        $forumids = $params['forumids'];
        $limitfrom = $params['limitfrom'];
        $limitnum = $params['limitnum'];

        // Array to store the forum discussions to return.
        $arrdiscussions = array();
        // Keep track of the users we have looked up in the DB.
        $arrusers = array();

        // Loop through them.
        foreach ($forumids as $id) {
            // Get the forum object.
            $forum = $DB->get_record('forumx', array('id' => $id), '*', MUST_EXIST);
            $course = get_course($forum->course);

            $modinfo = get_fast_modinfo($course);
            $forums = $modinfo->get_instances_of('forumx');
            $cm = $forums[$forum->id];

            // Get the module context.
            $modcontext = context_module::instance($cm->id);

            // Validate the context.
            self::validate_context($modcontext);

            require_capability('mod/forumx:viewdiscussion', $modcontext);

            // Get the discussions for this forum.
            $params = array();

            $groupselect = "";
            $groupmode = groups_get_activity_groupmode($cm, $course);

            if ($groupmode and $groupmode != VISIBLEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                // Get all the discussions from all the groups this user belongs to.
                $usergroups = groups_get_user_groups($course->id);
                if (!empty($usergroups['0'])) {
                    list($sql, $params) = $DB->get_in_or_equal($usergroups['0']);
                    $groupselect = "AND (groupid $sql OR groupid = -1)";
                }
            }
            array_unshift($params, $id);
            $select = "forum = ? $groupselect";

            if ($discussions = $DB->get_records_select('forumx_discussions', $select, $params, 'timemodified DESC', '*',
                $limitfrom, $limitnum)) {

                // Check if they can view full names.
                $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);
                // Get the unreads array, this takes a forum id and returns data for all discussions.
                $unreads = array();
                if ($cantrack = forumx_tp_can_track_forums($forum)) {
                    if ($forumtracked = forumx_tp_is_tracked($forum)) {
                        $unreads = forumx_get_discussions_unread($cm);
                    }
                }
                // The forum function returns the replies for all the discussions in a given forum.
                $replies = forumx_count_discussion_replies($id);

                foreach ($discussions as $discussion) {
                    // This function checks capabilities, timed discussions, groups and qanda forums posting.
                    if (!forumx_user_can_see_discussion($forum, $discussion, $modcontext)) {
                        continue;
                    }

                    $usernamefields = user_picture::fields();
                    // If we don't have the users details then perform DB call.
                    if (empty($arrusers[$discussion->userid])) {
                        $arrusers[$discussion->userid] = $DB->get_record('user', array('id' => $discussion->userid),
                            $usernamefields, MUST_EXIST);
                    }
                    // Get the subject.
                    $subject = $DB->get_field('forumx_posts', 'subject', array('id' => $discussion->firstpost), MUST_EXIST);
                    // Create object to return.
                    $return = new stdClass();
                    $return->id = (int)$discussion->id;
                    $return->course = $discussion->course;
                    $return->forumx = $discussion->forumx;
                    $return->name = $discussion->name;
                    $return->userid = $discussion->userid;
                    $return->groupid = $discussion->groupid;
                    $return->assessed = $discussion->assessed;
                    $return->timemodified = (int)$discussion->timemodified;
                    $return->usermodified = $discussion->usermodified;
                    $return->timestart = $discussion->timestart;
                    $return->timeend = $discussion->timeend;
                    $return->firstpost = (int)$discussion->firstpost;
                    $return->firstuserfullname = fullname($arrusers[$discussion->userid], $canviewfullname);
                    $return->firstuserimagealt = $arrusers[$discussion->userid]->imagealt;
                    $return->firstuserpicture = $arrusers[$discussion->userid]->picture;
                    $return->firstuseremail = $arrusers[$discussion->userid]->email;
                    $return->subject = $subject;
                    $return->numunread = '';
                    if ($cantrack && $forumtracked) {
                        if (isset($unreads[$discussion->id])) {
                            $return->numunread = (int)$unreads[$discussion->id];
                        }
                    }
                    // Check if there are any replies to this discussion.
                    if (!empty($replies[$discussion->id])) {
                        $return->numreplies = (int)$replies[$discussion->id]->replies;
                        $return->lastpost = (int)$replies[$discussion->id]->lastpostid;
                    } else { // No replies, so the last post will be the first post.
                        $return->numreplies = 0;
                        $return->lastpost = (int)$discussion->firstpost;
                    }
                    // Get the last post as well as the user who made it.
                    $lastpost = $DB->get_record('forumx_posts', array('id' => $return->lastpost), '*', MUST_EXIST);
                    if (empty($arrusers[$lastpost->userid])) {
                        $arrusers[$lastpost->userid] = $DB->get_record('user', array('id' => $lastpost->userid),
                            $usernamefields, MUST_EXIST);
                    }
                    $return->lastuserid = $lastpost->userid;
                    $return->lastuserfullname = fullname($arrusers[$lastpost->userid], $canviewfullname);
                    $return->lastuserimagealt = $arrusers[$lastpost->userid]->imagealt;
                    $return->lastuserpicture = $arrusers[$lastpost->userid]->picture;
                    $return->lastuseremail = $arrusers[$lastpost->userid]->email;
                    // Add the discussion statistics to the array to return.
                    $arrdiscussions[$return->id] = (array)$return;
                }
            }
        }

        return $arrdiscussions;
    }

    /**
     * Describes the get_forum_discussions return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     * @deprecated Moodle 2.8 MDL-46458 - Please do not call this function any more.
     * @see get_forum_discussions_paginated
     */
    public static function get_forum_discussions_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'forumx' => new external_value(PARAM_INT, 'The forum id'),
                    'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                    'userid' => new external_value(PARAM_INT, 'User id'),
                    'groupid' => new external_value(PARAM_INT, 'Group id'),
                    'assessed' => new external_value(PARAM_INT, 'Is this assessed?'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                    'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                    'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                    'firstpost' => new external_value(PARAM_INT, 'The first post in the discussion'),
                    'firstuserfullname' => new external_value(PARAM_TEXT, 'The discussion creators fullname'),
                    'firstuserimagealt' => new external_value(PARAM_TEXT, 'The discussion creators image alt'),
                    'firstuserpicture' => new external_value(PARAM_INT, 'The discussion creators profile picture'),
                    'firstuseremail' => new external_value(PARAM_TEXT, 'The discussion creators email'),
                    'subject' => new external_value(PARAM_TEXT, 'The discussion subject'),
                    'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                    'numunread' => new external_value(PARAM_TEXT, 'The number of unread posts, blank if this value is
                        not available due to forum settings.'),
                    'lastpost' => new external_value(PARAM_INT, 'The id of the last post in the discussion'),
                    'lastuserid' => new external_value(PARAM_INT, 'The id of the user who made the last post'),
                    'lastuserfullname' => new external_value(PARAM_TEXT, 'The last person to posts fullname'),
                    'lastuserimagealt' => new external_value(PARAM_TEXT, 'The last person to posts image alt'),
                    'lastuserpicture' => new external_value(PARAM_INT, 'The last person to posts profile picture'),
                    'lastuseremail' => new external_value(PARAM_TEXT, 'The last person to posts email'),
                ), 'discussion'
            )
        );
    }

    /**
     * Describes the parameters for get_forum_discussion_posts.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.7
     */
    public static function get_forum_discussion_posts_parameters()
    {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC')
            )
        );
    }

    /**
     * Returns a list of forum posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the forum post details
     * @since Moodle 2.7
     */
    public static function get_forum_discussion_posts($discussionid, $sortby = "created", $sortdirection = "DESC")
    {
        global $CFG, $DB, $USER, $PAGE;

        $posts = array();
        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::get_forum_discussion_posts_parameters(),
            array(
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection));

        // Compact/extract functions are not recommended.
        $discussionid = $params['discussionid'];
        $sortby = $params['sortby'];
        $sortdirection = $params['sortdirection'];

        $sortallowedvalues = array('id', 'created', 'modified');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('forumx_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $forum = $DB->get_record('forumx', array('id' => $discussion->forum), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/forumx/discuss.php.
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        // Check they have the view forum capability.
        require_capability('mod/forumx:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forumx');

        if (!$post = forumx_get_post_full($discussion->firstpost)) {
            throw new moodle_exception('notexists', 'forumx');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!forumx_user_can_see_post($forum, $discussion, $post, null, $cm)) {
            throw new moodle_exception('noviewdiscussionspermission', 'forumx');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = forumx_user_can_post($forum, $discussion, $USER, $cm, $course, $modcontext);

        $forumtracked = forumx_tp_is_tracked($forum);

        $sort = 'p.' . $sortby . ' ' . $sortdirection;
        $allposts = forumx_get_all_discussion_posts($discussion->id, $sort, $forumtracked);

        foreach ($allposts as $post) {

            if (!forumx_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                $warning = array();
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function forumx_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $post->postread = false;
            } else {
                $post->postread = true;
            }

            $post->canreply = $canreply;
            if (!empty($post->children)) {
                $post->children = array_keys($post->children);
            } else {
                $post->children = array();
            }

            $user = new stdclass();
            $user->id = $post->userid;
            $user = username_load_fields_from_object($user, $post, null, array('picture', 'imagealt', 'email'));
            $post->userfullname = fullname($user, $canviewfullname);

            $userpicture = new user_picture($user);
            $userpicture->size = 1; // Size f1.
            $post->userpictureurl = $userpicture->get_url($PAGE)->out(false);

            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_forumx', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = array();

                $fs = get_file_storage();
                if ($files = $fs->get_area_files($modcontext->id, 'mod_forumx', 'attachment', $post->id, "filename", false)) {
                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        $fileurl = moodle_url::make_webservice_pluginfile_url(
                            $modcontext->id, 'mod_forumx', 'attachment', $post->id, '/', $filename);

                        $post->attachments[] = array(
                            'filename' => $filename,
                            'mimetype' => $file->get_mimetype(),
                            'fileurl' => $fileurl->out(false)
                        );
                    }
                }
            }

            $posts[] = $post;
        }

        $result = array();
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_forum_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function get_forum_discussion_posts_returns()
    {
        return new external_single_structure(
            array(
                'posts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Post id'),
                            'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                            'parent' => new external_value(PARAM_INT, 'Parent id'),
                            'userid' => new external_value(PARAM_INT, 'User id'),
                            'created' => new external_value(PARAM_INT, 'Creation time'),
                            'modified' => new external_value(PARAM_INT, 'Time modified'),
                            'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                            'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                            'message' => new external_value(PARAM_RAW, 'The post message'),
                            'messageformat' => new external_format_value('message'),
                            'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                            'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                            'attachments' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'filename' => new external_value(PARAM_FILE, 'file name'),
                                        'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                        'fileurl' => new external_value(PARAM_URL, 'file download url')
                                    )
                                ), 'attachments', VALUE_OPTIONAL
                            ),
                            'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                            'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                            'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                            'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                            'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                            'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                            'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL)
                        ), 'post'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_forum_discussions_paginated.
     *
     * @return external_external_function_parameters
     * @since Moodle 2.8
     */
    public static function get_forum_discussions_paginated_parameters()
    {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'forum instance id', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns a list of forum discussions optionally sorted and paginated.
     *
     * @param int $forumid the forum instance id
     * @param string $sortby sort by this element (id, timemodified, timestart or timeend)
     * @param string $sortdirection sort direction: ASC or DESC
     * @param int $page page number
     * @param int $perpage items per page
     *
     * @return array the forum discussion details including warnings
     * @since Moodle 2.8
     */
    public static function get_forum_discussions_paginated($forumid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                           $page = -1, $perpage = 0)
    {
        global $CFG, $DB, $USER, $PAGE;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $warnings = array();
        $discussions = array();

        $params = self::validate_parameters(self::get_forum_discussions_paginated_parameters(),
            array(
                'forumid' => $forumid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage
            )
        );

        // Compact/extract functions are not recommended.
        $forumid = $params['forumid'];
        $sortby = $params['sortby'];
        $sortdirection = $params['sortdirection'];
        $page = $params['page'];
        $perpage = $params['perpage'];

        $sortallowedvalues = array('id', 'timemodified', 'timestart', 'timeend');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $forum = $DB->get_record('forumx', array('id' => $forumid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $forum->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // Check they have the view forum capability.
        require_capability('mod/forumx:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forumx');

        $sort = 'd.' . $sortby . ' ' . $sortdirection;
        $alldiscussions = forumx_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage, forumx_POSTS_ALL_USER_GROUPS);

        if ($alldiscussions) {
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

            // Get the unreads array, this takes a forum id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = forumx_tp_can_track_forums($forum)) {
                if ($forumtracked = forumx_tp_is_tracked($forum)) {
                    $unreads = forumx_get_discussions_unread($cm);
                }
            }
            // The forum function returns the replies for all the discussions in a given forum.
            $replies = forumx_count_discussion_replies($forumid, $sort, -1, $page, $perpage);

            foreach ($alldiscussions as $discussion) {

                // This function checks for qanda forums.
                // Note that the forumx_get_discussions returns as id the post id, not the discussion id so we need to do this.
                $discussionrec = clone $discussion;
                $discussionrec->id = $discussion->discussion;
                if (!forumx_user_can_see_discussion($forum, $discussionrec, $modcontext)) {
                    $warning = array();
                    // Function forumx_get_discussions returns forumx_posts ids not forumx_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if ($cantrack && $forumtracked) {
                    if (isset($unreads[$discussion->discussion])) {
                        $discussion->numunread = (int)$unreads[$discussion->discussion];
                    }
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int)$replies[$discussion->discussion]->replies;
                }

                $picturefields = explode(',', user_picture::fields());

                // Load user objects from the results of the query.
                $user = new stdclass();
                $user->id = $discussion->userid;
                $user = username_load_fields_from_object($user, $discussion, null, $picturefields);
                // Preserve the id, it can be modified by username_load_fields_from_object.
                $user->id = $discussion->userid;
                $discussion->userfullname = fullname($user, $canviewfullname);

                $userpicture = new user_picture($user);
                $userpicture->size = 1; // Size f1.
                $discussion->userpictureurl = $userpicture->get_url($PAGE)->out(false);

                $usermodified = new stdclass();
                $usermodified->id = $discussion->usermodified;
                $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um', $picturefields);
                // Preserve the id (it can be overwritten due to the prefixed $picturefields).
                $usermodified->id = $discussion->usermodified;
                $discussion->usermodifiedfullname = fullname($usermodified, $canviewfullname);

                $userpicture = new user_picture($usermodified);
                $userpicture->size = 1; // Size f1.
                $discussion->usermodifiedpictureurl = $userpicture->get_url($PAGE)->out(false);

                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    external_format_text($discussion->message, $discussion->messageformat,
                        $modcontext->id, 'mod_forumx', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = array();

                    $fs = get_file_storage();
                    if ($files = $fs->get_area_files($modcontext->id, 'mod_forumx', 'attachment',
                        $discussion->id, "filename", false)) {
                        foreach ($files as $file) {
                            $filename = $file->get_filename();

                            $discussion->attachments[] = array(
                                'filename' => $filename,
                                'mimetype' => $file->get_mimetype(),
                                'fileurl' => file_encode_url($CFG->wwwroot . '/webservice/pluginfile.php',
                                    '/' . $modcontext->id . '/mod_forumx/attachment/' . $discussion->id . '/' . $filename)
                            );
                        }
                    }
                }

                $discussions[] = $discussion;
            }
        }

        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the get_forum_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function get_forum_discussions_paginated_returns()
    {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Post id'),
                            'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                            'groupid' => new external_value(PARAM_INT, 'Group id'),
                            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                            'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                            'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                            'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                            'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                            'parent' => new external_value(PARAM_INT, 'Parent id'),
                            'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                            'created' => new external_value(PARAM_INT, 'Creation time'),
                            'modified' => new external_value(PARAM_INT, 'Time modified'),
                            'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                            'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                            'message' => new external_value(PARAM_RAW, 'The post message'),
                            'messageformat' => new external_format_value('message'),
                            'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                            'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                            'attachments' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'filename' => new external_value(PARAM_FILE, 'file name'),
                                        'mimetype' => new external_value(PARAM_RAW, 'mime type'),
                                        'fileurl' => new external_value(PARAM_URL, 'file download url')
                                    )
                                ), 'attachments', VALUE_OPTIONAL
                            ),
                            'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                            'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                            'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                            'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                            'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                            'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                            'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                            'numunread' => new external_value(PARAM_INT, 'The number of unread discussions.')
                        ), 'post'
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_forum_parameters()
    {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'forum instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $forumid the forum instance id
     * @return array of warnings and status result
     * @throws moodle_exception
     * @since Moodle 2.9
     */
    public static function view_forum($forumid)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::view_forum_parameters(),
            array(
                'forumid' => $forumid
            ));
        $warnings = array();

        // Request and permission validation.
        $forum = $DB->get_record('forumx', array('id' => $params['forumid']), 'id', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'forumx');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/forumx:viewdiscussion', $context, null, true, 'noviewdiscussionspermission', 'forumx');

        // Call the forum/lib API.
        forumx_view($forum, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_forum_returns()
    {
        return new external_value(PARAM_BOOL, 'status: true if success');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_forum_discussion_parameters()
    {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion id')
            )
        );
    }

    /**
     * Trigger the discussion viewed event.
     *
     * @param int $discussionid the discussion id
     * @return array of warnings and status result
     * @throws moodle_exception
     * @since Moodle 2.9
     */
    public static function view_forum_discussion($discussionid)
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::view_forum_discussion_parameters(),
            array(
                'discussionid' => $discussionid
            ));
        $warnings = array();

        $discussion = $DB->get_record('forumx_discussions', array('id' => $params['discussionid']), '*', MUST_EXIST);
        $forum = $DB->get_record('forumx', array('id' => $discussion->forumx), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'forumx');

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        require_capability('mod/forumx:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'forumx');

        // Call the forum/lib API.
        forumx_discussion_view($modcontext, $forum, $discussion);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_forum_discussion_returns()
    {
        return new external_value(PARAM_BOOL, 'status: true if success');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_post_parameters()
    {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'the post id we are going to reply to
                                                (can be the initial discussion post'),
                'subject' => new external_value(PARAM_TEXT, 'new post subject'),
                'message' => new external_value(PARAM_RAW, 'new post message (only html format allowed)'),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                            '),
                            'value' => new external_value(PARAM_RAW, 'the value of the option,
                                                            this param is validated in the external function.'
                            )
                        )
                    ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function add_discussion_post($postid, $subject, $message, $options = array())
    {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::add_discussion_post_parameters(),
            array(
                'postid' => $postid,
                'subject' => $subject,
                'message' => $message,
                'options' => $options
            ));
        // Validate options.
        $options = array(
            'discussionsubscribe' => true
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        $warnings = array();

        if (!$parent = forumx_get_post_full($params['postid'])) {
            throw new moodle_exception('invalidparentpostid', 'forumx');
        }

        if (!$discussion = $DB->get_record("forumx_discussions", array("id" => $parent->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'forumx');
        }

        // Request and permission validation.
        $forum = $DB->get_record('forumx', array('id' => $discussion->forumx), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'forumx');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if (!forumx_user_can_post($forum, $discussion, $USER, $cm, $course, $context)) {
            throw new moodle_exception('nopostforum', 'forumx');
        }

        $thresholdwarning = forumx_check_throttling($forum, $cm);
        forumx_check_blocking_threshold($thresholdwarning);

        // Create the post.
        $post = new stdClass();
        $post->discussion = $discussion->id;
        $post->parent = $parent->id;
        $post->subject = $params['subject'];
        $post->message = $params['message'];
        $post->messageformat = FORMAT_HTML;   // Force formatting for now.
        $post->messagetrust = trusttext_trusted($context);
        $post->itemid = 0;

        if ($postid = forumx_add_new_post($post, null)) {

            $post->id = $postid;

            // Trigger events and completion.
            $params = array(
                'context' => $context,
                'objectid' => $post->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumid' => $forum->id,
                    'forumtype' => $forum->type,
                )
            );
            $event = \mod_forumx\event\post_created::create($params);
            $event->add_record_snapshot('forumx_posts', $post);
            $event->add_record_snapshot('forumx_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                ($forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            forumx_post_subscription($settings, $forum, $discussion);
        } else {
            throw new moodle_exception('couldnotadd', 'forumx');
        }

        $result = array();
        $result['postid'] = $postid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_post_returns()
    {
        return new external_value(PARAM_INT, 'new post id');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_quick_discussion_post_parameters()
    {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'the post id we are going to reply to
                                                (can be the initial discussion post'),
                'subject' => new external_value(PARAM_TEXT, 'new post subject'),
                'message' => new external_value(PARAM_RAW, 'new post message (only html format allowed)'),
                'ashtml' => new external_value(PARAM_INT, 'Return HTML structure, default to 0', VALUE_DEFAULT, 0),
                'postlevel' => new external_value(PARAM_INT, 'new post display level, default to 1', VALUE_DEFAULT, 1),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                            '),
                            'value' => new external_value(PARAM_RAW, 'the value of the option,
                                                            this param is validated in the external function.'
                            )
                        )
                    ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function add_quick_discussion_post($postid, $subject, $message, $ashtml = 0, $postlevel = 1, $options = array())
    {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::add_quick_discussion_post_parameters(),
            array(
                'postid' => $postid,
                'subject' => forumx_filter_post(htmlspecialchars($subject)),
                'message' => forumx_format_quick_message($message),
                'ashtml' => $ashtml,
                'postlevel' => $postlevel,
                'options' => $options
            ));
        // Validate options.
        $options = array(
            'discussionsubscribe' => true
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        $warnings = array();

        if (!$parent = forumx_get_post_full($params['postid'])) {
            throw new moodle_exception('invalidparentpostid', 'forumx');
        }

        if (!$discussion = $DB->get_record("forumx_discussions", array("id" => $parent->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'forumx');
        }
        // Request and permission validation.
        $forum = $DB->get_record('forumx', array('id' => $discussion->forumx), '*', MUST_EXIST);
        //list($course, $cm) = get_course_and_cm_from_instance($forum, 'forumx');
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        if ($discussion->locked == 1) {
            $results = array(
                'error' => true,
                'errortype' => 'locked',
                'post' => '',
                'errormessage' => get_string('cannotreplylockeddiscussion', 'forumx'),
                'replybutton' => forumx_print_post_reply_button(true)
            );
            if (!has_capability('mod/forumx:lockmessage', $context)) {
                $results['lockicon'] = \html_writer::span('', 'forumx_unlock icon_status', array(
                    'title' => get_string('discussionislocked', 'forumx'),
                    'aria-label' => get_string('discussionislocked', 'forumx')));

            } else {
                $results['lockicon'] = '';
            }
            return $results;
            //throw new moodle_exception('cannotreplylockeddiscussion', 'forumx');
        }


        if (!forumx_user_can_post($forum, $discussion, $USER, $cm, $course, $context)) {
            throw new moodle_exception('nopostforum', 'forumx');
        }

        $thresholdwarning = forumx_check_throttling($forum, $cm);
        forumx_check_blocking_threshold($thresholdwarning);

        // A special case where the user can see the full discussion only after posting a reply.
        $return_full_discussion_replies = false;
        if ($forum->type == 'qanda' && !has_capability('mod/forumx:viewqandawithoutposting', $context) &&
            !forumx_user_has_posted($forum->id, $discussion->id, $USER->id)) {
            $return_full_discussion_replies = true;
        }
        // Create the post.
        $post = new stdClass();
        $post->discussion = $discussion->id;
        $post->parent = $parent->id;
        $post->subject = $params['subject'];
        $post->message = $params['message'];
        $post->messageformat = FORMAT_HTML;   // Force formatting for now.
        $post->messagetrust = trusttext_trusted($context);
        $post->itemid = 0;
        $post->forum = $forum->id;

        if ($postid = forumx_add_new_post($post, null)) {

            $post->id = $postid;

            // Trigger events and completion.
            $event_params = array(
                'context' => $context,
                'objectid' => $post->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'forumid' => $forum->id,
                    'forumtype' => $forum->type,
                )
            );
            $event = \mod_forumx\event\post_created::create($event_params);
            $event->add_record_snapshot('forumx_posts', $post);
            $event->add_record_snapshot('forumx_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                ($forum->completionreplies || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            if ($options['discussionsubscribe'] == true) {
                $settings = new stdClass();
                $settings->discussionsubscribe = $options['discussionsubscribe'];
                forumx_post_subscription($settings, $forum, $discussion);
            }
        } else {
            throw new moodle_exception('couldnotadd', 'forumx');
        }
        if ($params['ashtml']) {
            $new_post = forumx_get_post_full($postid);
            $new_post->postlevel = $params['postlevel'];
            $indent_level = $new_post->postlevel === 1 ? ' indentlevel1' : '';
            $new_post->active_post = 1; // Post will be displayed open.
            $displaymode = get_user_preferences('forumx_displaymode', $CFG->forumx_displaymode);
            ob_start();
            $forumtracked = forumx_tp_is_tracked($forum);
            if ($forumtracked && ($displaymode == forumx_MODE_FLATNEWEST || $displaymode == forumx_MODE_FLATOLDEST)) {
                forumx_tp_mark_discussion_read($USER, $discussion->discussion);
                $forumtracked = false;
            }
            if ($return_full_discussion_replies) {
                $post = forumx_get_post_full($discussion->firstpost);
                $canreply = true;
                $canrate = has_capability('mod/forumx:rate', $context);
                forumx_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate, null, null, $postid, true);
            } else {
                echo '<div class="indent flatindent' . $indent_level . '">';
                forumx_print_post($new_post, $discussion, $forum, $cm, $course, true, true, false,
                    '', '', true, true, $forumtracked, false, null, false);
                echo "</div>\n";
            }
            $new_post = ob_get_contents();
            ob_end_clean();
            $result = array(
                'error' => false,
                'post' => $new_post,
                'postid' => $postid,
                'singlepost' => !$return_full_discussion_replies
            );
        } else {
            $result = array(
                'error' => false,
                'postid' => $postid
            );
        }
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_quick_discussion_post_returns()
    {
        return new external_single_structure(
            array(
                'error' => new external_value(PARAM_BOOL, 'Action result'),
                'errortype' => new external_value(PARAM_ALPHA, 'Error type', VALUE_OPTIONAL),
                'post' => new external_value(PARAM_RAW, 'New post', VALUE_OPTIONAL),
                'postid' => new external_value(PARAM_INT, 'New post id'),
                'singlepost' => new external_value(PARAM_BOOL, 'Is a single post', VALUE_OPTIONAL, true),
                'errormessage' => new external_value(PARAM_ALPHANUM, 'Error message', VALUE_OPTIONAL),
                'replybutton' => new external_value(PARAM_RAW, 'Reply button', VALUE_OPTIONAL),
                'lockicon' => new external_value(PARAM_RAW, 'Lock icon', VALUE_OPTIONAL),
            ), 'forumx'
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_parameters()
    {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum instance ID'),
                'subject' => new external_value(PARAM_TEXT, 'New Discussion subject'),
                'message' => new external_value(PARAM_RAW, 'New Discussion message (only html format allowed)'),
                'groupid' => new external_value(PARAM_INT, 'The group, default to -1', VALUE_DEFAULT, -1),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                            '),
                            'value' => new external_value(PARAM_RAW, 'The value of the option,
                                                            This param is validated in the external function.'
                            )
                        )
                    ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Add a new discussion into an existing forum.
     *
     * @param int $forumid the forum instance id
     * @param string $subject new discussion subject
     * @param string $message new discussion message (only html format allowed)
     * @param int $groupid the user course group
     * @param array $options optional settings
     * @return array of warnings and the new discussion id
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function add_discussion($forumid, $subject, $message, $groupid = -1, $options = array())
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::add_discussion_parameters(),
            array(
                'forumid' => $forumid,
                'subject' => $subject,
                'message' => $message,
                'groupid' => $groupid,
                'options' => $options
            ));
        // Validate options.
        $options = array(
            'discussionsubscribe' => true
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        $warnings = array();

        // Request and permission validation.
        $forum = $DB->get_record('forumx', array('id' => $params['forumid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($forum, 'forumx');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Normalize group.
        if (!groups_get_activity_groupmode($cm)) {
            // Groups not supported, force to -1.
            $groupid = -1;
        } else {
            // Check if we receive the default or and empty value for groupid,
            // in this case, get the group for the user in the activity.
            if ($groupid === -1 or empty($params['groupid'])) {
                $groupid = groups_get_activity_group($cm);
            } else {
                // Here we rely in the group passed, forumx_user_can_post_discussion will validate the group.
                $groupid = $params['groupid'];
            }
        }

        if (!forumx_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
            throw new moodle_exception('cannotcreatediscussion', 'forumx');
        }

        $thresholdwarning = forumx_check_throttling($forum, $cm);
        forumx_check_blocking_threshold($thresholdwarning);

        // Create the discussion.
        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $forum->id;
        $discussion->message = $params['message'];
        $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
        $discussion->messagetrust = trusttext_trusted($context);
        $discussion->itemid = 0;
        $discussion->groupid = $groupid;
        $discussion->mailnow = 0;
        $discussion->subject = $params['subject'];
        $discussion->name = $discussion->subject;
        $discussion->timestart = 0;
        $discussion->timeend = 0;

        if ($discussionid = forumx_add_discussion($discussion)) {

            $discussion->id = $discussionid;

            // Trigger events and completion.
            $params = array(
                'context' => $context,
                'objectid' => $discussion->id,
                'other' => array(
                    'forumid' => $forum->id,
                )
            );
            $event = \mod_forumx\event\discussion_created::create($params);
            $event->add_record_snapshot('forumx_discussions', $discussion);
            $event->trigger();

            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                ($forum->completiondiscussions || $forum->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            forumx_post_subscription($settings, $forum, $discussion);
        } else {
            throw new moodle_exception('couldnotadd', 'forumx');
        }

        $result = array();
        $result['discussionid'] = $discussionid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_returns()
    {
        return new external_value(PARAM_INT, 'New Discussion ID');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function recommend_post_parameters()
    {
        return new external_function_parameters (
            array(
                'action' => new external_value(PARAM_TEXT, 'action type'),
                'post' => new external_value(PARAM_INT, 'post id'),
                'discussion' => new external_value(PARAM_INT, 'discussion id')
            )
        );
    }

    public static function recommend_post($action, $post, $discussion)
    {
        global $DB, $USER;

        if (!$post = $DB->get_record('forumx_posts', array('id' => $post))) {
            return;
        }
        // Add recommendation.
        if ($action == 'recommendpost') {
            return \mod_forumx\post_actions::recommend_post($post);
        } else if ($action == 'unrecommendpost') {
            // Remove recommendation.
            \mod_forumx\post_actions::unrecommend_post($post);
            // Now check if the discussion has recommaned messages.
            return \mod_forumx\post_actions::is_discussion_recommended($discussion);
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function recommend_post_returns()
    {
        return new external_value(PARAM_BOOL, 'Does the discussion still has an icon');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function flag_post_parameters()
    {
        return new external_function_parameters (
            array(
                'action' => new external_value(PARAM_TEXT, 'action type'),
                'post' => new external_value(PARAM_INT, 'post id'),
                'discussion' => new external_value(PARAM_INT, 'discussion id')
            )
        );
    }

    public static function flag_post($action, $post, $discussion)
    {
        global $DB, $USER;

        if (!$post = $DB->get_record('forumx_posts', array('id' => $post))) {
            return;
        }
        // Add flag
        $flag_int = 1;
        if ($action == 'flagpost') {
            $flag_int = 1;
        } else if ($action == 'unflagpost') {
            $flag_int = 0;
        } else {
            $flag_int = (int)$action;
        }
        if ($flag_int > 0) {
            return \mod_forumx\post_actions::add_flag($post->id, $USER->id, $flag_int);
        } else if ($flag_int == 0) {
            \mod_forumx\post_actions::remove_flag($post->id, $USER->id);
            // Now check if the discussion has flagged messages.
            return \mod_forumx\post_actions::is_discussion_flagged($discussion, $USER->id);
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function flag_post_returns()
    {
        return new external_value(PARAM_BOOL, 'Does the discussion still has an icon');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function single_read_parameters()
    {
        return new external_function_parameters (
            array(
                'forum' => new external_value(PARAM_INT, 'forum id'),
                'post' => new external_value(PARAM_INT, 'post id')
            )
        );
    }

    public static function single_read($forum, $post)
    {
        global $DB, $USER, $CFG;

        if (!$post = $DB->get_record('forumx_posts', array('id' => $post))) {
            return;
        }
        require_once($CFG->dirroot . '/mod/forumx/lib.php');
        // Mark the post as read
        return forumx_tp_mark_post_read($USER->id, $post, $forum);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function single_read_returns()
    {
        return new external_value(PARAM_BOOL, 'Has the post been marked as read');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function multiple_read_parameters()
    {
        return new external_function_parameters (
            array(
                'forum' => new external_value(PARAM_INT, 'forum id'),
                'discussionid' => new external_value(PARAM_INT, 'discussion id'),
                'posts' => new external_value(PARAM_SEQUENCE, 'posts ids')
            )
        );
    }

    public static function multiple_read($forum, $discussionid, $posts)
    {
        global $USER, $CFG, $DB;

        // Mark the post as read.
        $posts = explode(',', $posts);
        list($idsql, $idparams) = $DB->get_in_or_equal($posts);
        $idparams[] = $discussionid;
        // Make sure all posts belong to the same discussion.
        if (!$posts = $DB->get_records_select('forumx_posts', 'id ' . $idsql . ' AND discussion=?', $idparams)) {
            return false;
        }
        require_once($CFG->dirroot . '/mod/forumx/lib.php');
        $posts = array_keys($posts);
        return forumx_tp_mark_posts_read($USER, $posts);
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function multiple_read_returns()
    {
        return new external_value(PARAM_BOOL, 'Has all the posts been marked as read');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function subscribe_discussion_parameters()
    {
        return new external_function_parameters (
            array(
                'discussion' => new external_value(PARAM_INT, 'discussion id'),
                'subscribe' => new external_value(PARAM_ALPHA, 'subscribe action')
            )
        );
    }

    public static function subscribe_discussion($discussion, $subscribe)
    {
        global $USER, $CFG, $DB;

        if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'forumx');
        }
        if (!$forum = $DB->get_record('forumx', array('id' => $discussion->forumx))) {
            throw new moodle_exception('invalidforumid', 'forumx');
        }
        require_once($CFG->dirroot . '/mod/forumx/lib.php');
        if ($subscribe == 'subscribe') {
            // Check if the forum is subscribable.
            if (!\mod_forumx\subscriptions::is_subscribable($forum)) {
                return array(
                    'error' => true,
                    'errortype' => 'unsubscribable',
                    'errormessage' => get_string('errorsubscribediscussion:unsubscribable', 'forumx'));
            } else {
                // Check if the user is subscribed to the forum.
                if (\mod_forumx\subscriptions::is_subscribed($USER->id, $forum)) {
                    return array(
                        'error' => true,
                        'errortype' => 'subscribed',
                        'errormessage' => get_string('errorsubscribediscussion:subscribedtoforum', 'forumx'));
                } else {
                    if (\mod_forumx\subscriptions::subscribe_user_to_discussion($USER->id, $discussion)) {
                        return array('error' => false);
                    } else {
                        return array('error' => true);
                    }
                }
            }
        } else if ($subscribe == 'unsubscribe') {
            if (\mod_forumx\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion)) {
                return array('error' => false);
            } else {
                return array('error' => true);
            }
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function subscribe_discussion_returns()
    {
        new external_single_structure(
            array(
                'error' => new external_value(PARAM_BOOL, 'Action result'),
                'errortype' => new external_value(PARAM_ALPHA, 'Error type', VALUE_OPTIONAL),
                'errormessage' => new external_value(PARAM_ALPHANUM, 'Error message', VALUE_OPTIONAL),
            ), 'forumx'
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_quick_discussion_parameters()
    {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum instance ID'),
                'subject' => new external_value(PARAM_TEXT, 'New Discussion subject'),
                'message' => new external_value(PARAM_RAW, 'New Discussion message (only html format allowed)'),
                'groupid' => new external_value(PARAM_INT, 'The group, default to -1', VALUE_DEFAULT, -1),
                'togroups' => new external_value(PARAM_INT, 'Post to all groups, default to 0', VALUE_DEFAULT, 0),
                'ashtml' => new external_value(PARAM_INT, 'Return HTML structure, default to 0', VALUE_DEFAULT, 0),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true'),
                            'value' => new external_value(PARAM_RAW, 'The value of the option,
                                        This param is validated in the external function.')
                        )), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Add a new discussion into an existing forum.
     *
     * @param int $forumid the forum instance id
     * @param string $subject new discussion subject
     * @param string $message new discussion message (only html format allowed)
     * @param int $groupid the user course group
     * @param int $ashtml return a complete html element of the new discussion
     * @param array $options optional settings
     * @return array of warnings and the new discussion
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function add_quick_discussion($forumid, $subject, $message, $groupid = -1, $togroups = 0, $ashtml = 0, $options = array())
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::add_quick_discussion_parameters(),
            array(
                'forumid' => $forumid,
                'subject' => forumx_filter_post(htmlspecialchars($subject)),
                'message' => forumx_format_quick_message($message),
                'groupid' => $groupid,
                'togroups' => $togroups,
                'ashtml' => $ashtml,
                'options' => $options
            ));
        // Validate options.
        $options = array(
            'discussionsubscribe' => true
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        $warnings = array();

        // Request and permission validation.
        $forum = $DB->get_record('forumx', array('id' => $params['forumid']), '*', MUST_EXIST);
        //list($course, $cm) = get_course_and_cm_from_instance($forum, 'forumx');
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        $groupslist = array();
        $can_post_to_groups = $params['togroups'] && forumx_can_post_to_groups($forum, $cm, $course, $context, $groupslist);
        // Normalize group.
        if (!groups_get_activity_groupmode($cm)) {
            // Groups not supported, force to -1.
            $groupid = -1;
        } else {
            // Check if we receive the default or and empty value for groupid,
            // in this case, get the group for the user in the activity.
            if ($groupid === -1 or empty($params['groupid'])) {
                $groupid = groups_get_activity_group($cm);
            } else {
                // Here we rely in the group passed, forumx_user_can_post_discussion will validate the group.
                $groupid = $params['groupid'];
            }
        }

        if (!$can_post_to_groups && !forumx_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
            throw new moodle_exception('cannotcreatediscussion', 'forumx');
        }

        $thresholdwarning = forumx_check_throttling($forum, $cm);
        forumx_check_blocking_threshold($thresholdwarning);

        $posting_all_groups = false;
        if (empty($groupslist)) {
            $groupslist[] = $groupid;
        } else {
            $posting_all_groups = true;
        }
        $new_ids = array();
        $current_group_discussion = -1;
        foreach ($groupslist as $gid) {
            // Create the discussion.
            $discussion = new stdClass();
            $discussion->course = $course->id;
            $discussion->forumx = $forum->id;
            $discussion->message = $params['message'];
            $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
            $discussion->messagetrust = trusttext_trusted($context);
            $discussion->itemid = 0;
            $discussion->groupid = $gid;
            $discussion->mailnow = 0;
            $discussion->subject = $params['subject'];
            $discussion->name = $discussion->subject;
            $discussion->timestart = 0;
            $discussion->timeend = 0;
            $discussion->on_top = 0;
            $discussion->locked = 0;

            if ($discussionid = forumx_add_discussion($discussion)) {

                $discussion->id = $discussionid;
                $new_ids[] = $discussionid;
                // If posting to all groups, save the discussion id for the group the user is viewing.
                if ($posting_all_groups && $params['groupid'] > -1 && $params['groupid'] == $gid) {
                    $current_group_discussion = $discussionid;
                }

                // Trigger events and completion.
                $event_params = array(
                    'context' => $context,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'forumid' => $forum->id,
                    )
                );
                $event = \mod_forumx\event\discussion_created::create($event_params);
                $event->add_record_snapshot('forumx_discussions', $discussion);
                $event->trigger();

                $completion = new completion_info($course);
                if ($completion->is_enabled($cm) &&
                    ($forum->completiondiscussions || $forum->completionposts)) {
                    $completion->update_state($cm, COMPLETION_COMPLETE);
                }

                if ($options['discussionsubscribe'] == true) {
                    $settings = new stdClass();
                    $settings->discussionsubscribe = $options['discussionsubscribe'];
                    forumx_post_subscription($settings, $forum, $discussion);
                }
            } else {
                throw new moodle_exception('couldnotadd', 'forumx');
            }
        }
        if ($params['ashtml']) {
            ob_start();
            // If the user is viewing a single group in the forum, return only the discussion for this group.
            if ($posting_all_groups && $params['groupid'] > -1) {
                $new_ids = array($current_group_discussion);
            }
            foreach ($new_ids as $newid) {
                forumx_get_single_discussion($forum, $context, $course, $cm, $newid, forumx_DISPLAY_OPEN);
            }
            $new_discussion = ob_get_contents();
            ob_end_clean();
            $result = $new_discussion;
        } else {
            $result = $new_ids;
        }
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_quick_discussion_returns()
    {
        return new external_value(PARAM_RAW, 'New Discussion html');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function delete_post_parameters()
    {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'post id')
            )
        );
    }

    /**
     * Delete a post or a discussion from a forum.
     * @param int $post
     * @throws moodle_exception
     */
    public static function delete_post($postid = 0)
    {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        if (!$post = forumx_get_post_full($postid)) {
            throw new moodle_exception('invalidpostid', 'forumx');
        }
        if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $post->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'forumx');
        }
        if (!$forum = $DB->get_record('forumx', array('id' => $discussion->forumx))) {
            throw new moodle_exception('invalidforumid', 'forumx');
        }
        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }
        //$course = get_course($forum->course);
        if (!$course = $DB->get_record('course', array('id' => $forum->course))) {
            throw new moodle_exception('invalidcourseid');
        }

        $modcontext = context_module::instance($cm->id);
        $delete_any_post = has_capability('mod/forumx:deleteanypost', $modcontext);
        if ($post->userid != $USER->id && !$delete_any_post) {
            throw new moodle_exception('cannotdeletepost', 'forumx');
        }

        $replycount = forumx_count_replies($post);
        $is_first_level = $post->parent == $discussion->firstpost;
        // Check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !$delete_any_post) {
            throw new moodle_exception('cannotdeletepost', 'forumx');
        }
        if ($post->totalscore) {
            throw new moodle_exception('couldnotdeleteratings', 'rating');
        } else if ($replycount && !$delete_any_post && $is_first_level) {
            throw new moodle_exception('couldnotdeletereplies', 'forumx');
        } else {
            // Post is a discussion topic as well, so delete discussion.
            if (!$post->parent) {
                if ($forum->type === 'single') {
                    throw new moodle_exception('cannotdeletesinglediscussion', 'forumx');
                }
                forumx_delete_discussion($discussion, false, $course, $cm, $forum);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'forumid' => $forum->id,
                    )
                );

                $event = \mod_forumx\event\discussion_deleted::create($params);
                $event->add_record_snapshot('forumx_discussions', $discussion);
                $event->trigger();

                // Special case: In 'eachuser' type forum, if the user is the owner of the deleted discussion,
                // return the add discussion elements.
                if (!$post->parent && $forum->type === 'eachuser' && $post->userid == $USER->id) {
                    $context = context_module::instance($cm->id);
                    self::validate_context($context);
                    $currentgroup = -1;
                    // Normalize group.
                    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
                        // Groups not supported, force to -1.
                        $currentgroup = -1;
                    } else {
                        // Check if we receive the default or and empty value for groupid,
                        // in this case, get the group for the user in the activity.
                        if ($currentgroup === -1) {
                            $currentgroup = groups_get_activity_group($cm);
                        }
                    }
                    if (forumx_user_can_post_discussion($forum, $currentgroup, -1, $cm, $context)) {
                        $canstart = true;
                        $button = forumx_print_new_message_button($canstart, $forum, $currentgroup, $groupmode, $context);
                        $dialog = forumx_print_new_message_dialog($forum, $groupmode, $currentgroup);
                        return array(
                            'deleted' => true,
                            'button' => $button,
                            'dialog' => $dialog
                        );
                    } else {
                        return array('deleted' => true);
                    }
                } else {
                    return array('deleted' => true);
                }
            } else {
                $delete_option = !$is_first_level ? 'reorder' : $delete_any_post;
                if (!forumx_delete_post($post, $delete_option, $course, $cm, $forum)) {
                    throw new moodle_exception('errorwhiledelete', 'forumx');
                }
                return array('deleted' => true);
            }
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function delete_post_returns()
    {
        return new external_single_structure(
            array(
                'deleted' => new external_value(PARAM_BOOL, 'Delete action status'),
                'button' => new external_value(PARAM_RAW, 'Add discussion button', VALUE_OPTIONAL),
                'dialog' => new external_value(PARAM_RAW, 'Add discussion dialog', VALUE_OPTIONAL)
            ), 'forumx'
        );
    }

    public static function subscribe_forum_parameters()
    {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'forum id'),
                'subscribe' => new external_value(PARAM_ALPHA, 'subscribe action')
            )
        );
    }

    public static function subscribe_forum($forumid, $subscribe)
    {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        if (!$forum = $DB->get_record('forumx', array('id' => $forumid))) {
            throw new moodle_exception('invalidforumid', 'forumx');
        }
        $course = get_course($forum->course);

        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }
        $context = context_module::instance($cm->id);

        // Unsubscribe from forum + discussions + digests.
        if ($subscribe == 'unsubscribe') {
            return \mod_forumx\subscriptions::unsubscribe_user($USER->id, $forum, $context, true);
        } else if ($subscribe == 'subscribe') {
            $issubscribed = \mod_forumx\subscriptions::is_subscribed($USER->id, $forum, null, $cm);
            if ($issubscribed) {
                return true;
            } else {
                if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
                    $groupmode = $cm->groupmode;
                } else {
                    $groupmode = $course->groupmode;
                }
                if ($groupmode && !has_capability('moodle/site:accessallgroups', $context)) {
                    if (!groups_get_all_groups($course->id, $USER->id)) {
                        throw new moodle_exception('cannotsubscribe', 'forumx');
                    }
                }
                if (!\mod_forumx\subscriptions::is_subscribable($forum)) {
                    throw new moodle_exception('cannotsubscribe', 'forumx');
                } else {
                    $coursecontext = context_course::instance($course->id);
                    if (!isguestuser($USER) && (is_enrolled($context, $USER, '', true)) || has_capability('moodle/course:view', $coursecontext)) {
                        if (\mod_forumx\subscriptions::subscribe_user($USER->id, $forum) != false) {
                            return true;
                        } else {
                            return false;
                        }
                    } else {
                        throw new moodle_exception('cannotsubscribe', 'forumx');
                    }
                }
            }
        }
    }

    public static function subscribe_forum_returns()
    {
        return new external_value(PARAM_BOOL, 'Subscribtion result');
    }

    public static function subscribe_forums_parameters()
    {
        return new external_function_parameters (
            array(
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'subscribe' => new external_value(PARAM_ALPHA, 'subscribe action')
            )
        );
    }

    public static function subscribe_forums($courseid, $subscribe)
    {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $course = get_course($courseid);
        $coursecontext = context_course::instance($courseid);
        if (isguestuser() || !isloggedin()) {
            return -1;
        }
        \mod_forumx\subscriptions::fill_subscription_cache_for_course($courseid, $USER->id);
        $modinfo = get_fast_modinfo($course);
        $forums = $DB->get_records('forumx', array('course' => $courseid));

        $actions = array();

        foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
            if (!$cm->uservisible or !isset($forums[$forumid])) {
                continue;
            }
            if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
                continue;   // Shouldn't happen.
            }
            if (!has_capability('mod/forumx:viewdiscussion', $context)) {
                continue;
            }
            if ($subscribe == 'unsubscribe') {
                \mod_forumx\subscriptions::unsubscribe_user($USER->id, $forums[$forumid], $context, true);
                $actions[] = $forumid;
            } else if ($subscribe == 'subscribe') {
                if (\mod_forumx\subscriptions::is_subscribed($USER->id, $forums[$forumid], null, $cm)) {
                    $actions[] = $forumid;
                    continue;
                } else {
                    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
                        $groupmode = $cm->groupmode;
                    } else {
                        $groupmode = $course->groupmode;
                    }
                    if ($groupmode && !has_capability('moodle/site:accessallgroups', $context)) {
                        if (!groups_get_all_groups($course->id, $USER->id)) {
                            continue;
                        }
                    }
                    if (!\mod_forumx\subscriptions::is_subscribable($forums[$forumid])) {
                        continue;
                    } else {
                        if (is_enrolled($context, $USER, '', true) || has_capability('moodle/course:view', $coursecontext)) {
                            if (\mod_forumx\subscriptions::subscribe_user($USER->id, $forums[$forumid]) != false) {
                                $actions[] = $forumid;
                                continue;
                            } else {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    }
                }
            }
        }
        if (empty($actions)) {
            return 0;
        }
        return implode(',', $actions);
    }

    public static function subscribe_forums_returns()
    {
        return new external_value(PARAM_RAW, 'Course forums subscribtion result');
    }

    public static function track_forum_parameters()
    {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'forum id'),
                'track' => new external_value(PARAM_ALPHA, 'tracking action'),
                'inview' => new external_value(PARAM_BOOL, 'in view page'),
                'discussions' => new external_value(PARAM_SEQUENCE, 'discussions ids')
            )
        );
    }

    public static function track_forum($forumid, $track, $inview, $discussions = null)
    {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        if (!$forum = $DB->get_record('forumx', array('id' => $forumid))) {
            throw new moodle_exception('invalidforumid', 'forumx');
        }
        $course = get_course($forum->course);

        if (!$cm = get_coursemodule_from_instance('forumx', $forum->id, $forum->course)) {
            throw new moodle_exception('invalidcoursemodule');
        }
        $context = context_module::instance($cm->id);

        $is_tracked = forumx_tp_is_tracked($forum, $USER);

        $eventparams = array(
            'context' => $context,
            'relateduserid' => $USER->id,
            'other' => array('forumid' => $forum->id),

        );
        if ($track == 'untrack') {
            if (!$is_tracked) {
                return array('result' => true, 'unread' => 0);
            } else {
                if (forumx_tp_stop_tracking($forum->id, $USER->id)) {
                    $event = \mod_forumx\event\readtracking_disabled::create($eventparams);
                    $event->trigger();
                    return array('result' => true, 'unread' => 0);
                } else {
                    return array('result' => false, 'unread' => 0);
                }
            }
        } else if ($track == 'track') {
            if (!$is_tracked) {
                if (!forumx_tp_can_track_forums($forum)) {
                    throw new moodle_exception('cannottrack');
                }
                if (forumx_tp_start_tracking($forum->id, $USER->id)) {
                    $event = \mod_forumx\event\readtracking_enabled::create($eventparams);
                    $event->trigger();
                }
            }
            $unread = 0;
            if ($inview) {
                $unread = forumx_tp_get_untracked_posts($USER->id, $discussions);
            } else {
                $unread = forumx_tp_count_forum_unread_posts($cm, $course);
            }
            return array('result' => true, 'unread' => $unread);
        }
    }

    public static function track_forum_returns()
    {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_BOOL, 'Track action results'),
                'unread' => new external_value(PARAM_RAW, 'Unread posts (if track was activated)'),
            )
        );
    }

    public static function track_forums_parameters()
    {
        return new external_function_parameters (
            array(
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'track' => new external_value(PARAM_ALPHA, 'tracking action')
            )
        );
    }

    public static function track_forums($courseid, $track)
    {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/mod/forumx/lib.php');
        if (!$usetracking = forumx_tp_can_track_forums()) {
            return array(array('id' => -1, 'unread' => 0));
        }
        $forums = $DB->get_records('forumx', array('course' => $courseid));
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $tracked = array();
        $actions = array();
        foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
            if (!$cm->uservisible or !isset($forums[$forumid])) {
                continue;
            }
            if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
                continue;   // Shouldn't happen.
            }
            if (!has_capability('mod/forumx:viewdiscussion', $context)) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $is_tracked = forumx_tp_is_tracked($forums[$forumid], $USER);
            $eventparams = array(
                'context' => $context,
                'relateduserid' => $USER->id,
                'other' => array('forumid' => $forumid)
            );

            if ($track == 'untrack') {
                if (!$is_tracked) {
                    $actions[$forumid] = array('id' => $forumid, 'unread' => 0);
                    continue;
                } else {
                    if (forumx_tp_stop_tracking($forumid, $USER->id)) {
                        $event = \mod_forumx\event\readtracking_disabled::create($eventparams);
                        $event->trigger();
                        $actions[$forumid] = array('id' => $forumid, 'unread' => 0);
                        continue;
                    } else {
                        continue;
                    }
                }
            } else if ($track == 'track') {
                if (!$is_tracked) {
                    if (!forumx_tp_can_track_forums($forums[$forumid])) {
                        continue;
                    }
                    if (forumx_tp_start_tracking($forumid, $USER->id)) {
                        $event = \mod_forumx\event\readtracking_enabled::create($eventparams);
                        $event->trigger();
                    }
                }
                $tracked[$forumid] = $forumid;
                $actions[$forumid] = array('id' => $forumid, 'unread' => 0); // Unread posts will be added later.
            }
        }
        // Since the unread posts result is cached on the first call, we cannot use it in the loop.
        if (!empty($tracked)) {
            foreach ($modinfo->get_instances_of('forumx') as $forumid => $cm) {
                if (isset($tracked[$forumid])) {
                    $actions[$forumid]['unread'] = forumx_tp_count_forum_unread_posts($cm, $course);
                }
            }
        }
        if (empty($actions)) {
            return array(array('id' => 0, 'unread' => 0));
        }
        return $actions;
    }

    public static function track_forums_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Forum id'),
                    'unread' => new external_value(PARAM_INT, 'Unread posts')
                ), 'forumx'
            )
        );
    }

    public static function lock_discussion_parameters()
    {
        return new external_function_parameters (
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum id'),
                'discussionid' => new external_value(PARAM_INT, 'Discussion id'),
                'lock' => new external_value(PARAM_ALPHA, 'Lock action'),
            )
        );
    }

    public static function lock_discussion($forumid, $discussionid, $lock)
    {
        global $CFG, $DB;
        if (!$forum = $DB->get_record("forumx", array("id" => $forumid))) {
            throw new moodle_exception('invalidcoursemodule', 'forumx');
        }
        if (!$course = $DB->get_record("course", array("id" => $forum->course))) {
            throw new moodle_exception('invalidcourseid');
        }
        if (!$cm = get_coursemodule_from_instance("forumx", $forum->id, $course->id)) {
            throw new moodle_exception('invalidcoursemodule');
        }

        if (!$discussion = $DB->get_record('forumx_discussions', array('id' => $discussionid))) {
            throw new moodle_exception('cannotfinddiscussion', 'forumx');
        }

        $discussion->locked = $lock == 'lock' ? 1 : 0;
        if (!$DB->update_record("forumx_discussions", $discussion)) {
            throw new moodle_exception("couldnotupdate", "forumx");
        }
        require_once($CFG->dirroot . '/mod/forumx/lib.php');
        $output = forumx_print_post_reply_button($lock == 'lock');
        return $output;
    }

    public static function lock_discussion_returns()
    {
        return new external_value(PARAM_RAW, 'Lock result');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function quick_forward_post_parameters()
    {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'the post to forward'),
                'forumid' => new external_value(PARAM_INT, 'the post forum'),
                'email' => new external_value(PARAM_EMAIL, 'the target email'),
                'subject' => new external_value(PARAM_TEXT, 'new post subject'),
                'ccme' => new external_value(PARAM_BOOL, 'Send a copy to sender', VALUE_OPTIONAL, false),
                'message' => new external_value(PARAM_RAW, 'Email message')
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function quick_forward_post($postid, $forumid, $email, $subject, $ccme = false, $message)
    {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $results = array();
        $params = self::validate_parameters(self::quick_forward_post_parameters(),
            array(
                'postid' => $postid,
                'forumid' => $forumid,
                'email' => $email,
                'subject' => $subject,
                'ccme' => $ccme,
                'message' => $message
            ));
        if (!$DB->record_exists('user', array('email' => $params['email']))) {
            $results = array(
                'error' => true,
                'errortype' => 'email',
                'errormessage' => get_string('useremaildontexist', 'forumx')
            );
            return $results;
        }
        if (!$post = forumx_get_post_full($params['postid'])) {
            throw new moodle_exception('invalidpostid', 'forumx', '', $params['postid']);
        }
        // Request and permission validation.
        $forum = $DB->get_record('forumx', array('id' => $params['forumid']), '*', MUST_EXIST);
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        /*****************************************************************/


        // Set stricness to IGNORE_MULTIPLE if in developer mode, to handle test users with the same email.
        $strictness = ($CFG->debugdeveloper) ? IGNORE_MULTIPLE : IGNORE_MISSING; //@todo: check the config variable

        $userto = $DB->get_record('user', array('email' => $params['email']), '*', $strictness);
        $course_unique = forumx_extract_course_shortname($course->shortname);

        $a = (object)array('name' => fullname($USER, true),
            'course_short' => $course->shortname,
            'course_full' => $course->fullname,
            'email' => $USER->email,
            'course_unique' => $course_unique,
            'course_link' => $CFG->wwwroot . '/course/view.php?id=' . $course->id,
            'link_to_sender' => $CFG->wwwroot . '/user/view.php?id=' . $USER->id,
            'forum_name' => $forum->name);

        $post->message = forumx_handle_images_mail($post->message);
        $form = new \stdClass();
        $form->format = FORMAT_HTML;
        $form->message = array('text' => $params['message'], 'format' => FORMAT_HTML);
        $msg = forumx_print_post_plain($post, $cm, $course, $forum, $form, get_string('forwardpreface', 'forumx', $a), false, $userto->timezone);

        $subject = stripslashes($params['subject']) . ' ' . get_string('strconsubject', 'forumx') . ' ' . $course_unique;
        if (!email_to_user($userto, $USER, $subject, $msg[FORMAT_PLAIN], $msg[FORMAT_HTML])) {
            throw new moodle_exception('errorforwardemail', 'forumx', '', $userto->email);
        }

        // Send to me.
        if (!empty($params['ccme'])) {
            if (!email_to_user($USER, $USER, $subject, $msg[FORMAT_PLAIN], $msg[FORMAT_HTML])) {
                throw new moodle_exception('errorforwardemail', 'forumx', '', $USER->email);
            }
        }

        /*****************************************************************************/

        $results = array(
            'error' => false
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function quick_forward_post_returns()
    {
        new external_single_structure(
            array(
                'error' => new external_value(PARAM_BOOL, 'Action result'),
                'errortype' => new external_value(PARAM_ALPHA, 'Error type', VALUE_OPTIONAL),
                'errormessage' => new external_value(PARAM_ALPHANUM, 'Error message', VALUE_OPTIONAL)
            ), 'forumx forward results'
        );
    }

    public static function mark_forum_read_parameters()
    {
        return new external_function_parameters(
            array(
                'forumid' => new external_value(PARAM_INT, 'Forum id'),
                'discussionid' => new external_value(PARAM_INT, 'discussion id', VALUE_OPTIONAL)
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @throws moodle_exception
     * @since Moodle 3.0
     */
    public static function mark_forum_read($forumid, $discussionid = null)
    {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/forumx/lib.php');

        $params = self::validate_parameters(self::mark_forum_read_parameters(),
            array(
                'forumid' => $forumid,
                'discussionid' => $discussionid
            ));
        // Request and permission validation.
        if (!$forum = $DB->get_record("forumx", array("id" => $forumid))) {
            throw new moodle_exception('invalidcoursemodule', 'forumx');
        }
        if (!forumx_tp_is_tracked($forum, $USER)) {
            return true;
        }

        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forumx', $forum->id, $course->id);

        if (!empty($discussionid)) {
            if (!$DB->record_exists('forumx_discussions', array('id' => $discussionid, 'forumx' => $forumid))) {
                throw new moodle_exception('invaliddiscussionid', 'forumx');
            }
            return forumx_tp_mark_discussion_read($USER, $discussionid);
        } else {
            // Mark all postss as read in current group.
            $currentgroup = groups_get_activity_group($cm);
            if (!$currentgroup) {
                // Mark_forum_read requires===false, while get_activity_group may return 0.
                $currentgroup = false;
            }
            return forumx_tp_mark_forum_read($USER, $forum->id, $currentgroup);
        }
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function mark_forum_read_returns()
    {
        return new external_value(PARAM_BOOL, 'status: true if success');
    }


    /* Added by mattan-d */
    public static function find_user_parameters()
    {
        return new external_function_parameters(
            array(
                'typing' => new external_value(PARAM_TEXT, 'some string'),
                'courseid' => new external_value(PARAM_INT, 'course id')
            )
        );
    }

    public static function find_user($typing = '', $courseid)
    {
        global $DB, $CFG, $USER, $PAGE;

        $params = self::validate_parameters(self::find_user_parameters(),
            array(
                'typing' => $typing,
                'courseid' => $courseid
            ));

        $clientusers = explode('@', $typing);
        $context = context_course::instance($courseid);
        $allusers = get_enrolled_users($context);
        $response = [];

        foreach ($allusers as $realuser) {
            foreach ($clientusers as $user) {
                if (strpos($realuser->firstname, trim($user)) !== false || strpos($realuser->lastname, trim($user)) !== false) {
                    array_push($response, array('id' => $realuser->id, 'username' => $realuser->firstname . ' ' . $realuser->lastname));
                }
            }
        }

        return $response;
    }

    public static function find_user_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'user id'),
                    'username' => new external_value(PARAM_RAW, 'user name'),
                )
            )
        );
    }


    // like post
    public static function like_post_parameters()
    {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'post id'),
                'discussionId' => new external_value(PARAM_INT, 'discussion id'),
            )
        );
    }

    public static function like_post($postid, $discussionId)
    {
        global $DB, $CFG, $USER, $PAGE;

        $params = self::validate_parameters(self::like_post_parameters(),
            array(
                'postid' => $postid,
                'discussionId' => $discussionId
            ));


        $response = [];
        $islike = $DB->get_record('forumx_posts_likes', array('userid' => $USER->id, 'discussion' => $discussionId, 'post' => $postid));
        if ($islike) {
            $DB->delete_records('forumx_posts_likes', ['id' => $islike->id]);
            $action = 'unlike';
        } else {
            $data = new stdClass();
            $data->post = $postid;
            $data->discussion = $discussionId;
            $data->userid = $USER->id;
            $data->status = 1;
            $data->id = $DB->insert_record('forumx_posts_likes', $data);

            $action = 'like';
        }

        $countlike = $DB->count_records('forumx_posts_likes', array('discussion' => $discussionId, 'post' => $postid));
        array_push($response, array('action' => $action, 'likes' => $countlike));

        return $response;
    }

    public static function like_post_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'action' => new external_value(PARAM_TEXT, 'action'),
                    'likes' => new external_value(PARAM_RAW, 'likes'),
                )
            )
        );
    }
}
