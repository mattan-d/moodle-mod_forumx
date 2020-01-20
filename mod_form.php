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
 * @copyright Jamie Pratt <me@jamiep.org>
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_forumx_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform=& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('forumname', 'forumx'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('forumintro', 'forumx'));

        $forumtypes = forumx_get_forum_types();
        core_collator::asort($forumtypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type', get_string('forumtype', 'forumx'), $forumtypes);
        $mform->addHelpButton('type', 'forumtype', 'forumx');
        $mform->setDefault('type', 'general');

        $mform->addElement('advcheckbox', 'hideauthor', get_string('hideauthor', 'forumx'), get_string('hideauthorcomment', 'forumx'));
        $mform->addHelpButton('hideauthor', 'hideauthor', 'forumx');
        $mform->setDefault('hideauthor', 0);
        $mform->setType('hideauthor', PARAM_INT);
        
        // Attachments and word count.
        $mform->addElement('header', 'attachmentswordcounthdr', get_string('attachmentswordcount', 'forumx'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $CFG->forumx_maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'forumx'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'forumx');
        $mform->setDefault('maxbytes', $CFG->forumx_maxbytes);

        $choices = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'forumx'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'forumx');
        $mform->setDefault('maxattachments', $CFG->forumx_maxattachments);

        $mform->addElement('selectyesno', 'displaywordcount', get_string('displaywordcount', 'forumx'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'forumx');
        $mform->setDefault('displaywordcount', 0);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionandtrackinghdr', get_string('subscriptionandtracking', 'forumx'));

        $options = array();
        $options[forumx_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'forumx');
        $options[forumx_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'forumx');
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'forumx'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'forumx');

        $options = array();
        $options[forumx_TRACKING_OPTIONAL] = get_string('trackingoptional', 'forumx');
        $options[forumx_TRACKING_OFF] = get_string('trackingoff', 'forumx');
        if ($CFG->forumx_allowforcedreadtracking) {
            $options[forumx_TRACKING_FORCED] = get_string('trackingon', 'forumx');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'forumx'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'forumx');
        $default = $CFG->forumx_trackingtype;
        if ((!$CFG->forumx_allowforcedreadtracking) && ($default == forumx_TRACKING_FORCED)) {
            $default = forumx_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        if ($CFG->enablerssfeeds && isset($CFG->forumx_enablerssfeeds) && $CFG->forumx_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'forumx');
            $choices[2] = get_string('posts', 'forumx');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'forumx');
            if (isset($CFG->forumx_rsstype)) {
                $mform->setDefault('rsstype', $CFG->forumx_rsstype);
            }

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'forumx');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
            if (isset($CFG->forumx_rssarticles)) {
                $mform->setDefault('rssarticles', $CFG->forumx_rssarticles);
            }
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'forumx'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','forumx');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'forumx'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'forumx');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'forumx'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'forumx');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'forumx'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'forumx');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

        // Lock forum.
        $mform->addElement('header', 'forumstatusrheader', get_string('forumstatus', 'forumx'));
        $options = array(
        		0=>get_string('unlocked', 'forumx'),
        		1=>get_string('locked', 'forumx')
    	);
        $mform->addElement('select', 'locked', get_string('lockedlabel', 'forumx'), $options);
        $mform->setDefault('locked', forumx_UNLOCKED);
        $mform->addHelpButton('locked', 'lockedlabel', 'forumx');
        
        $mform->addElement('checkbox', 'tlocked', get_string('unlockedtiming', 'forumx'));
        $mform->disabledIf('tlocked', 'locked', 'eq', 1);
        $mform->setType('tlocked', PARAM_INT);
        $mform->setDefault('tlocked', '0');
        
        $mform->addElement('date_time_selector', 'unlocktimestart', get_string('fromdate', 'forumx'));
        $mform->disabledIf('unlocktimestart','tlocked');
        $mform->disabledIf('unlocktimestart','locked','notequal', 0);
        $mform->setType('unlocktimestart', PARAM_INT);
        
        $mform->addElement('date_time_selector','unlocktimefinish', get_string('todate', 'forumx'));
        $mform->disabledIf('unlocktimefinish','tlocked');
        $mform->disabledIf('unlocktimefinish','locked','notequal', 0);
        $mform->setType('unlocktimefinish', PARAM_INT);
        
        $coursecontext = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_forumx');

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

    }

    function definition_after_data() {
        parent::definition_after_data();
        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        // We don't want to have these appear as possible selections in the form but
        // we want the form to display them if they are set.
        if ($typevalue[0]=='news') {
            $type->addOption(get_string('namenews', 'forumx'), 'news');
            $mform->addHelpButton('type', 'namenews', 'forumx');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0]=='social') {
            $type->addOption(get_string('namesocial', 'forumx'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completiondiscussionsenabled']=
            !empty($default_values['completiondiscussions']) ? 1 : 0;
        if (empty($default_values['completiondiscussions'])) {
            $default_values['completiondiscussions'] = 1;
        }
        $default_values['completionrepliesenabled']=
            !empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies'] = 1;
        }
        $default_values['completionpostsenabled']=
            !empty($default_values['completionposts']) ? 1 : 0;
        if (empty($default_values['completionposts'])) {
            $default_values['completionposts'] = 1;
        }
    	if (isset($default_values["locked"])) {
			if ($default_values["locked"] == 0) {
				if (!empty($default_values["unlocktimestart"]) || !empty($default_values["unlocktimefinish"])) {
					$default_values["tlocked"] = 1;
				}
			}
		}
    }

      function add_completion_rules() {
        $mform=& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionpostsenabled', '', get_string('completionposts','forumx'));
        $group[] =& $mform->createElement('text', 'completionposts', '', array('size'=>3));
        $mform->setType('completionposts',PARAM_INT);
        $mform->addGroup($group, 'completionpostsgroup', get_string('completionpostsgroup','forumx'), array(' '), false);
        $mform->disabledIf('completionposts','completionpostsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completiondiscussionsenabled', '', get_string('completiondiscussions','forumx'));
        $group[] =& $mform->createElement('text', 'completiondiscussions', '', array('size'=>3));
        $mform->setType('completiondiscussions',PARAM_INT);
        $mform->addGroup($group, 'completiondiscussionsgroup', get_string('completiondiscussionsgroup','forumx'), array(' '), false);
        $mform->disabledIf('completiondiscussions','completiondiscussionsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionrepliesenabled', '', get_string('completionreplies','forumx'));
        $group[] =& $mform->createElement('text', 'completionreplies', '', array('size'=>3));
        $mform->setType('completionreplies',PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup', get_string('completionrepliesgroup','forumx'), array(' '), false);
        $mform->disabledIf('completionreplies','completionrepliesenabled','notchecked');

        return array('completiondiscussionsgroup','completionrepliesgroup','completionpostsgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completiondiscussionsenabled']) && $data['completiondiscussions']!=0) ||
            (!empty($data['completionrepliesenabled']) && $data['completionreplies']!=0) ||
            (!empty($data['completionpostsenabled']) && $data['completionposts']!=0);
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked.
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiondiscussionsenabled) || !$autocompletion) {
                $data->completiondiscussions = 0;
            }
            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionpostsenabled) || !$autocompletion) {
                $data->completionposts = 0;
            }
        }
        // If the unlock timing checkboxes aren't ticked, empty the time range.
        if (empty($data->tlocked)) {
        	$data->unlocktimestart = null;
        	$data->unlocktimefinish = null;
        }
        return $data;
    }
}

