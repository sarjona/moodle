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
 * @package    datafield
 * @subpackage tag
 * @copyright  2015 onwards Andrew Hancox (andrewdchancox@googlemail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/mod/data/field/text/field.class.php');

class data_field_tag extends data_field_text {

    public $type = 'tag';

    public function generate_sql($tablealias, $value) {
        global $DB;

        static $i=0;
        $i++;
        $name = "df_tag_$i";

        $trimmedvalue = trim($value, '|');
        $trimmedvalue = $DB->sql_like_escape($trimmedvalue);

        if ($trimmedvalue != $value) {
            return array(
                " ({$tablealias}.fieldid = {$this->field->id} AND " . $DB->sql_like("{$tablealias}.content1",
                    ":$name",
                    false) . ") ",
                array($name => "%, $trimmedvalue,%")
            );
        } else {
            return array(
                " ({$tablealias}.fieldid = {$this->field->id} AND ".$DB->sql_like("{$tablealias}.content",
                    ":$name",
                    false).") "
            , array($name=>"%$value%")
            );
        }
    }

    /**
     * Display the content of the field in browse mode
     *
     * @global object
     * @param int $recordid
     * @param object $template
     * @return bool|string
     */
    public function display_browse_field($recordid, $template) {
        global $DB;

        if ($content = $DB->get_record('data_content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid))) {
            if (isset($content->content)) {
                $str = self::cleantext($content->content, true, $this->field->id, $this->data->id);
            } else {
                $str = '';
            }
            return $str;
        }
        return false;
    }

    public function update_content($recordid, $value, $name='') {
        global $DB;

        $value = clean_param($value, PARAM_NOTAGS);

        $searchablevalue = self::cleantext($value, false);

        if (empty($searchablevalue)) {
            return true;
        }

        $content = new stdClass();
        $content->fieldid = $this->field->id;
        $content->recordid = $recordid;
        $content->content = $value;
        $content->content1 = $searchablevalue;

        if ($oldcontent = $DB->get_record('data_content', array('fieldid'=>$this->field->id, 'recordid'=>$recordid))) {
            $content->id = $oldcontent->id;
            return $DB->update_record('data_content', $content);
        } else {
            return $DB->insert_record('data_content', $content);
        }
    }

    public function name() {
        return get_string('name'.$this->type, 'datafield_'.$this->type);
    }

    public static function cleantext($text, $display = false, $fieldid = null, $dataid = null) {
        $options = new stdClass();
        $options->filter=false;
        $options->para = false;

        $tags = explode(',', $text);
        $elements = array();

        if ($display && (empty($fieldid) || empty($dataid))) {
            print_error('When displaying content fieldid and dataid must be provided');
        }

        foreach ($tags as $tag) {
            $tag = trim($tag);

            if (empty($tag)) {
                continue;
            }

            if ($display) {
                $params =  array(
                    "f_{$fieldid}" => '|' . $tag . '|',
                    'd' => $dataid,
                    'advanced' => 1
                );
                $url = new moodle_url('/mod/data/view.php', $params);
                $elements[] = html_writer::link($url, $tag);
            } else {
                $elements[] = $tag;
            }
        }
        $str = implode(', ', $elements);

        if (!$display) {
            $str = ', ' . $str . ',';
        }

        return $str;
    }

    /**
     * Prints the respective type icon
     *
     * @global object
     * @return string
     */
    function image() {
        global $OUTPUT;

        $params = array('d'=>$this->data->id, 'fid'=>$this->field->id, 'mode'=>'display', 'sesskey'=>sesskey());
        $link = new moodle_url('/mod/data/field.php', $params);
        $str = '<a href="'.$link->out().'">';
        $str .= '<img src="'.$OUTPUT->image_url('tag', 'datafield_tag') . '" ';
        $str .= 'height="'.$this->iconheight.'" width="'.$this->iconwidth.'" alt="'.$this->type.'" title="'.$this->type.'" /></a>';
        return $str;
    }

    public function supports_preview(): bool {
        return false;
    }
}
