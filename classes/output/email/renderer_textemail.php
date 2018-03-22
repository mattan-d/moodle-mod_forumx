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
 * Forum post renderable.
 *
 * @package    mod_ouilforum
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ouilforum\output\email;

defined('MOODLE_INTERNAL') || die();

/**
 * Forum post renderable.
 *
 * @since      Moodle 3.0
 * @package    mod_ouilforum
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer_textemail extends renderer {

    /**
     * The template name for this renderer.
     *
     * @return string
     */
    public function ouilforum_post_template() {
        return 'ouilforum_post_email_textemail';
    }

    /**
     * The plaintext version of the e-mail message.
     *
     * @param \stdClass $cm
     * @param \stdClass $post
     * @return string
     */
    public function format_message_text($cm, $post) {
        $message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php',
            \context_module::instance($cm->id)->id,
            'mod_ouilforum', 'post', $post->id);
        return format_text_email($message, $post->messageformat);
    }

    /**
     * The plaintext version of the attachments list.
     *
     * @param \stdClass $cm
     * @param \stdClass $post
     * @return string
     */
    public function format_message_attachments($cm, $post) {
        return ouilforum_print_attachments($post, $cm, "text");
    }
}
