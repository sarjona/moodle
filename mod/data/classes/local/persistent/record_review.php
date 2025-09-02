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

namespace mod_data\local\persistent;

use core\persistent;

class record_review extends persistent {


    /**
     * Database data.
     */
    const TABLE = 'data_record_review';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return array(
            'recordid' => [
                'type' => PARAM_INT,
            ],
            'revieweruserid' => [
                'type' => PARAM_INT,
            ],
            'reviewtext' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
            ],
            'reviewgrade' => [
                'type' => PARAM_FLOAT,
                'null' => NULL_ALLOWED,
            ],
            'approval' => [
                'type' => PARAM_BOOL,
                'null' => NULL_ALLOWED,
            ]
        );
    }

    public static function get_reviews_for_instance(int $instanceid) {
        return self::get_records_select(
            'recordid IN (SELECT recordid FROM data_record_review WHERE instanceid = :instanceid)',
            ['instanceid' => $instanceid]
        );
    }

}
