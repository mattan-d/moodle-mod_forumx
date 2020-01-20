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
 * Event observers used in forum.
 *
 * @package    mod_forumx
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_forumx.
 */
class mod_forumx_observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // NOTE: this has to be as fast as possible.
        // Get user enrolment info from event.
        $cp = (object)$event->other['userenrolment'];
        if ($cp->lastenrol) {
            if (!$forums = $DB->get_records('forumx', array('course' => $cp->courseid),'', 'id')) {
            	return;
            }
            list($forumselect, $params) = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED);
            $params['userid'] = $cp->userid;

            $DB->delete_records_select('forumx_digests', 'userid = :userid AND forumx '.$forumselect, $params);
            $DB->delete_records_select('forumx_subscriptions', 'userid = :userid AND forumx '.$forumselect, $params);
            $DB->delete_records_select('forumx_track_prefs', 'userid = :userid AND forumxid '.$forumselect, $params);
            $DB->delete_records_select('forumx_read', 'userid = :userid AND forumxid '.$forumselect, $params);
        }
    }

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $CFG, $DB;

        $context = context::instance_by_id($event->contextid, MUST_EXIST);

        // If contextlevel is course then only subscribe user. Role assignment
        // at course level means user is enroled in course and can subscribe to forum.
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // Forum lib required for the constant used below.
        require_once($CFG->dirroot.'/mod/forumx/lib.php');

        $userid = $event->relateduserid;
        $sql = "SELECT f.id, f.course as course, cm.id AS cmid, f.forcesubscribe
                  FROM {forumx} f
                  JOIN {course_modules} cm ON (cm.instance = f.id)
                  JOIN {modules} m ON (m.id = cm.module)
             LEFT JOIN {forumx_subscriptions} fs ON (fs.forumx = f.id AND fs.userid = :userid)
                 WHERE f.course = :courseid
                   AND f.forcesubscribe = :initial
                   AND m.name = 'forumx'
                   AND fs.id IS NULL";
        $params = array('courseid' => $context->instanceid, 'userid' => $userid, 'initial' => forumx_INITIALSUBSCRIBE);

        $forums = $DB->get_records_sql($sql, $params);
        foreach ($forums as $forum) {
            // If user doesn't have allowforcesubscribe capability then don't subscribe.
        	$uservisible = \core_availability\info_module::is_user_visible($forum->cmid,$userid, false);
        	
            $modcontext = context_module::instance($forum->cmid);
            if (has_capability('mod/forumx:allowforcesubscribe', $modcontext, $userid)  && $uservisible) {
                \mod_forumx\subscriptions::subscribe_user($userid, $forum, $modcontext);
            }
        }
    }

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'forumx') {
            // Include the forum library to make use of the forumx_instance_created function.
            require_once($CFG->dirroot.'/mod/forumx/lib.php');

            $forum = $event->get_record_snapshot('forumx', $event->other['instanceid']);
            forumx_instance_created($event->get_context(), $forum);
        }
    }
    
    /**
     * Observer for \core\event\group_member_added event.
     *
     * @param \core\event\group_member_added $event
     * @return void
     */
    public static function group_member_added(\core\event\group_member_added $event) {
    	global $CFG, $DB;
    	$userid = $event->relateduserid;
    	$groupid = $event->objectid;
    	// Include the forum library to make use of the forum_instance_created function.
    	require_once($CFG->dirroot.'/mod/forumx/lib.php');
    	if ($group_object = $DB->get_record('groups', array('id'=>$groupid))) {
    		if ($group_object->name == 'exam') {
    			forumx_forceunsubscribe_user($group_object->courseid, $userid);
    		} else {
    			forumx_forcesubscribe_user_by_groupid($group_object->courseid, $userid, $groupid);
    		}
    	}
    }
    
    /**
     * Observer for \core\event\group_member_added event.
     *
     * @param \core\event\group_member_added $event
     * @return void
     */
    public static function group_member_removed(\core\event\group_member_removed $event) {
    	global $CFG,$DB;
    	$userid = $event->relateduserid;
    	$groupid = $event->objectid;
    	//mtrace ("<br>remve from   " .$groupid);
    	// Include the forum library to make use of the forum_instance_created function.
    	if ($group_object = $DB->get_record('groups', array('id'=>$groupid))) {
	    	require_once($CFG->dirroot.'/mod/forumx/lib.php');
    	
	    	if ($group_object->name == 'exam') {
	    	//	mtrace ("<br>forumx_forcesubscribe_user_by_groupid from   " .$groupid);
	    		forumx_forcesubscribe_user_by_groupid($group_object->courseid, $userid, $groupid);
	    	} else{
	    		forumx_forceunsubscribe_user($group_object->courseid, $userid);
	    	}
    	
    	}
    }
    

    
    public static function role_unassigned(\core\event\role_unassigned $event) {
    	global $CFG,$DB;
    	$userid = $event->relateduserid;
    	//mtrace ("<br> role_unassigned");
    	
    	$context = context::instance_by_id($event->contextid, MUST_EXIST);
    	if ($context->contextlevel != CONTEXT_COURSE) {
    		return;
    	}
    	$courseid = $context->instanceid;
//    	mtrace ("<br>role_unassigned  " .$courseid);
    	// Include the forum library to make use of the forum_instance_created function.
   		require_once($CFG->dirroot.'/mod/forumx/lib.php');
   		forumx_forceunsubscribe_user_role($courseid, $userid);
    	
    }
    
    
    
    
    public static function grouping_updated(\core\event\grouping_updated $event) {
    	global $CFG;
    	 
    	$groupingid=$event->objectid;
    	// Include the forum library to make use of the forum_instance_created function.
    	require_once($CFG->dirroot . '/mod/forumx/lib.php');
    		
    	forumx_subscribe_grouping_update($courseid , $groupingid);
    
    }
    
    
    
    public static function grouping_group_assigned(\core\event\grouping_group_assigned $event) {

    	global $CFG;
    	$groupingid=$event->objectid;
    	$courseid=$event->courseid;
    	// Include the forum library to make use of the forum_instance_created function.
    	require_once($CFG->dirroot . '/mod/forumx/lib.php');
    	forumx_subscribe_grouping_update($courseid , $groupingid);
    
    }
    
    
    public static function grouping_group_unassigned(\core\event\grouping_group_unassigned $event) {
    

    	global $CFG;
    	$groupingid=$event->objectid;
    	$courseid=$event->courseid;
    	// Include the forum library to make use of the forum_instance_created function.
    	require_once($CFG->dirroot . '/mod/forumx/lib.php');
    	forumx_subscribe_grouping_update($courseid , $groupingid);
    
    }
   
    
}
