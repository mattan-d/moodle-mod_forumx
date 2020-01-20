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
 * File containing the form definition to forward a post in the forum.
 *
 * @package   mod_forumx
 * @copyright 2015 onwards The Open University of Israel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class mod_forumx_forward_form extends moodleform {

	function definition() {
		global $CFG, $OUTPUT, $DB;

        $mform =& $this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $forumx = $this->_customdata['forumx'];
        $post = $this->_customdata['post'];
        
        $modcontext = context_module::instance($cm->id);
        $mform->addElement('header', 'forward_data', "123");
        $mform->addElement('text', 'email', get_string('forwardemailaddress', 'forumx'), array('size'=>48));
        $mform->setType('email', PARAM_EMAIL);
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->addRule('email', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('email', get_string('invalidemail'), 'email', null, 'client');
        $mform->addRule('email', get_string('useremaildontexist', 'forumx'), 'required', null, 'server');
        $mform->addHelpButton('email', 'forwardemailaddress', 'forumx');
        
        $mform->addElement('checkbox', 'ccme', get_string('forwardccme', 'forumx'));
        
        $mform->addElement('text', 'subject', get_string('subject', 'forumx'), array('size'=>48));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->setDefault('subject', $this->_customdata['subject']);
        
        $mform->addElement('editor', 'message', get_string('forwardintro', 'forumx'), null);
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('message', 'forwardintro', 'forumx');
        
    	$mform->addElement('hidden', 'f', $forumx->id);
    	$mform->setType('f', PARAM_INT);
    	$mform->addElement('hidden', 'postid', $post->id);
    	$mform->setType('postid', PARAM_INT);
    	$mform->addElement('hidden', 'format', FORMAT_HTML);
    	$mform->setType('format', PARAM_INT);

    	$this->add_action_buttons(true, get_string('forwardbymail', 'forumx'));
		
	}
	
	function validation($data, $files) {
		global $CFG, $DB;
		$errors = parent::validation($data, $files);
		
		if (!$DB->record_exists('user', array('email'=>$data['email']))) {
			$errors['email'] = get_string('useremaildontexist', 'forumx');
		}
		return $errors;
	}
}

?>