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
 * Polyfill class for mbstring.
 *
 * @package    core_h5p
 * @copyright  2019 Carlos Escobedo <carlos@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_h5p;

defined('MOODLE_INTERNAL') || die();

/**
 * Polyfill class for mbstring.
 *
 * @package    core_h5p
 * @copyright  2019 Carlos Escobedo <carlos@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mbstring_polyfill extends \core_text {
    /**
     * Polyfill for mb_substr function.
     *
     * @param  string $str      text to extract the substring from
     * @param  string $start    negative value means from end
     * @param  string $length   maximum length of characters beginning from start
     * @param  string $encoding charset
     * @return string get part of string
     */
    public static function mb_substr(string $str, int $start, int $length = null, string $encoding = 'utf-8') : string {
        return self::substr($str, $start, $length, $encoding);
    }

    /**
     * Polyfill for mb_strtolower function.
     *
     * @param  string $str      the text being lowercased
     * @param  string $encoding charset
     * @return string Returns str with all alphabetic characters converted to lowercase.
     */
    public static function mb_strtolower(string $str, string $encoding = 'utf-8') : string {
        return self::strtolower($str, $encoding);
    }
}