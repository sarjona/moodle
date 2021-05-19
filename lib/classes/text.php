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
 * Defines string apis
 *
 * @package    core
 * @copyright  (C) 2001-3001 Eloy Lafuente (stronk7) {@link http://contiento.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!class_exists('CharsetConverter')) {
    require_once($CFG->libdir . '/typo3/src/autoload.php');
}

use TYPO3\CMS\Core\Charset\CharsetConverter;

/**
 * defines string api's for manipulating strings
 *
 * This class is used to manipulate strings under Moodle 1.6 an later. As
 * utf-8 text become mandatory a pool of safe functions under this encoding
 * become necessary. The name of the methods is exactly the
 * same than their PHP originals.
 *
 * A big part of this class acts as a wrapper over the Typo3 charset library,
 * really a cool group of utilities to handle texts and encoding conversion.
 *
 * Take a look to its own copyright and license details.
 *
 * IMPORTANT Note: Typo3 libraries always expect lowercase charsets to use 100%
 * its capabilities so, don't forget to make the conversion
 * from every wrapper function!
 *
 * @package   core
 * @category  string
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_text {

    /**
     * @var string[] Array of strings representing Unicode non-characters
     */
    protected static $noncharacters;

    /**
     * Return t3lib helper class, which is used for conversion between charsets
     *
     * @param bool $reset
     * @return t3lib_cs
     */
    protected static function typo3($reset = false) {
        global $CFG;
        static $typo3cs = null;

        if ($reset) {
            $typo3cs = null;
            return null;
        }

        if (isset($typo3cs)) {
            return $typo3cs;
        }

        // And this directory must exist to allow Typo to cache conversion
        // tables when using internal functions
        make_temp_directory('typo3temp/charset');

        // Constants required by typo3.
        defined('LF') ?: define('LF', chr(10));
        defined('TYPO3_DATA_PATH') ?: define('TYPO3_DATA_PATH', str_replace('\\', '/', $CFG->libdir . '/typo3/data/'));
        defined('TYPO3_TEMP_PATH') ?: define('TYPO3_TEMP_PATH', str_replace('\\', '/', $CFG->tempdir . '/typo3temp'));

        $typo3cs = new CharsetConverter();

        return $typo3cs;
    }

    /**
     * Reset internal textlib caches.
     * @static
     */
    public static function reset_caches() {
        self::typo3(true);
    }

    /**
     * Standardise charset name
     *
     * Please note it does not mean the returned charset is actually supported.
     *
     * @static
     * @param string $charset raw charset name
     * @return string normalised lowercase charset name
     */
    public static function parse_charset($charset) {
        $charset = strtolower($charset);

        // shortcuts so that we do not have to load typo3 on every page

        if ($charset === 'utf8' or $charset === 'utf-8') {
            return 'utf-8';
        }

        if (preg_match('/^(cp|win|windows)-?(12[0-9]{2})$/', $charset, $matches)) {
            return 'windows-'.$matches[2];
        }

        if (preg_match('/^iso-8859-[0-9]+$/', $charset, $matches)) {
            return $charset;
        }

        if ($charset === 'euc-jp') {
            return 'euc-jp';
        }
        if ($charset === 'iso-2022-jp') {
            return 'iso-2022-jp';
        }
        if ($charset === 'shift-jis' or $charset === 'shift_jis') {
            return 'shift_jis';
        }
        if ($charset === 'gb2312') {
            return 'gb2312';
        }
        if ($charset === 'gb18030') {
            return 'gb18030';
        }
        if ($charset === 'ms-ansi') {
            return 'windows-1252';
        }

        // Confirm the charset is supported. mb_encoding_aliases will return false if not supported/unknown.
        $encodings = mb_list_encodings();
        if (in_array($charset, $encodings) || @mb_encoding_aliases($charset)) {
            return $charset;
        }

        // We have reached this stage and haven't matched with anything. Return the original.
        return $charset;
    }

    /**
     * Converts the text between different encodings. It uses iconv extension with //TRANSLIT parameter,
     * falls back to typo3. If both source and target are utf-8 it tries to fix invalid characters only.
     *
     * @param string $text
     * @param string $fromCS source encoding
     * @param string $toCS result encoding
     * @return string|bool converted string or false on error
     */
    public static function convert($text, $fromCS, $toCS='utf-8') {
        $fromCS = self::parse_charset($fromCS);
        $toCS   = self::parse_charset($toCS);

        $text = (string)$text; // we can work only with strings

        if ($text === '') {
            return '';
        }

        if ($fromCS === 'utf-8') {
            $text = fix_utf8($text);
            if ($toCS === 'utf-8') {
                return $text;
            }
        }

        if ($toCS === 'ascii') {
            // Try to normalize the conversion a bit.
            $text = self::specialtoascii($text, $fromCS);
        }

        // Prevent any error notices, do not use //IGNORE so that we get
        // consistent result from Typo3 if iconv fails.
        $result = @iconv($fromCS, $toCS.'//TRANSLIT', $text);

        if ($result === false or $result === '') {
            // note: iconv is prone to return empty string when invalid char encountered, or false if encoding unsupported
            $oldlevel = error_reporting(E_PARSE);
            error_reporting($oldlevel);
        }

        return $result;
    }

    /**
     * Multibyte safe substr() function, uses mbstring or iconv for UTF-8
     *
     * @param string $text string to truncate
     * @param int $start negative value means from end
     * @param int $len maximum length of characters beginning from start
     * @param string $charset encoding of the text
     * @return string portion of string specified by the $start and $len
     */
    public static function substr($text, $start, $len=null, $charset='utf-8') {
        $charset = self::parse_charset($charset);

        if ($charset === 'utf-8') {
            // this is much faster than iconv - see MDL-31142
            if ($len === null) {
                $oldcharset = mb_internal_encoding();
                mb_internal_encoding('UTF-8');
                $result = mb_substr($text, $start);
                mb_internal_encoding($oldcharset);
                return $result;
            }
        }

        // Check whether the charset is supported by mbstring. CP1250 is not supported. Fall back to iconv.
        if (@mb_encoding_aliases($charset)) {
            $result = mb_substr($text, $start, $len, $charset);
        } else {
            $result = iconv_substr($text, $start, $len, $charset);
        }

        return $result;
    }

    /**
     * Truncates a string to no more than a certain number of bytes in a multi-byte safe manner.
     * UTF-8 only!
     *
     * @param string $string String to truncate
     * @param int $bytes Maximum length of bytes in the result
     * @return string Portion of string specified by $bytes
     * @since Moodle 3.1
     */
    public static function str_max_bytes($string, $bytes) {
        return mb_strcut($string, 0, $bytes, 'UTF-8');
    }

    /**
     * Finds the last occurrence of a character in a string within another.
     * UTF-8 ONLY safe mb_strrchr().
     *
     * @param string $haystack The string from which to get the last occurrence of needle.
     * @param string $needle The string to find in haystack.
     * @param boolean $part If true, returns the portion before needle, else return the portion after (including needle).
     * @return string|false False when not found.
     * @since Moodle 2.4.6, 2.5.2, 2.6
     */
    public static function strrchr($haystack, $needle, $part = false) {

        if (function_exists('mb_strrchr')) {
            return mb_strrchr($haystack, $needle, $part, 'UTF-8');
        }

        $pos = self::strrpos($haystack, $needle);
        if ($pos === false) {
            return false;
        }

        $length = null;
        if ($part) {
            $length = $pos;
            $pos = 0;
        }

        return self::substr($haystack, $pos, $length, 'utf-8');
    }

    /**
     * Multibyte safe strlen() function, uses mbstring or iconv for UTF-8. Falls back to mbstring.
     *
     * @param string $text input string
     * @param string $charset encoding of the text
     * @return int number of characters
     */
    public static function strlen($text, $charset='utf-8') {
        $charset = self::parse_charset($charset);

        if ($charset === 'utf-8') {
            if (function_exists('mb_strlen')) {
                return mb_strlen($text, 'UTF-8');
            } else {
                return iconv_strlen($text, 'UTF-8');
            }
        }

        return iconv_strlen($text, $charset);
    }

    /**
     * Multibyte safe strtolower() function, uses mbstring.
     *
     * @param string $text input string
     * @param string $charset encoding of the text (may not work for all encodings)
     * @return string lower case text
     */
    public static function strtolower($text, $charset='utf-8') {
        $charset = self::parse_charset($charset);

        // Confirm mbstring can handle the charset.
        if (@mb_encoding_aliases($charset)) {
            return mb_strtolower($text, $charset);
        }

        // mbstring cannot handle the charset. Convert to UTF-8.
        $convertedtext = self::convert($text, $charset, 'utf-8');
        $result = mb_strtolower($convertedtext);
        $result = self::convert($result, 'utf-8', $charset);
        return $result;
    }

    /**
     * Multibyte safe strtoupper() function, uses mbstring.
     *
     * @param string $text input string
     * @param string $charset encoding of the text (may not work for all encodings)
     * @return string upper case text
     */
    public static function strtoupper($text, $charset='utf-8') {
        $charset = self::parse_charset($charset);

        // Confirm mbstring can handle the charset.
        if (@mb_encoding_aliases($charset)) {
            return mb_strtoupper($text, $charset);
        }

        // mbstring cannot handle the charset. Convert to UTF-8.
        $convertedtext = self::convert($text, $charset, 'utf-8');
        $result = mb_strtoupper($convertedtext);
        $result = self::convert($result, 'utf-8', $charset);
        return $result;
    }

    /**
     * Find the position of the first occurrence of a substring in a string.
     * UTF-8 ONLY safe strpos(), uses mbstring, falls back to iconv.
     *
     * @param string $haystack the string to search in
     * @param string $needle one or more charachters to search for
     * @param int $offset offset from begining of string
     * @return int the numeric position of the first occurrence of needle in haystack.
     */
    public static function strpos($haystack, $needle, $offset=0) {
        if (function_exists('mb_strpos')) {
            return mb_strpos($haystack, $needle, $offset, 'UTF-8');
        } else {
            return iconv_strpos($haystack, $needle, $offset, 'UTF-8');
        }
    }

    /**
     * Find the position of the last occurrence of a substring in a string
     * UTF-8 ONLY safe strrpos(), uses mbstring, falls back to iconv.
     *
     * @param string $haystack the string to search in
     * @param string $needle one or more charachters to search for
     * @return int the numeric position of the last occurrence of needle in haystack
     */
    public static function strrpos($haystack, $needle) {
        if (function_exists('mb_strrpos')) {
            return mb_strrpos($haystack, $needle, null, 'UTF-8');
        } else {
            return iconv_strrpos($haystack, $needle, 'UTF-8');
        }
    }

    /**
     * Reverse UTF-8 multibytes character sets (used for RTL languages)
     * (We only do this because there is no mb_strrev or iconv_strrev)
     *
     * @param string $str the multibyte string to reverse
     * @return string the reversed multi byte string
     */
    public static function strrev($str) {
        preg_match_all('/./us', $str, $ar);
        return join('', array_reverse($ar[0]));
    }

    /**
     * Try to convert upper unicode characters to plain ascii,
     * the returned string may contain unconverted unicode characters.
     *
     * @param string $text input string
     * @param string $charset encoding of the text
     * @return string converted ascii string
     */
    public static function specialtoascii($text, $charset='utf-8') {
        $charset = self::parse_charset($charset);
        $oldlevel = error_reporting(E_PARSE);

        // Typo3 approach.
        // $result = self::typo3()->specCharsToASCII($charset, (string)$text);

        // mbstring approach.
        // $result = mb_convert_encoding((string) $text, $charset);

        // Iconv approach.
        // $result = iconv($charset, 'ASCII//TRANSLIT//IGNORE', (string) $text);

        // Iconv + intl approach.
        // $result = iconv($charset, 'ASCII//TRANSLIT//IGNORE', transliterator_transliterate('Any-Latin; Latin-ASCII', (string) $text));

        // Wordpress approach.
        // $result = self::remove_accents((string) $text);

        // Combination Iconv / Iconv + intl approach.
        if ($charset == 'utf-8') {
            $text = transliterator_transliterate('Any-Latin; Latin-ASCII', (string) $text);
        }
        $result = iconv($charset, 'ASCII//TRANSLIT//IGNORE', (string) $text);

        error_reporting($oldlevel);
        return $result;
    }

    /**
     * Checks to see if a string is utf8 encoded.
     *
     * NOTE: This function checks for 5-Byte sequences, UTF8
     *       has Bytes Sequences with a maximum length of 4.
     *
     * @author bmorel at ssi dot fr (modified)
     * @since 1.2.1
     *
     * @param string $str The string to be checked
     * @return bool True if $str fits a UTF-8 model, false otherwise.
     */
    protected static function seems_utf8( $str ) {
        $length = strlen( $str );
        for ( $i = 0; $i < $length; $i++ ) {
            $c = ord( $str[ $i ] );
            if ( $c < 0x80 ) {
                $n = 0; // 0bbbbbbb
            } else if ( ( $c & 0xE0 ) == 0xC0 ) {
                $n = 1; // 110bbbbb
            } else if ( ( $c & 0xF0 ) == 0xE0 ) {
                $n = 2; // 1110bbbb
            } else if ( ( $c & 0xF8 ) == 0xF0 ) {
                $n = 3; // 11110bbb
            } else if ( ( $c & 0xFC ) == 0xF8 ) {
                $n = 4; // 111110bb
            } else if ( ( $c & 0xFE ) == 0xFC ) {
                $n = 5; // 1111110b
            } else {
                return false; // Does not match any model.
            }
            for ( $j = 0; $j < $n; $j++ ) { // n bytes matching 10bbbbbb follow ?.
                if ( ( ++$i == $length ) || ( ( ord( $str[ $i ] ) & 0xC0 ) != 0x80 ) ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Converts all accent characters to ASCII characters.
     *
     * If there are no accent characters, then the string given is just returned.
     *
     * **Accent characters converted:**
     *
     * Currency signs:
     *
     * |   Code   | Glyph | Replacement |     Description     |
     * | -------- | ----- | ----------- | ------------------- |
     * | U+00A3   | £     | (empty)     | British Pound sign  |
     * | U+20AC   | €     | E           | Euro sign           |
     *
     * Decompositions for Latin-1 Supplement:
     *
     * |  Code   | Glyph | Replacement |               Description              |
     * | ------- | ----- | ----------- | -------------------------------------- |
     * | U+00AA  | ª     | a           | Feminine ordinal indicator             |
     * | U+00BA  | º     | o           | Masculine ordinal indicator            |
     * | U+00C0  | À     | A           | Latin capital letter A with grave      |
     * | U+00C1  | Á     | A           | Latin capital letter A with acute      |
     * | U+00C2  | Â     | A           | Latin capital letter A with circumflex |
     * | U+00C3  | Ã     | A           | Latin capital letter A with tilde      |
     * | U+00C4  | Ä     | A           | Latin capital letter A with diaeresis  |
     * | U+00C5  | Å     | A           | Latin capital letter A with ring above |
     * | U+00C6  | Æ     | AE          | Latin capital letter AE                |
     * | U+00C7  | Ç     | C           | Latin capital letter C with cedilla    |
     * | U+00C8  | È     | E           | Latin capital letter E with grave      |
     * | U+00C9  | É     | E           | Latin capital letter E with acute      |
     * | U+00CA  | Ê     | E           | Latin capital letter E with circumflex |
     * | U+00CB  | Ë     | E           | Latin capital letter E with diaeresis  |
     * | U+00CC  | Ì     | I           | Latin capital letter I with grave      |
     * | U+00CD  | Í     | I           | Latin capital letter I with acute      |
     * | U+00CE  | Î     | I           | Latin capital letter I with circumflex |
     * | U+00CF  | Ï     | I           | Latin capital letter I with diaeresis  |
     * | U+00D0  | Ð     | D           | Latin capital letter Eth               |
     * | U+00D1  | Ñ     | N           | Latin capital letter N with tilde      |
     * | U+00D2  | Ò     | O           | Latin capital letter O with grave      |
     * | U+00D3  | Ó     | O           | Latin capital letter O with acute      |
     * | U+00D4  | Ô     | O           | Latin capital letter O with circumflex |
     * | U+00D5  | Õ     | O           | Latin capital letter O with tilde      |
     * | U+00D6  | Ö     | O           | Latin capital letter O with diaeresis  |
     * | U+00D8  | Ø     | O           | Latin capital letter O with stroke     |
     * | U+00D9  | Ù     | U           | Latin capital letter U with grave      |
     * | U+00DA  | Ú     | U           | Latin capital letter U with acute      |
     * | U+00DB  | Û     | U           | Latin capital letter U with circumflex |
     * | U+00DC  | Ü     | U           | Latin capital letter U with diaeresis  |
     * | U+00DD  | Ý     | Y           | Latin capital letter Y with acute      |
     * | U+00DE  | Þ     | TH          | Latin capital letter Thorn             |
     * | U+00DF  | ß     | s           | Latin small letter sharp s             |
     * | U+00E0  | à     | a           | Latin small letter a with grave        |
     * | U+00E1  | á     | a           | Latin small letter a with acute        |
     * | U+00E2  | â     | a           | Latin small letter a with circumflex   |
     * | U+00E3  | ã     | a           | Latin small letter a with tilde        |
     * | U+00E4  | ä     | a           | Latin small letter a with diaeresis    |
     * | U+00E5  | å     | a           | Latin small letter a with ring above   |
     * | U+00E6  | æ     | ae          | Latin small letter ae                  |
     * | U+00E7  | ç     | c           | Latin small letter c with cedilla      |
     * | U+00E8  | è     | e           | Latin small letter e with grave        |
     * | U+00E9  | é     | e           | Latin small letter e with acute        |
     * | U+00EA  | ê     | e           | Latin small letter e with circumflex   |
     * | U+00EB  | ë     | e           | Latin small letter e with diaeresis    |
     * | U+00EC  | ì     | i           | Latin small letter i with grave        |
     * | U+00ED  | í     | i           | Latin small letter i with acute        |
     * | U+00EE  | î     | i           | Latin small letter i with circumflex   |
     * | U+00EF  | ï     | i           | Latin small letter i with diaeresis    |
     * | U+00F0  | ð     | d           | Latin small letter Eth                 |
     * | U+00F1  | ñ     | n           | Latin small letter n with tilde        |
     * | U+00F2  | ò     | o           | Latin small letter o with grave        |
     * | U+00F3  | ó     | o           | Latin small letter o with acute        |
     * | U+00F4  | ô     | o           | Latin small letter o with circumflex   |
     * | U+00F5  | õ     | o           | Latin small letter o with tilde        |
     * | U+00F6  | ö     | o           | Latin small letter o with diaeresis    |
     * | U+00F8  | ø     | o           | Latin small letter o with stroke       |
     * | U+00F9  | ù     | u           | Latin small letter u with grave        |
     * | U+00FA  | ú     | u           | Latin small letter u with acute        |
     * | U+00FB  | û     | u           | Latin small letter u with circumflex   |
     * | U+00FC  | ü     | u           | Latin small letter u with diaeresis    |
     * | U+00FD  | ý     | y           | Latin small letter y with acute        |
     * | U+00FE  | þ     | th          | Latin small letter Thorn               |
     * | U+00FF  | ÿ     | y           | Latin small letter y with diaeresis    |
     *
     * Decompositions for Latin Extended-A:
     *
     * |  Code   | Glyph | Replacement |                    Description                    |
     * | ------- | ----- | ----------- | ------------------------------------------------- |
     * | U+0100  | Ā     | A           | Latin capital letter A with macron                |
     * | U+0101  | ā     | a           | Latin small letter a with macron                  |
     * | U+0102  | Ă     | A           | Latin capital letter A with breve                 |
     * | U+0103  | ă     | a           | Latin small letter a with breve                   |
     * | U+0104  | Ą     | A           | Latin capital letter A with ogonek                |
     * | U+0105  | ą     | a           | Latin small letter a with ogonek                  |
     * | U+01006 | Ć     | C           | Latin capital letter C with acute                 |
     * | U+0107  | ć     | c           | Latin small letter c with acute                   |
     * | U+0108  | Ĉ     | C           | Latin capital letter C with circumflex            |
     * | U+0109  | ĉ     | c           | Latin small letter c with circumflex              |
     * | U+010A  | Ċ     | C           | Latin capital letter C with dot above             |
     * | U+010B  | ċ     | c           | Latin small letter c with dot above               |
     * | U+010C  | Č     | C           | Latin capital letter C with caron                 |
     * | U+010D  | č     | c           | Latin small letter c with caron                   |
     * | U+010E  | Ď     | D           | Latin capital letter D with caron                 |
     * | U+010F  | ď     | d           | Latin small letter d with caron                   |
     * | U+0110  | Đ     | D           | Latin capital letter D with stroke                |
     * | U+0111  | đ     | d           | Latin small letter d with stroke                  |
     * | U+0112  | Ē     | E           | Latin capital letter E with macron                |
     * | U+0113  | ē     | e           | Latin small letter e with macron                  |
     * | U+0114  | Ĕ     | E           | Latin capital letter E with breve                 |
     * | U+0115  | ĕ     | e           | Latin small letter e with breve                   |
     * | U+0116  | Ė     | E           | Latin capital letter E with dot above             |
     * | U+0117  | ė     | e           | Latin small letter e with dot above               |
     * | U+0118  | Ę     | E           | Latin capital letter E with ogonek                |
     * | U+0119  | ę     | e           | Latin small letter e with ogonek                  |
     * | U+011A  | Ě     | E           | Latin capital letter E with caron                 |
     * | U+011B  | ě     | e           | Latin small letter e with caron                   |
     * | U+011C  | Ĝ     | G           | Latin capital letter G with circumflex            |
     * | U+011D  | ĝ     | g           | Latin small letter g with circumflex              |
     * | U+011E  | Ğ     | G           | Latin capital letter G with breve                 |
     * | U+011F  | ğ     | g           | Latin small letter g with breve                   |
     * | U+0120  | Ġ     | G           | Latin capital letter G with dot above             |
     * | U+0121  | ġ     | g           | Latin small letter g with dot above               |
     * | U+0122  | Ģ     | G           | Latin capital letter G with cedilla               |
     * | U+0123  | ģ     | g           | Latin small letter g with cedilla                 |
     * | U+0124  | Ĥ     | H           | Latin capital letter H with circumflex            |
     * | U+0125  | ĥ     | h           | Latin small letter h with circumflex              |
     * | U+0126  | Ħ     | H           | Latin capital letter H with stroke                |
     * | U+0127  | ħ     | h           | Latin small letter h with stroke                  |
     * | U+0128  | Ĩ     | I           | Latin capital letter I with tilde                 |
     * | U+0129  | ĩ     | i           | Latin small letter i with tilde                   |
     * | U+012A  | Ī     | I           | Latin capital letter I with macron                |
     * | U+012B  | ī     | i           | Latin small letter i with macron                  |
     * | U+012C  | Ĭ     | I           | Latin capital letter I with breve                 |
     * | U+012D  | ĭ     | i           | Latin small letter i with breve                   |
     * | U+012E  | Į     | I           | Latin capital letter I with ogonek                |
     * | U+012F  | į     | i           | Latin small letter i with ogonek                  |
     * | U+0130  | İ     | I           | Latin capital letter I with dot above             |
     * | U+0131  | ı     | i           | Latin small letter dotless i                      |
     * | U+0132  | Ĳ     | IJ          | Latin capital ligature IJ                         |
     * | U+0133  | ĳ     | ij          | Latin small ligature ij                           |
     * | U+0134  | Ĵ     | J           | Latin capital letter J with circumflex            |
     * | U+0135  | ĵ     | j           | Latin small letter j with circumflex              |
     * | U+0136  | Ķ     | K           | Latin capital letter K with cedilla               |
     * | U+0137  | ķ     | k           | Latin small letter k with cedilla                 |
     * | U+0138  | ĸ     | k           | Latin small letter Kra                            |
     * | U+0139  | Ĺ     | L           | Latin capital letter L with acute                 |
     * | U+013A  | ĺ     | l           | Latin small letter l with acute                   |
     * | U+013B  | Ļ     | L           | Latin capital letter L with cedilla               |
     * | U+013C  | ļ     | l           | Latin small letter l with cedilla                 |
     * | U+013D  | Ľ     | L           | Latin capital letter L with caron                 |
     * | U+013E  | ľ     | l           | Latin small letter l with caron                   |
     * | U+013F  | Ŀ     | L           | Latin capital letter L with middle dot            |
     * | U+0140  | ŀ     | l           | Latin small letter l with middle dot              |
     * | U+0141  | Ł     | L           | Latin capital letter L with stroke                |
     * | U+0142  | ł     | l           | Latin small letter l with stroke                  |
     * | U+0143  | Ń     | N           | Latin capital letter N with acute                 |
     * | U+0144  | ń     | n           | Latin small letter N with acute                   |
     * | U+0145  | Ņ     | N           | Latin capital letter N with cedilla               |
     * | U+0146  | ņ     | n           | Latin small letter n with cedilla                 |
     * | U+0147  | Ň     | N           | Latin capital letter N with caron                 |
     * | U+0148  | ň     | n           | Latin small letter n with caron                   |
     * | U+0149  | ŉ     | n           | Latin small letter n preceded by apostrophe       |
     * | U+014A  | Ŋ     | N           | Latin capital letter Eng                          |
     * | U+014B  | ŋ     | n           | Latin small letter Eng                            |
     * | U+014C  | Ō     | O           | Latin capital letter O with macron                |
     * | U+014D  | ō     | o           | Latin small letter o with macron                  |
     * | U+014E  | Ŏ     | O           | Latin capital letter O with breve                 |
     * | U+014F  | ŏ     | o           | Latin small letter o with breve                   |
     * | U+0150  | Ő     | O           | Latin capital letter O with double acute          |
     * | U+0151  | ő     | o           | Latin small letter o with double acute            |
     * | U+0152  | Œ     | OE          | Latin capital ligature OE                         |
     * | U+0153  | œ     | oe          | Latin small ligature oe                           |
     * | U+0154  | Ŕ     | R           | Latin capital letter R with acute                 |
     * | U+0155  | ŕ     | r           | Latin small letter r with acute                   |
     * | U+0156  | Ŗ     | R           | Latin capital letter R with cedilla               |
     * | U+0157  | ŗ     | r           | Latin small letter r with cedilla                 |
     * | U+0158  | Ř     | R           | Latin capital letter R with caron                 |
     * | U+0159  | ř     | r           | Latin small letter r with caron                   |
     * | U+015A  | Ś     | S           | Latin capital letter S with acute                 |
     * | U+015B  | ś     | s           | Latin small letter s with acute                   |
     * | U+015C  | Ŝ     | S           | Latin capital letter S with circumflex            |
     * | U+015D  | ŝ     | s           | Latin small letter s with circumflex              |
     * | U+015E  | Ş     | S           | Latin capital letter S with cedilla               |
     * | U+015F  | ş     | s           | Latin small letter s with cedilla                 |
     * | U+0160  | Š     | S           | Latin capital letter S with caron                 |
     * | U+0161  | š     | s           | Latin small letter s with caron                   |
     * | U+0162  | Ţ     | T           | Latin capital letter T with cedilla               |
     * | U+0163  | ţ     | t           | Latin small letter t with cedilla                 |
     * | U+0164  | Ť     | T           | Latin capital letter T with caron                 |
     * | U+0165  | ť     | t           | Latin small letter t with caron                   |
     * | U+0166  | Ŧ     | T           | Latin capital letter T with stroke                |
     * | U+0167  | ŧ     | t           | Latin small letter t with stroke                  |
     * | U+0168  | Ũ     | U           | Latin capital letter U with tilde                 |
     * | U+0169  | ũ     | u           | Latin small letter u with tilde                   |
     * | U+016A  | Ū     | U           | Latin capital letter U with macron                |
     * | U+016B  | ū     | u           | Latin small letter u with macron                  |
     * | U+016C  | Ŭ     | U           | Latin capital letter U with breve                 |
     * | U+016D  | ŭ     | u           | Latin small letter u with breve                   |
     * | U+016E  | Ů     | U           | Latin capital letter U with ring above            |
     * | U+016F  | ů     | u           | Latin small letter u with ring above              |
     * | U+0170  | Ű     | U           | Latin capital letter U with double acute          |
     * | U+0171  | ű     | u           | Latin small letter u with double acute            |
     * | U+0172  | Ų     | U           | Latin capital letter U with ogonek                |
     * | U+0173  | ų     | u           | Latin small letter u with ogonek                  |
     * | U+0174  | Ŵ     | W           | Latin capital letter W with circumflex            |
     * | U+0175  | ŵ     | w           | Latin small letter w with circumflex              |
     * | U+0176  | Ŷ     | Y           | Latin capital letter Y with circumflex            |
     * | U+0177  | ŷ     | y           | Latin small letter y with circumflex              |
     * | U+0178  | Ÿ     | Y           | Latin capital letter Y with diaeresis             |
     * | U+0179  | Ź     | Z           | Latin capital letter Z with acute                 |
     * | U+017A  | ź     | z           | Latin small letter z with acute                   |
     * | U+017B  | Ż     | Z           | Latin capital letter Z with dot above             |
     * | U+017C  | ż     | z           | Latin small letter z with dot above               |
     * | U+017D  | Ž     | Z           | Latin capital letter Z with caron                 |
     * | U+017E  | ž     | z           | Latin small letter z with caron                   |
     * | U+017F  | ſ     | s           | Latin small letter long s                         |
     * | U+01A0  | Ơ     | O           | Latin capital letter O with horn                  |
     * | U+01A1  | ơ     | o           | Latin small letter o with horn                    |
     * | U+01AF  | Ư     | U           | Latin capital letter U with horn                  |
     * | U+01B0  | ư     | u           | Latin small letter u with horn                    |
     * | U+01CD  | Ǎ     | A           | Latin capital letter A with caron                 |
     * | U+01CE  | ǎ     | a           | Latin small letter a with caron                   |
     * | U+01CF  | Ǐ     | I           | Latin capital letter I with caron                 |
     * | U+01D0  | ǐ     | i           | Latin small letter i with caron                   |
     * | U+01D1  | Ǒ     | O           | Latin capital letter O with caron                 |
     * | U+01D2  | ǒ     | o           | Latin small letter o with caron                   |
     * | U+01D3  | Ǔ     | U           | Latin capital letter U with caron                 |
     * | U+01D4  | ǔ     | u           | Latin small letter u with caron                   |
     * | U+01D5  | Ǖ     | U           | Latin capital letter U with diaeresis and macron  |
     * | U+01D6  | ǖ     | u           | Latin small letter u with diaeresis and macron    |
     * | U+01D7  | Ǘ     | U           | Latin capital letter U with diaeresis and acute   |
     * | U+01D8  | ǘ     | u           | Latin small letter u with diaeresis and acute     |
     * | U+01D9  | Ǚ     | U           | Latin capital letter U with diaeresis and caron   |
     * | U+01DA  | ǚ     | u           | Latin small letter u with diaeresis and caron     |
     * | U+01DB  | Ǜ     | U           | Latin capital letter U with diaeresis and grave   |
     * | U+01DC  | ǜ     | u           | Latin small letter u with diaeresis and grave     |
     *
     * Decompositions for Latin Extended-B:
     *
     * |   Code   | Glyph | Replacement |                Description                |
     * | -------- | ----- | ----------- | ----------------------------------------- |
     * | U+0218   | Ș     | S           | Latin capital letter S with comma below   |
     * | U+0219   | ș     | s           | Latin small letter s with comma below     |
     * | U+021A   | Ț     | T           | Latin capital letter T with comma below   |
     * | U+021B   | ț     | t           | Latin small letter t with comma below     |
     *
     * Vowels with diacritic (Chinese, Hanyu Pinyin):
     *
     * |   Code   | Glyph | Replacement |                      Description                      |
     * | -------- | ----- | ----------- | ----------------------------------------------------- |
     * | U+0251   | ɑ     | a           | Latin small letter alpha                              |
     * | U+1EA0   | Ạ     | A           | Latin capital letter A with dot below                 |
     * | U+1EA1   | ạ     | a           | Latin small letter a with dot below                   |
     * | U+1EA2   | Ả     | A           | Latin capital letter A with hook above                |
     * | U+1EA3   | ả     | a           | Latin small letter a with hook above                  |
     * | U+1EA4   | Ấ     | A           | Latin capital letter A with circumflex and acute      |
     * | U+1EA5   | ấ     | a           | Latin small letter a with circumflex and acute        |
     * | U+1EA6   | Ầ     | A           | Latin capital letter A with circumflex and grave      |
     * | U+1EA7   | ầ     | a           | Latin small letter a with circumflex and grave        |
     * | U+1EA8   | Ẩ     | A           | Latin capital letter A with circumflex and hook above |
     * | U+1EA9   | ẩ     | a           | Latin small letter a with circumflex and hook above   |
     * | U+1EAA   | Ẫ     | A           | Latin capital letter A with circumflex and tilde      |
     * | U+1EAB   | ẫ     | a           | Latin small letter a with circumflex and tilde        |
     * | U+1EA6   | Ậ     | A           | Latin capital letter A with circumflex and dot below  |
     * | U+1EAD   | ậ     | a           | Latin small letter a with circumflex and dot below    |
     * | U+1EAE   | Ắ     | A           | Latin capital letter A with breve and acute           |
     * | U+1EAF   | ắ     | a           | Latin small letter a with breve and acute             |
     * | U+1EB0   | Ằ     | A           | Latin capital letter A with breve and grave           |
     * | U+1EB1   | ằ     | a           | Latin small letter a with breve and grave             |
     * | U+1EB2   | Ẳ     | A           | Latin capital letter A with breve and hook above      |
     * | U+1EB3   | ẳ     | a           | Latin small letter a with breve and hook above        |
     * | U+1EB4   | Ẵ     | A           | Latin capital letter A with breve and tilde           |
     * | U+1EB5   | ẵ     | a           | Latin small letter a with breve and tilde             |
     * | U+1EB6   | Ặ     | A           | Latin capital letter A with breve and dot below       |
     * | U+1EB7   | ặ     | a           | Latin small letter a with breve and dot below         |
     * | U+1EB8   | Ẹ     | E           | Latin capital letter E with dot below                 |
     * | U+1EB9   | ẹ     | e           | Latin small letter e with dot below                   |
     * | U+1EBA   | Ẻ     | E           | Latin capital letter E with hook above                |
     * | U+1EBB   | ẻ     | e           | Latin small letter e with hook above                  |
     * | U+1EBC   | Ẽ     | E           | Latin capital letter E with tilde                     |
     * | U+1EBD   | ẽ     | e           | Latin small letter e with tilde                       |
     * | U+1EBE   | Ế     | E           | Latin capital letter E with circumflex and acute      |
     * | U+1EBF   | ế     | e           | Latin small letter e with circumflex and acute        |
     * | U+1EC0   | Ề     | E           | Latin capital letter E with circumflex and grave      |
     * | U+1EC1   | ề     | e           | Latin small letter e with circumflex and grave        |
     * | U+1EC2   | Ể     | E           | Latin capital letter E with circumflex and hook above |
     * | U+1EC3   | ể     | e           | Latin small letter e with circumflex and hook above   |
     * | U+1EC4   | Ễ     | E           | Latin capital letter E with circumflex and tilde      |
     * | U+1EC5   | ễ     | e           | Latin small letter e with circumflex and tilde        |
     * | U+1EC6   | Ệ     | E           | Latin capital letter E with circumflex and dot below  |
     * | U+1EC7   | ệ     | e           | Latin small letter e with circumflex and dot below    |
     * | U+1EC8   | Ỉ     | I           | Latin capital letter I with hook above                |
     * | U+1EC9   | ỉ     | i           | Latin small letter i with hook above                  |
     * | U+1ECA   | Ị     | I           | Latin capital letter I with dot below                 |
     * | U+1ECB   | ị     | i           | Latin small letter i with dot below                   |
     * | U+1ECC   | Ọ     | O           | Latin capital letter O with dot below                 |
     * | U+1ECD   | ọ     | o           | Latin small letter o with dot below                   |
     * | U+1ECE   | Ỏ     | O           | Latin capital letter O with hook above                |
     * | U+1ECF   | ỏ     | o           | Latin small letter o with hook above                  |
     * | U+1ED0   | Ố     | O           | Latin capital letter O with circumflex and acute      |
     * | U+1ED1   | ố     | o           | Latin small letter o with circumflex and acute        |
     * | U+1ED2   | Ồ     | O           | Latin capital letter O with circumflex and grave      |
     * | U+1ED3   | ồ     | o           | Latin small letter o with circumflex and grave        |
     * | U+1ED4   | Ổ     | O           | Latin capital letter O with circumflex and hook above |
     * | U+1ED5   | ổ     | o           | Latin small letter o with circumflex and hook above   |
     * | U+1ED6   | Ỗ     | O           | Latin capital letter O with circumflex and tilde      |
     * | U+1ED7   | ỗ     | o           | Latin small letter o with circumflex and tilde        |
     * | U+1ED8   | Ộ     | O           | Latin capital letter O with circumflex and dot below  |
     * | U+1ED9   | ộ     | o           | Latin small letter o with circumflex and dot below    |
     * | U+1EDA   | Ớ     | O           | Latin capital letter O with horn and acute            |
     * | U+1EDB   | ớ     | o           | Latin small letter o with horn and acute              |
     * | U+1EDC   | Ờ     | O           | Latin capital letter O with horn and grave            |
     * | U+1EDD   | ờ     | o           | Latin small letter o with horn and grave              |
     * | U+1EDE   | Ở     | O           | Latin capital letter O with horn and hook above       |
     * | U+1EDF   | ở     | o           | Latin small letter o with horn and hook above         |
     * | U+1EE0   | Ỡ     | O           | Latin capital letter O with horn and tilde            |
     * | U+1EE1   | ỡ     | o           | Latin small letter o with horn and tilde              |
     * | U+1EE2   | Ợ     | O           | Latin capital letter O with horn and dot below        |
     * | U+1EE3   | ợ     | o           | Latin small letter o with horn and dot below          |
     * | U+1EE4   | Ụ     | U           | Latin capital letter U with dot below                 |
     * | U+1EE5   | ụ     | u           | Latin small letter u with dot below                   |
     * | U+1EE6   | Ủ     | U           | Latin capital letter U with hook above                |
     * | U+1EE7   | ủ     | u           | Latin small letter u with hook above                  |
     * | U+1EE8   | Ứ     | U           | Latin capital letter U with horn and acute            |
     * | U+1EE9   | ứ     | u           | Latin small letter u with horn and acute              |
     * | U+1EEA   | Ừ     | U           | Latin capital letter U with horn and grave            |
     * | U+1EEB   | ừ     | u           | Latin small letter u with horn and grave              |
     * | U+1EEC   | Ử     | U           | Latin capital letter U with horn and hook above       |
     * | U+1EED   | ử     | u           | Latin small letter u with horn and hook above         |
     * | U+1EEE   | Ữ     | U           | Latin capital letter U with horn and tilde            |
     * | U+1EEF   | ữ     | u           | Latin small letter u with horn and tilde              |
     * | U+1EF0   | Ự     | U           | Latin capital letter U with horn and dot below        |
     * | U+1EF1   | ự     | u           | Latin small letter u with horn and dot below          |
     * | U+1EF2   | Ỳ     | Y           | Latin capital letter Y with grave                     |
     * | U+1EF3   | ỳ     | y           | Latin small letter y with grave                       |
     * | U+1EF4   | Ỵ     | Y           | Latin capital letter Y with dot below                 |
     * | U+1EF5   | ỵ     | y           | Latin small letter y with dot below                   |
     * | U+1EF6   | Ỷ     | Y           | Latin capital letter Y with hook above                |
     * | U+1EF7   | ỷ     | y           | Latin small letter y with hook above                  |
     * | U+1EF8   | Ỹ     | Y           | Latin capital letter Y with tilde                     |
     * | U+1EF9   | ỹ     | y           | Latin small letter y with tilde                       |
     *
     * German (`de_DE`), German formal (`de_DE_formal`), German (Switzerland) formal (`de_CH`),
     * German (Switzerland) informal (`de_CH_informal`), and German (Austria) (`de_AT`) locales:
     *
     * |   Code   | Glyph | Replacement |               Description               |
     * | -------- | ----- | ----------- | --------------------------------------- |
     * | U+00C4   | Ä     | Ae          | Latin capital letter A with diaeresis   |
     * | U+00E4   | ä     | ae          | Latin small letter a with diaeresis     |
     * | U+00D6   | Ö     | Oe          | Latin capital letter O with diaeresis   |
     * | U+00F6   | ö     | oe          | Latin small letter o with diaeresis     |
     * | U+00DC   | Ü     | Ue          | Latin capital letter U with diaeresis   |
     * | U+00FC   | ü     | ue          | Latin small letter u with diaeresis     |
     * | U+00DF   | ß     | ss          | Latin small letter sharp s              |
     *
     * Danish (`da_DK`) locale:
     *
     * |   Code   | Glyph | Replacement |               Description               |
     * | -------- | ----- | ----------- | --------------------------------------- |
     * | U+00C6   | Æ     | Ae          | Latin capital letter AE                 |
     * | U+00E6   | æ     | ae          | Latin small letter ae                   |
     * | U+00D8   | Ø     | Oe          | Latin capital letter O with stroke      |
     * | U+00F8   | ø     | oe          | Latin small letter o with stroke        |
     * | U+00C5   | Å     | Aa          | Latin capital letter A with ring above  |
     * | U+00E5   | å     | aa          | Latin small letter a with ring above    |
     *
     * Catalan (`ca`) locale:
     *
     * |   Code   | Glyph | Replacement |               Description               |
     * | -------- | ----- | ----------- | --------------------------------------- |
     * | U+00B7   | l·l   | ll          | Flown dot (between two Ls)              |
     *
     * Serbian (`sr_RS`) and Bosnian (`bs_BA`) locales:
     *
     * |   Code   | Glyph | Replacement |               Description               |
     * | -------- | ----- | ----------- | --------------------------------------- |
     * | U+0110   | Đ     | DJ          | Latin capital letter D with stroke      |
     * | U+0111   | đ     | dj          | Latin small letter d with stroke        |
     *
     * @since 1.2.1
     * @since 4.6.0 Added locale support for `de_CH`, `de_CH_informal`, and `ca`.
     * @since 4.7.0 Added locale support for `sr_RS`.
     * @since 4.8.0 Added locale support for `bs_BA`.
     * @since 5.7.0 Added locale support for `de_AT`.
     *
     * @param string $string Text that might have accent characters
     * @return string Filtered string with replaced "nice" characters.
     */
    public static function remove_accents( $string ) {
        if ( ! preg_match( '/[\x80-\xff]/', $string ) ) {
            return $string;
        }

        if ( self::seems_utf8( $string ) ) {
            $chars = array(
                // Decompositions for Latin-1 Supplement.
                'ª' => 'a',
                'º' => 'o',
                'À' => 'A',
                'Á' => 'A',
                'Â' => 'A',
                'Ã' => 'A',
                'Ä' => 'A',
                'Å' => 'A',
                'Æ' => 'AE',
                'Ç' => 'C',
                'È' => 'E',
                'É' => 'E',
                'Ê' => 'E',
                'Ë' => 'E',
                'Ì' => 'I',
                'Í' => 'I',
                'Î' => 'I',
                'Ï' => 'I',
                'Ð' => 'D',
                'Ñ' => 'N',
                'Ò' => 'O',
                'Ó' => 'O',
                'Ô' => 'O',
                'Õ' => 'O',
                'Ö' => 'O',
                'Ù' => 'U',
                'Ú' => 'U',
                'Û' => 'U',
                'Ü' => 'U',
                'Ý' => 'Y',
                'Þ' => 'TH',
                'ß' => 's',
                'à' => 'a',
                'á' => 'a',
                'â' => 'a',
                'ã' => 'a',
                'ä' => 'a',
                'å' => 'a',
                'æ' => 'ae',
                'ç' => 'c',
                'è' => 'e',
                'é' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'ì' => 'i',
                'í' => 'i',
                'î' => 'i',
                'ï' => 'i',
                'ð' => 'd',
                'ñ' => 'n',
                'ò' => 'o',
                'ó' => 'o',
                'ô' => 'o',
                'õ' => 'o',
                'ö' => 'o',
                'ø' => 'o',
                'ù' => 'u',
                'ú' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'ý' => 'y',
                'þ' => 'th',
                'ÿ' => 'y',
                'Ø' => 'O',
                // Decompositions for Latin Extended-A.
                'Ā' => 'A',
                'ā' => 'a',
                'Ă' => 'A',
                'ă' => 'a',
                'Ą' => 'A',
                'ą' => 'a',
                'Ć' => 'C',
                'ć' => 'c',
                'Ĉ' => 'C',
                'ĉ' => 'c',
                'Ċ' => 'C',
                'ċ' => 'c',
                'Č' => 'C',
                'č' => 'c',
                'Ď' => 'D',
                'ď' => 'd',
                'Đ' => 'D',
                'đ' => 'd',
                'Ē' => 'E',
                'ē' => 'e',
                'Ĕ' => 'E',
                'ĕ' => 'e',
                'Ė' => 'E',
                'ė' => 'e',
                'Ę' => 'E',
                'ę' => 'e',
                'Ě' => 'E',
                'ě' => 'e',
                'Ĝ' => 'G',
                'ĝ' => 'g',
                'Ğ' => 'G',
                'ğ' => 'g',
                'Ġ' => 'G',
                'ġ' => 'g',
                'Ģ' => 'G',
                'ģ' => 'g',
                'Ĥ' => 'H',
                'ĥ' => 'h',
                'Ħ' => 'H',
                'ħ' => 'h',
                'Ĩ' => 'I',
                'ĩ' => 'i',
                'Ī' => 'I',
                'ī' => 'i',
                'Ĭ' => 'I',
                'ĭ' => 'i',
                'Į' => 'I',
                'į' => 'i',
                'İ' => 'I',
                'ı' => 'i',
                'Ĳ' => 'IJ',
                'ĳ' => 'ij',
                'Ĵ' => 'J',
                'ĵ' => 'j',
                'Ķ' => 'K',
                'ķ' => 'k',
                'ĸ' => 'k',
                'Ĺ' => 'L',
                'ĺ' => 'l',
                'Ļ' => 'L',
                'ļ' => 'l',
                'Ľ' => 'L',
                'ľ' => 'l',
                'Ŀ' => 'L',
                'ŀ' => 'l',
                'Ł' => 'L',
                'ł' => 'l',
                'Ń' => 'N',
                'ń' => 'n',
                'Ņ' => 'N',
                'ņ' => 'n',
                'Ň' => 'N',
                'ň' => 'n',
                'ŉ' => 'n',
                'Ŋ' => 'N',
                'ŋ' => 'n',
                'Ō' => 'O',
                'ō' => 'o',
                'Ŏ' => 'O',
                'ŏ' => 'o',
                'Ő' => 'O',
                'ő' => 'o',
                'Œ' => 'OE',
                'œ' => 'oe',
                'Ŕ' => 'R',
                'ŕ' => 'r',
                'Ŗ' => 'R',
                'ŗ' => 'r',
                'Ř' => 'R',
                'ř' => 'r',
                'Ś' => 'S',
                'ś' => 's',
                'Ŝ' => 'S',
                'ŝ' => 's',
                'Ş' => 'S',
                'ş' => 's',
                'Š' => 'S',
                'š' => 's',
                'Ţ' => 'T',
                'ţ' => 't',
                'Ť' => 'T',
                'ť' => 't',
                'Ŧ' => 'T',
                'ŧ' => 't',
                'Ũ' => 'U',
                'ũ' => 'u',
                'Ū' => 'U',
                'ū' => 'u',
                'Ŭ' => 'U',
                'ŭ' => 'u',
                'Ů' => 'U',
                'ů' => 'u',
                'Ű' => 'U',
                'ű' => 'u',
                'Ų' => 'U',
                'ų' => 'u',
                'Ŵ' => 'W',
                'ŵ' => 'w',
                'Ŷ' => 'Y',
                'ŷ' => 'y',
                'Ÿ' => 'Y',
                'Ź' => 'Z',
                'ź' => 'z',
                'Ż' => 'Z',
                'ż' => 'z',
                'Ž' => 'Z',
                'ž' => 'z',
                'ſ' => 's',
                // Decompositions for Latin Extended-B.
                'Ș' => 'S',
                'ș' => 's',
                'Ț' => 'T',
                'ț' => 't',
                // Euro sign.
                '€' => 'E',
                // GBP (Pound) sign.
                '£' => '',
                // Vowels with diacritic (Vietnamese).
                // Unmarked.
                'Ơ' => 'O',
                'ơ' => 'o',
                'Ư' => 'U',
                'ư' => 'u',
                // Grave accent.
                'Ầ' => 'A',
                'ầ' => 'a',
                'Ằ' => 'A',
                'ằ' => 'a',
                'Ề' => 'E',
                'ề' => 'e',
                'Ồ' => 'O',
                'ồ' => 'o',
                'Ờ' => 'O',
                'ờ' => 'o',
                'Ừ' => 'U',
                'ừ' => 'u',
                'Ỳ' => 'Y',
                'ỳ' => 'y',
                // Hook.
                'Ả' => 'A',
                'ả' => 'a',
                'Ẩ' => 'A',
                'ẩ' => 'a',
                'Ẳ' => 'A',
                'ẳ' => 'a',
                'Ẻ' => 'E',
                'ẻ' => 'e',
                'Ể' => 'E',
                'ể' => 'e',
                'Ỉ' => 'I',
                'ỉ' => 'i',
                'Ỏ' => 'O',
                'ỏ' => 'o',
                'Ổ' => 'O',
                'ổ' => 'o',
                'Ở' => 'O',
                'ở' => 'o',
                'Ủ' => 'U',
                'ủ' => 'u',
                'Ử' => 'U',
                'ử' => 'u',
                'Ỷ' => 'Y',
                'ỷ' => 'y',
                // Tilde.
                'Ẫ' => 'A',
                'ẫ' => 'a',
                'Ẵ' => 'A',
                'ẵ' => 'a',
                'Ẽ' => 'E',
                'ẽ' => 'e',
                'Ễ' => 'E',
                'ễ' => 'e',
                'Ỗ' => 'O',
                'ỗ' => 'o',
                'Ỡ' => 'O',
                'ỡ' => 'o',
                'Ữ' => 'U',
                'ữ' => 'u',
                'Ỹ' => 'Y',
                'ỹ' => 'y',
                // Acute accent.
                'Ấ' => 'A',
                'ấ' => 'a',
                'Ắ' => 'A',
                'ắ' => 'a',
                'Ế' => 'E',
                'ế' => 'e',
                'Ố' => 'O',
                'ố' => 'o',
                'Ớ' => 'O',
                'ớ' => 'o',
                'Ứ' => 'U',
                'ứ' => 'u',
                // Dot below.
                'Ạ' => 'A',
                'ạ' => 'a',
                'Ậ' => 'A',
                'ậ' => 'a',
                'Ặ' => 'A',
                'ặ' => 'a',
                'Ẹ' => 'E',
                'ẹ' => 'e',
                'Ệ' => 'E',
                'ệ' => 'e',
                'Ị' => 'I',
                'ị' => 'i',
                'Ọ' => 'O',
                'ọ' => 'o',
                'Ộ' => 'O',
                'ộ' => 'o',
                'Ợ' => 'O',
                'ợ' => 'o',
                'Ụ' => 'U',
                'ụ' => 'u',
                'Ự' => 'U',
                'ự' => 'u',
                'Ỵ' => 'Y',
                'ỵ' => 'y',
                // Vowels with diacritic (Chinese, Hanyu Pinyin).
                'ɑ' => 'a',
                // Macron.
                'Ǖ' => 'U',
                'ǖ' => 'u',
                // Acute accent.
                'Ǘ' => 'U',
                'ǘ' => 'u',
                // Caron.
                'Ǎ' => 'A',
                'ǎ' => 'a',
                'Ǐ' => 'I',
                'ǐ' => 'i',
                'Ǒ' => 'O',
                'ǒ' => 'o',
                'Ǔ' => 'U',
                'ǔ' => 'u',
                'Ǚ' => 'U',
                'ǚ' => 'u',
                // Grave accent.
                'Ǜ' => 'U',
                'ǜ' => 'u',
            );

            // Used for locale-specific rules.
            $locale = str_replace('.UTF-8', '', moodle_getlocale());

            if ( in_array( $locale, array( 'de_DE', 'de_DE_formal', 'de_CH', 'de_CH_informal', 'de_AT' ), true ) ) {
                $chars['Ä'] = 'Ae';
                $chars['ä'] = 'ae';
                $chars['Ö'] = 'Oe';
                $chars['ö'] = 'oe';
                $chars['Ü'] = 'Ue';
                $chars['ü'] = 'ue';
                $chars['ß'] = 'ss';
            } else if ( 'da_DK' === $locale ) {
                $chars['Æ'] = 'Ae';
                $chars['æ'] = 'ae';
                $chars['Ø'] = 'Oe';
                $chars['ø'] = 'oe';
                $chars['Å'] = 'Aa';
                $chars['å'] = 'aa';
            } else if ( 'ca' === $locale ) {
                $chars['l·l'] = 'll';
            } else if ( 'sr_RS' === $locale || 'bs_BA' === $locale ) {
                $chars['Đ'] = 'DJ';
                $chars['đ'] = 'dj';
            }

            $string = strtr( $string, $chars );
        } else {
            $chars = array();
            // Assume ISO-8859-1 if not UTF-8.
            $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
                . "\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
                . "\xc3\xc4\xc5\xc7\xc8\xc9\xca"
                . "\xcb\xcc\xcd\xce\xcf\xd1\xd2"
                . "\xd3\xd4\xd5\xd6\xd8\xd9\xda"
                . "\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
                . "\xe4\xe5\xe7\xe8\xe9\xea\xeb"
                . "\xec\xed\xee\xef\xf1\xf2\xf3"
                . "\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
                . "\xfc\xfd\xff";

            $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

            $string              = strtr( $string, $chars['in'], $chars['out'] );
            $doublechars        = array();
            $doublechars['in']  = array( "\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe" );
            $doublechars['out'] = array( 'OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th' );
            $string              = str_replace( $doublechars['in'], $doublechars['out'], $string );
        }

        return $string;
    }

    /**
     * Generate a correct base64 encoded header to be used in MIME mail messages.
     * This function seems to be 100% compliant with RFC1342. Credits go to:
     * paravoid (http://www.php.net/manual/en/function.mb-encode-mimeheader.php#60283).
     *
     * @param string $text input string
     * @param string $charset encoding of the text
     * @return string base64 encoded header
     */
    public static function encode_mimeheader($text, $charset='utf-8') {
        if (empty($text)) {
            return (string)$text;
        }
        // Normalize charset
        $charset = self::parse_charset($charset);
        // If the text is pure ASCII, we don't need to encode it
        if (self::convert($text, $charset, 'ascii') == $text) {
            return $text;
        }
        // Although RFC says that line feed should be \r\n, it seems that
        // some mailers double convert \r, so we are going to use \n alone
        $linefeed="\n";
        // Define start and end of every chunk
        $start = "=?$charset?B?";
        $end = "?=";
        // Accumulate results
        $encoded = '';
        // Max line length is 75 (including start and end)
        $length = 75 - strlen($start) - strlen($end);
        // Multi-byte ratio
        $multilength = self::strlen($text, $charset);
        // Detect if strlen and friends supported
        if ($multilength === false) {
            if ($charset == 'GB18030' or $charset == 'gb18030') {
                while (strlen($text)) {
                    // try to encode first 22 chars - we expect most chars are two bytes long
                    if (preg_match('/^(([\x00-\x7f])|([\x81-\xfe][\x40-\x7e])|([\x81-\xfe][\x80-\xfe])|([\x81-\xfe][\x30-\x39]..)){1,22}/m', $text, $matches)) {
                        $chunk = $matches[0];
                        $encchunk = base64_encode($chunk);
                        if (strlen($encchunk) > $length) {
                            // find first 11 chars - each char in 4 bytes - worst case scenario
                            preg_match('/^(([\x00-\x7f])|([\x81-\xfe][\x40-\x7e])|([\x81-\xfe][\x80-\xfe])|([\x81-\xfe][\x30-\x39]..)){1,11}/m', $text, $matches);
                            $chunk = $matches[0];
                            $encchunk = base64_encode($chunk);
                        }
                        $text = substr($text, strlen($chunk));
                        $encoded .= ' '.$start.$encchunk.$end.$linefeed;
                    } else {
                        break;
                    }
                }
                $encoded = trim($encoded);
                return $encoded;
            } else {
                return false;
            }
        }
        $ratio = $multilength / strlen($text);
        // Base64 ratio
        $magic = $avglength = floor(3 * $length * $ratio / 4);
        // basic infinite loop protection
        $maxiterations = strlen($text)*2;
        $iteration = 0;
        // Iterate over the string in magic chunks
        for ($i=0; $i <= $multilength; $i+=$magic) {
            if ($iteration++ > $maxiterations) {
                return false; // probably infinite loop
            }
            $magic = $avglength;
            $offset = 0;
            // Ensure the chunk fits in length, reducing magic if necessary
            do {
                $magic -= $offset;
                $chunk = self::substr($text, $i, $magic, $charset);
                $chunk = base64_encode($chunk);
                $offset++;
            } while (strlen($chunk) > $length);
            // This chunk doesn't break any multi-byte char. Use it.
            if ($chunk)
                $encoded .= ' '.$start.$chunk.$end.$linefeed;
        }
        // Strip the first space and the last linefeed
        $encoded = substr($encoded, 1, -strlen($linefeed));

        return $encoded;
    }

    /**
     * Returns HTML entity transliteration table.
     * @return array with (html entity => utf-8) elements
     */
    protected static function get_entities_table() {
        static $trans_tbl = null;

        // Generate/create $trans_tbl
        if (!isset($trans_tbl)) {
            if (version_compare(phpversion(), '5.3.4') < 0) {
                $trans_tbl = array();
                foreach (get_html_translation_table(HTML_ENTITIES) as $val=>$key) {
                    $trans_tbl[$key] = self::convert($val, 'ISO-8859-1', 'utf-8');
                }

            } else if (version_compare(phpversion(), '5.4.0') < 0) {
                $trans_tbl = get_html_translation_table(HTML_ENTITIES, ENT_COMPAT, 'UTF-8');
                $trans_tbl = array_flip($trans_tbl);

            } else {
                $trans_tbl = get_html_translation_table(HTML_ENTITIES, ENT_COMPAT | ENT_HTML401, 'UTF-8');
                $trans_tbl = array_flip($trans_tbl);
            }
        }

        return $trans_tbl;
    }

    /**
     * Converts all the numeric entities &#nnnn; or &#xnnn; to UTF-8
     * Original from laurynas dot butkus at gmail at:
     * http://php.net/manual/en/function.html-entity-decode.php#75153
     * with some custom mods to provide more functionality
     *
     * @param string $str input string
     * @param boolean $htmlent convert also html entities (defaults to true)
     * @return string encoded UTF-8 string
     */
    public static function entities_to_utf8($str, $htmlent=true) {
        static $callback1 = null ;
        static $callback2 = null ;

        if (!$callback1 or !$callback2) {
            $callback1 = function($matches) {
                return core_text::code2utf8(hexdec($matches[1]));
            };
            $callback2 = function($matches) {
                return core_text::code2utf8($matches[1]);
            };
        }

        $result = (string)$str;
        $result = preg_replace_callback('/&#x([0-9a-f]+);/i', $callback1, $result);
        $result = preg_replace_callback('/&#([0-9]+);/', $callback2, $result);

        // Replace literal entities (if desired)
        if ($htmlent) {
            $trans_tbl = self::get_entities_table();
            // It should be safe to search for ascii strings and replace them with utf-8 here.
            $result = strtr($result, $trans_tbl);
        }
        // Return utf8-ised string
        return $result;
    }

    /**
     * Converts all Unicode chars > 127 to numeric entities &#nnnn; or &#xnnn;.
     *
     * @param string $str input string
     * @param boolean $dec output decadic only number entities
     * @param boolean $nonnum remove all non-numeric entities
     * @return string converted string
     */
    public static function utf8_to_entities($str, $dec=false, $nonnum=false) {
        static $callback = null ;

        if ($nonnum) {
            $str = self::entities_to_utf8($str, true);
        }

        $result = mb_strtolower(mb_encode_numericentity($str, [0xa0, 0xffff, 0, 0xffff], 'UTF-8', true));

        // We cannot use the decimal equivalent of the above call due to the unit test and our allowance for
        // entities to be entered within the provided $str. Refer to the correspond unit test for examples.
        if ($dec) {
            if (!$callback) {
                $callback = function($matches) {
                    return '&#' . hexdec($matches[1]) . ';';
                };
            }
            $result = preg_replace_callback('/&#x([0-9a-f]+);/i', $callback, $result);
        }

        return $result;
    }

    /**
     * Removes the BOM from unicode string {@link http://unicode.org/faq/utf_bom.html}
     *
     * @param string $str input string
     * @return string
     */
    public static function trim_utf8_bom($str) {
        $bom = "\xef\xbb\xbf";
        if (strpos($str, $bom) === 0) {
            return substr($str, strlen($bom));
        }
        return $str;
    }

    /**
     * There are a number of Unicode non-characters including the byte-order mark (which may appear
     * multiple times in a string) and also other ranges. These can cause problems for some
     * processing.
     *
     * This function removes the characters using string replace, so that the rest of the string
     * remains unchanged.
     *
     * @param string $value Input string
     * @return string Cleaned string value
     * @since Moodle 3.5
     */
    public static function remove_unicode_non_characters($value) {
        // Set up list of all Unicode non-characters for fast replacing.
        if (!self::$noncharacters) {
            self::$noncharacters = [];
            // This list of characters is based on the Unicode standard. It includes the last two
            // characters of each code planes 0-16 inclusive...
            for ($plane = 0; $plane <= 16; $plane++) {
                $base = ($plane === 0 ? '' : dechex($plane));
                self::$noncharacters[] = html_entity_decode('&#x' . $base . 'fffe;');
                self::$noncharacters[] = html_entity_decode('&#x' . $base . 'ffff;');
            }
            // ...And the character range U+FDD0 to U+FDEF.
            for ($char = 0xfdd0; $char <= 0xfdef; $char++) {
                self::$noncharacters[] = html_entity_decode('&#x' . dechex($char) . ';');
            }
        }

        // Do character replacement.
        return str_replace(self::$noncharacters, '', $value);
    }

    /**
     * Returns encoding options for select boxes, utf-8 and platform encoding first
     *
     * @return array encodings
     */
    public static function get_encodings() {
        $encodings = array();
        $encodings['UTF-8'] = 'UTF-8';
        $winenc = strtoupper(get_string('localewincharset', 'langconfig'));
        if ($winenc != '') {
            $encodings[$winenc] = $winenc;
        }
        $nixenc = strtoupper(get_string('oldcharset', 'langconfig'));
        $encodings[$nixenc] = $nixenc;

        $listedencodings = mb_list_encodings();
        foreach ($listedencodings as $enc) {
            $enc = strtoupper($enc);
            $encodings[$enc] = $enc;
        }
        return $encodings;
    }

    /**
     * Returns the utf8 string corresponding to the unicode value
     * (from php.net, courtesy - romans@void.lv)
     *
     * @param  int    $num one unicode value
     * @return string the UTF-8 char corresponding to the unicode value
     */
    public static function code2utf8($num) {
        if ($num < 128) {
            return chr($num);
        }
        if ($num < 2048) {
            return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
        }
        if ($num < 65536) {
            return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        }
        if ($num < 2097152) {
            return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
        }
        return '';
    }

    /**
     * Returns the code of the given UTF-8 character
     *
     * @param  string $utf8char one UTF-8 character
     * @return int    the code of the given character
     */
    public static function utf8ord($utf8char) {
        if ($utf8char == '') {
            return 0;
        }
        $ord0 = ord($utf8char[0]);
        if ($ord0 >= 0 && $ord0 <= 127) {
            return $ord0;
        }
        $ord1 = ord($utf8char[1]);
        if ($ord0 >= 192 && $ord0 <= 223) {
            return ($ord0 - 192) * 64 + ($ord1 - 128);
        }
        $ord2 = ord($utf8char[2]);
        if ($ord0 >= 224 && $ord0 <= 239) {
            return ($ord0 - 224) * 4096 + ($ord1 - 128) * 64 + ($ord2 - 128);
        }
        $ord3 = ord($utf8char[3]);
        if ($ord0 >= 240 && $ord0 <= 247) {
            return ($ord0 - 240) * 262144 + ($ord1 - 128 )* 4096 + ($ord2 - 128) * 64 + ($ord3 - 128);
        }
        return false;
    }

    /**
     * Makes first letter of each word capital - words must be separated by spaces.
     * Use with care, this function does not work properly in many locales!!!
     *
     * @param string $text input string
     * @return string
     */
    public static function strtotitle($text) {
        if (empty($text)) {
            return $text;
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        }

        $text = self::strtolower($text);
        $words = explode(' ', $text);
        foreach ($words as $i=>$word) {
            $length = self::strlen($word);
            if (!$length) {
                continue;

            } else if ($length == 1) {
                $words[$i] = self::strtoupper($word);

            } else {
                $letter = self::substr($word, 0, 1);
                $letter = self::strtoupper($letter);
                $rest   = self::substr($word, 1);
                $words[$i] = $letter.$rest;
            }
        }
        return implode(' ', $words);
    }
}
