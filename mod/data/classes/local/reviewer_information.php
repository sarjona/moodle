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

namespace mod_data\local;

use mod_data\local\persistent\record_review;

/**
 * Class responsible for statically caching review data.
 *
 * @package    mod_data
 * @copyright  2025 Justus Dieckmann.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reviewer_information {

    public static array $data;

    public static array $datainstances = [];

    public static function pull_data_for(int $instanceid) {
        if (isset(self::$datainstances[$instanceid])) {
            return;
        }

        $recordreviews = record_review::get_reviews_for_instance($instanceid);
        foreach ($recordreviews as $recordreview) {
            if (!isset(self::$data[$recordreview->get('recordid')])) {
                self::$data[$recordreview->get('recordid')] = [];
            }
            self::$data[$recordreview->get('recordid')][$recordreview->get('revieweruserid')] = $recordreview;
        }
        self::$datainstances[$instanceid] = true;
    }

    public static function get_data_for_record(int $recordid) {
        return self::$data[$recordid];
    }

}
