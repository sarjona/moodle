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
 * Testing the mbstring_polyfill class implementation.
 *
 * @package    core_h5p
 * @category   test
 * @copyright  2019 Carlos Escobedo <carlos@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use \core_h5p\mbstring_polyfill;
defined('MOODLE_INTERNAL') || die();

/**
 * Test class covering the H5PFileStorage interface implementation.
 *
 * @package    core_h5p
 * @copyright  2019 Carlos Escobedo <carlos@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mbstring_polyfill_testcase extends advanced_testcase {
    /**
     * Basic setup for these tests.
     */
    public function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Test Polyfill for mb_substr function.
     */
    public function test_mb_substr() {
        $text = '1234567890 abcdfg';
        $result = mbstring_polyfill::mb_substr($text, 0, 10);
        $expected = core_text::substr($text, 0, 10);
        $this->assertEquals($expected, $result);
        $this->assertSame('abc', mbstring_polyfill::substr($text, 11, 3));
        $this->assertSame('f', mbstring_polyfill::substr($text, -2, 1));
        $this->assertSame($text, mbstring_polyfill::mb_substr($text, 0));

        // Since mbstring_polyfill::mb_substr is based on core_text.
        // The next two tests are taken from lib/tests/text_test.php.
        // To test euc_char_mapping.
        $str = pack("H*", "bcf2cce5d6d0cec4"); // GB2312.
        $s = pack("H*", "cce5"); // GB2312.
        $this->assertSame($s, mbstring_polyfill::substr($str, 1, 1, 'GB2312'));

        // To test sb_char_mapping.
        $iso2 = pack("H*", "ae6c75bb6f75e86bfd206b6f6eede8656b");
        $this->assertSame(core_text::convert('luť', 'utf-8', 'iso-8859-2'),
            mbstring_polyfill::mb_substr($iso2, 1, 3, 'iso-8859-2'));
    }

    /**
     * Test Polyfill for mb_strtolower function.
     */
    public function test_mb_strtolower() {
        // Since mbstring_polyfill::mb_strtolower is based on core_text/typo3.
        // The next tests are taken from lib/tests/text_test.php.
        $text = "Žluťoučký koníček";
        $low = 'žluťoučký koníček';
        $this->assertSame($low, mbstring_polyfill::mb_strtolower($text));

        $str = pack("H*", "bcf2cce5d6d0cec4"); // GB2312.
        $this->assertSame($str, mbstring_polyfill::mb_strtolower($str, 'GB2312'));

        $iso2 = pack("H*", "ae6c75bb6f75e86bfd206b6f6eede8656b");
        $this->assertSame(core_text::convert($low, 'utf-8', 'iso-8859-2'),
            mbstring_polyfill::mb_strtolower($iso2, 'iso-8859-2'));
    }
}