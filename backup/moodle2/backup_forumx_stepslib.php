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
 * @package    mod_forumx
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @copyright  2020 onwards MOFET
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_forumx_activity_task
 */

/**
 * Define the complete forum structure for backup, with file and id annotations
 */
class backup_forumx_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.

        $forumx = new backup_nested_element('forumx', array('id'), array(
            'type', 'name', 'intro', 'introformat',
            'assessed', 'assesstimestart', 'assesstimefinish', 'scale',
            'maxbytes', 'maxattachments', 'forcesubscribe', 'trackingtype',
            'rsstype', 'rssarticles', 'timemodified', 'warnafter',
            'blockafter', 'blockperiod', 'completiondiscussions', 'completionreplies',
            'completionposts', 'displaywordcount', 'hideauthor', 'locked',
        	'unlocktimestart', 'unlocktimefinish'));

        $discussions = new backup_nested_element('discussions');

        $discussion = new backup_nested_element('discussion', array('id'), array(
            'name', 'firstpost', 'userid', 'groupid',
            'assessed', 'timemodified', 'usermodified', 'timestart',
            'timeend', 'on_top', 'locked'));

        $posts = new backup_nested_element('posts');

        $post = new backup_nested_element('post', array('id'), array(
            'parent', 'userid', 'created', 'modified',
            'mailed', 'subject', 'message', 'messageformat',
            'messagetrust', 'attachment', 'totalscore', 'mailnow', 'mark'));

        $ratings = new backup_nested_element('ratings');

        $rating = new backup_nested_element('rating', array('id'), array(
            'component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated', 'timemodified'));

        $discussionsubs = new backup_nested_element('discussions_sub');

        $discussionsub = new backup_nested_element('discussion_sub', array('id'), array(
            'userid'));

		$flags = new backup_nested_element('flags');
		
		$flag = new backup_nested_element('flag', array('id'), array(
				'userid', 'flagged_date'));

        $subscriptions = new backup_nested_element('subscriptions');

        $subscription = new backup_nested_element('subscription', array('id'), array(
            'userid'));

        $digests = new backup_nested_element('digests');

        $digest = new backup_nested_element('digest', array('id'), array(
            'userid', 'maildigest'));

        $readposts = new backup_nested_element('readposts');

        $read = new backup_nested_element('read', array('id'), array(
            'userid', 'discussionid', 'postid', 'firstread',
            'lastread'));

        $trackedprefs = new backup_nested_element('trackedprefs');

        $track = new backup_nested_element('track', array('id'), array(
            'userid'));

        // Build the tree.

        $forumx->add_child($discussions);
        $discussions->add_child($discussion);

        $forumx->add_child($subscriptions);
        $subscriptions->add_child($subscription);

        $forumx->add_child($digests);
        $digests->add_child($digest);

        $forumx->add_child($readposts);
        $readposts->add_child($read);

        $forumx->add_child($trackedprefs);
        $trackedprefs->add_child($track);

        $discussion->add_child($posts);
        $posts->add_child($post);

        $posts->add_child($flags);
        $flags->add_child($flag);

        $post->add_child($ratings);
        $ratings->add_child($rating);

        $discussion->add_child($discussionsubs);
        $discussionsubs->add_child($discussionsub);

        // Define sources.

        $forumx->set_source_table('forumx', array('id' => backup::VAR_ACTIVITYID));

        // All these source definitions only happen if we are including user info.
        if ($userinfo) {
            $discussion->set_source_sql('
                SELECT *
                  FROM {forumx_discussions}
                 WHERE forumx = ?',
                array(backup::VAR_PARENTID));

            // Need posts ordered by id so parents are always before childs on restore.
            $post->set_source_table('forumx_posts', array('discussion' => backup::VAR_PARENTID), 'id ASC');
            $discussionsub->set_source_table('forumx_discussion_sub', array('discussionid' => backup::VAR_PARENTID));

            $subscription->set_source_table('forumx_subscriptions', array('forumx' => backup::VAR_PARENTID));
            $digest->set_source_table('forumx_digests', array('forumx' => backup::VAR_PARENTID));

            $flag->set_source_table('forumx_flags', array('postid' => backup::VAR_PARENTID));
            $read->set_source_table('forumx_read', array('forumxid' => backup::VAR_PARENTID));

            $track->set_source_table('forumx_track_prefs', array('forumxid' => backup::VAR_PARENTID));

            $rating->set_source_table('rating', array('contextid'  => backup::VAR_CONTEXTID,
                                                      'component'  => backup_helper::is_sqlparam('mod_forumx'),
                                                      'ratingarea' => backup_helper::is_sqlparam('post'),
                                                      'itemid'     => backup::VAR_PARENTID));
            $rating->set_source_alias('rating', 'value');
        }

        // Define id annotations.

        $forumx->annotate_ids('scale', 'scale');

        $discussion->annotate_ids('group', 'groupid');

        $post->annotate_ids('user', 'userid');

        $discussionsub->annotate_ids('user', 'userid');

        $rating->annotate_ids('scale', 'scaleid');

        $rating->annotate_ids('user', 'userid');

        $subscription->annotate_ids('user', 'userid');

        $digest->annotate_ids('user', 'userid');

        $flag->annotate_ids('user', 'userid');

        $read->annotate_ids('user', 'userid');

        $track->annotate_ids('user', 'userid');

        // Define file annotations

        $forumx->annotate_files('mod_forumx', 'intro', null); // This file area hasn't itemid.

        $post->annotate_files('mod_forumx', 'post', 'id');
        $post->annotate_files('mod_forumx', 'attachment', 'id');

        // Return the root element (forumx), wrapped into standard activity structure.
        return $this->prepare_activity_structure($forumx);
    }

}
