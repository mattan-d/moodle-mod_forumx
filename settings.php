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
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/forumx/lib.php');

    $settings->add(new admin_setting_configselect('forumx_displaymode', get_string('displaymode', 'forumx'),
                       get_string('configdisplaymode', 'forumx'), forumx_MODE_NESTED, forumx_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('forumx_replytouser', get_string('replytouser', 'forumx'),
                       get_string('configreplytouser', 'forumx'), 1));

    // Less non-HTML characters than this is short.
    $settings->add(new admin_setting_configtext('forumx_shortpost', get_string('shortpost', 'forumx'),
                       get_string('configshortpost', 'forumx'), 300, PARAM_INT));

    // More non-HTML characters than this is long.
    $settings->add(new admin_setting_configtext('forumx_longpost', get_string('longpost', 'forumx'),
                       get_string('configlongpost', 'forumx'), 600, PARAM_INT));

    // Number of discussions on a page.
    $settings->add(new admin_setting_configtext('forumx_manydiscussions', get_string('manydiscussions', 'forumx'),
                       get_string('configmanydiscussions', 'forumx'), 100, PARAM_INT));

    // Number of replies on a view page.
    $settings->add(new admin_setting_configtext('forumx_replieslimit', get_string('replieslimit', 'forumx'),
                       get_string('configreplieslimit', 'forumx'), 50, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->forumx_maxbytes)) {
            $maxbytes = $CFG->forumx_maxbytes;
        }
        $settings->add(new admin_setting_configselect('forumx_maxbytes', get_string('maxattachmentsize', 'forumx'),
                           get_string('configmaxbytes', 'forumx'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all forums.
    $settings->add(new admin_setting_configtext('forumx_maxattachments', get_string('maxattachments', 'forumx'),
                       get_string('configmaxattachments', 'forumx'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[forumx_TRACKING_OPTIONAL] = get_string('trackingoptional', 'forumx');
    $options[forumx_TRACKING_OFF] = get_string('trackingoff', 'forumx');
    $options[forumx_TRACKING_FORCED] = get_string('trackingon', 'forumx');
    $settings->add(new admin_setting_configselect('forumx_trackingtype', get_string('trackingtype', 'forumx'),
                       get_string('configtrackingtype', 'forumx'), forumx_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('forumx_trackreadposts', get_string('trackforum', 'forumx'),
                       get_string('configtrackreadposts', 'forumx'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('forumx_allowforcedreadtracking', get_string('forcedreadtracking', 'forumx'),
                       get_string('forcedreadtracking_desc', 'forumx'), 0));

    // Default number of days that a post is considered old.
    $settings->add(new admin_setting_configtext('forumx_oldpostdays', get_string('oldpostdays', 'forumx'),
                       get_string('configoldpostdays', 'forumx'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('forumx_usermarksread', get_string('usermarksread', 'forumx'),
                       get_string('configusermarksread', 'forumx'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d", $i);
    }
    // Default time (hour) to execute 'clean_read_records' cron.
    $settings->add(new admin_setting_configselect('forumx_cleanreadtime', get_string('cleanreadtime', 'forumx'),
                       get_string('configcleanreadtime', 'forumx'), 2, $options));

    // Default time (hour) to send digest email.
    $settings->add(new admin_setting_configselect('ouildigestmailtime', get_string('digestmailtime', 'forumx'),
                       get_string('configdigestmailtime', 'forumx'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'forumx').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'forumx');
    }
    $settings->add(new admin_setting_configselect('forumx_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'forumx'),
            2 => get_string('posts', 'forumx')
        );
        $settings->add(new admin_setting_configselect('forumx_rsstype', get_string('rsstypedefault', 'forumx'),
                get_string('configrsstypedefault', 'forumx'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('forumx_rssarticles', get_string('rssarticles', 'forumx'),
                get_string('configrssarticlesdefault', 'forumx'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('forumx_enabletimedposts', get_string('timedposts', 'forumx'),
                       get_string('configenabletimedposts', 'forumx'), 0));

    $settings->add(new admin_setting_configcheckbox('forumx_enableredirectpage', get_string('enableredirectpage', 'forumx'),
                       get_string('configenableredirectpage', 'forumx'), 0));
    
    $settings->add(new admin_setting_configselect('forumx_splitshortname', get_string('splitshortname', 'forumx'),
    		get_string('configsplitshortname', 'forumx'), 0, array(
    				forumx_EXTRACT_SHORTNAME_NONE => get_string('splitshortname:none', 'forumx'),
    				forumx_EXTRACT_SHORTNAME_PRE => get_string('splitshortname:pre', 'forumx'),
    				forumx_EXTRACT_SHORTNAME_POST => get_string('splitshortname:post', 'forumx')
    		)));
    
    $settings->add(new admin_setting_configtext_with_maxlength('forumx_shortnamedelimiter', get_string('shortnamedelimiter', 'forumx'),
    		get_string('configshortnamedelimiter', 'forumx'), '', PARAM_RAW_TRIMMED, 1, 1));

    $settings->add(new admin_setting_configtextarea('forumx_filterpost', get_string('filterpostconfig', 'forumx'),
    		get_string('filterpostconfig_desc', 'forumx'),
    		null, PARAM_RAW_TRIMMED));
}