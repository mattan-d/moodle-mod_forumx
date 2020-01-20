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
 * Definition of log events
 *
 * @package   mod_forumx
 * @category  log
 * @copyright 2010 Petr Skoda (http://skodak.org)
 * @copyright 2020 onwards MOFET
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $DB; // TODO: this is a hack, we should really do something with the SQL in SQL tables

$logs = array(
    array('module'=>'forumx', 'action'=>'add', 'mtable'=>'forumx', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'update', 'mtable'=>'forumx', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'add discussion', 'mtable'=>'forumx_discussions', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'add post', 'mtable'=>'forumx_posts', 'field'=>'subject'),
    array('module'=>'forumx', 'action'=>'update post', 'mtable'=>'forumx_posts', 'field'=>'subject'),
    array('module'=>'forumx', 'action'=>'user report', 'mtable'=>'user', 'field'=>$DB->sql_concat('firstname', "' '" , 'lastname')),
    array('module'=>'forumx', 'action'=>'move discussion', 'mtable'=>'forumx_discussions', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'view subscribers', 'mtable'=>'forumx', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'view discussion', 'mtable'=>'forumx_discussions', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'view forum', 'mtable'=>'forumx', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'subscribe', 'mtable'=>'forumx', 'field'=>'name'),
    array('module'=>'forumx', 'action'=>'unsubscribe', 'mtable'=>'forumx', 'field'=>'name'),
);