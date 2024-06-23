<?php
// This file is part of Moodle - http://moodle.org/.
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
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Description
 * @author   "Sebastián Grignoli" <grignoli@gmail.com>
 * @copyright  SybonTeam, sybon.org
 * @package  mod_bacs
 * @link     https://github.com/neitanod/forceutf8
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

/**
 * Class Encoding
 * @package mod_bacs
 */
class Encoding {
    /**
     *
     */
    const ICONV_TRANSLIT = "TRANSLIT";
    /**
     *
     */
    const ICONV_IGNORE = "IGNORE";
    /**
     *
     */
    const WITHOUT_ICONV = "";

    /**
     * @var mixed
     */
    protected static $win1252toutf8 = [
        128 => "\xe2\x82\xac",

        130 => "\xe2\x80\x9a",
        131 => "\xc6\x92",
        132 => "\xe2\x80\x9e",
        133 => "\xe2\x80\xa6",
        134 => "\xe2\x80\xa0",
        135 => "\xe2\x80\xa1",
        136 => "\xcb\x86",
        137 => "\xe2\x80\xb0",
        138 => "\xc5\xa0",
        139 => "\xe2\x80\xb9",
        140 => "\xc5\x92",

        142 => "\xc5\xbd",


        145 => "\xe2\x80\x98",
        146 => "\xe2\x80\x99",
        147 => "\xe2\x80\x9c",
        148 => "\xe2\x80\x9d",
        149 => "\xe2\x80\xa2",
        150 => "\xe2\x80\x93",
        151 => "\xe2\x80\x94",
        152 => "\xcb\x9c",
        153 => "\xe2\x84\xa2",
        154 => "\xc5\xa1",
        155 => "\xe2\x80\xba",
        156 => "\xc5\x93",

        158 => "\xc5\xbe",
        159 => "\xc5\xb8",
    ];

    /**
     * @var mixed
     */
    protected static $brokenutf8toutf8 = [
        "\xc2\x80" => "\xe2\x82\xac",

        "\xc2\x82" => "\xe2\x80\x9a",
        "\xc2\x83" => "\xc6\x92",
        "\xc2\x84" => "\xe2\x80\x9e",
        "\xc2\x85" => "\xe2\x80\xa6",
        "\xc2\x86" => "\xe2\x80\xa0",
        "\xc2\x87" => "\xe2\x80\xa1",
        "\xc2\x88" => "\xcb\x86",
        "\xc2\x89" => "\xe2\x80\xb0",
        "\xc2\x8a" => "\xc5\xa0",
        "\xc2\x8b" => "\xe2\x80\xb9",
        "\xc2\x8c" => "\xc5\x92",

        "\xc2\x8e" => "\xc5\xbd",


        "\xc2\x91" => "\xe2\x80\x98",
        "\xc2\x92" => "\xe2\x80\x99",
        "\xc2\x93" => "\xe2\x80\x9c",
        "\xc2\x94" => "\xe2\x80\x9d",
        "\xc2\x95" => "\xe2\x80\xa2",
        "\xc2\x96" => "\xe2\x80\x93",
        "\xc2\x97" => "\xe2\x80\x94",
        "\xc2\x98" => "\xcb\x9c",
        "\xc2\x99" => "\xe2\x84\xa2",
        "\xc2\x9a" => "\xc5\xa1",
        "\xc2\x9b" => "\xe2\x80\xba",
        "\xc2\x9c" => "\xc5\x93",

        "\xc2\x9e" => "\xc5\xbe",
        "\xc2\x9f" => "\xc5\xb8",
    ];

    /**
     * @var mixed
     */
    protected static $utf8towin1252 = [
        "\xe2\x82\xac" => "\x80",

        "\xe2\x80\x9a" => "\x82",
        "\xc6\x92"     => "\x83",
        "\xe2\x80\x9e" => "\x84",
        "\xe2\x80\xa6" => "\x85",
        "\xe2\x80\xa0" => "\x86",
        "\xe2\x80\xa1" => "\x87",
        "\xcb\x86"     => "\x88",
        "\xe2\x80\xb0" => "\x89",
        "\xc5\xa0"     => "\x8a",
        "\xe2\x80\xb9" => "\x8b",
        "\xc5\x92"     => "\x8c",

        "\xc5\xbd"     => "\x8e",


        "\xe2\x80\x98" => "\x91",
        "\xe2\x80\x99" => "\x92",
        "\xe2\x80\x9c" => "\x93",
        "\xe2\x80\x9d" => "\x94",
        "\xe2\x80\xa2" => "\x95",
        "\xe2\x80\x93" => "\x96",
        "\xe2\x80\x94" => "\x97",
        "\xcb\x9c"     => "\x98",
        "\xe2\x84\xa2" => "\x99",
        "\xc5\xa1"     => "\x9a",
        "\xe2\x80\xba" => "\x9b",
        "\xc5\x93"     => "\x9c",

        "\xc5\xbe"     => "\x9e",
        "\xc5\xb8"     => "\x9f",
    ];

    /**
     * Function \ForceUTF8\Encoding::toUTF8
     *
     * This function leaves UTF8 characters alone, while converting almost all non-UTF8 to UTF8.
     *
     * It assumes that the encoding of the original string is either Windows-1252 or ISO 8859-1.
     *
     * It may fail to convert characters to UTF-8 if they fall into one of these scenarios:
     *
     * 1) when any of these characters:   ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß
     *    are followed by any of these:  ("group B")
     *                                    ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶•¸¹º»¼½¾¿
     * For example:   %ABREPRESENT%C9%BB. «REPRESENTÉ»
     * The "«" (%AB) character will be converted, but the "É" followed by "»" (%C9%BB)
     * is also a valid unicode character, and will be left unchanged.
     *
     * 2) when any of these: àáâãäåæçèéêëìíîï  are followed by TWO chars from group B,
     * 3) when any of these: ðñòó  are followed by THREE chars from group B.
     *
     * @param string $text  Any string.
     * @return string  The same string, UTF8 encoded
     *
     */
    public static function toutf8($text) {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = self::toUTF8($v);
            }
            return $text;
        }

        if (!is_string($text)) {
            return $text;
        }

        $max = self::strlen($text);

        $buf = "";
        for ($i = 0; $i < $max; $i++) {
            $c1 = $text[$i];
            if ($c1 >= "\xc0") { // Should be converted to UTF8, if it's not UTF8 already.
                $c2 = $i + 1 >= $max ? "\x00" : $text[$i + 1];
                $c3 = $i + 2 >= $max ? "\x00" : $text[$i + 2];
                $c4 = $i + 3 >= $max ? "\x00" : $text[$i + 3];
                if ($c1 >= "\xc0" & $c1 <= "\xdf") { // Looks like 2 bytes UTF8.
                    if ($c2 >= "\x80" && $c2 <= "\xbf") { // Yeah, almost sure it's UTF8 already.
                        $buf .= $c1 . $c2;
                        $i++;
                    } else { // Not valid UTF8.  Convert it.
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = ($c1 & "\x3f") | "\x80";
                        $buf .= $cc1 . $cc2;
                    }
                } else if ($c1 >= "\xe0" & $c1 <= "\xef") { // Looks like 3 bytes UTF8.
                    if ($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf") { // Yeah, almost sure it's UTF8 already.
                        $buf .= $c1 . $c2 . $c3;
                        $i = $i + 2;
                    } else { // Not valid UTF8.  Convert it.
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = ($c1 & "\x3f") | "\x80";
                        $buf .= $cc1 . $cc2;
                    }
                } else if ($c1 >= "\xf0" & $c1 <= "\xf7") { // Looks like 4 bytes UTF8.
                    if (
                        $c2 >= "\x80"
                        && $c2 <= "\xbf"
                        && $c3 >= "\x80"
                        && $c3 <= "\xbf"
                        && $c4 >= "\x80"
                        && $c4 <= "\xbf"
                    ) { // Yeah, almost sure it's UTF8 already.
                        $buf .= $c1 . $c2 . $c3 . $c4;
                        $i = $i + 3;
                    } else { // Not valid UTF8.  Convert it.
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = ($c1 & "\x3f") | "\x80";
                        $buf .= $cc1 . $cc2;
                    }
                } else { // Doesn't look like UTF8, but should be converted.
                    $cc1 = (chr(ord($c1) / 64) | "\xc0");
                    $cc2 = (($c1 & "\x3f") | "\x80");
                    $buf .= $cc1 . $cc2;
                }
            } else if (($c1 & "\xc0") == "\x80") { // Needs conversion.
                if (isset(self::$win1252toutf8[ord($c1)])) { // Found in Windows-1252 special cases.
                    $buf .= self::$win1252toutf8[ord($c1)];
                } else {
                    $cc1 = (chr(ord($c1) / 64) | "\xc0");
                    $cc2 = (($c1 & "\x3f") | "\x80");
                    $buf .= $cc1 . $cc2;
                }
            } else { // It doesn't need conversion.
                $buf .= $c1;
            }
        }
        return $buf;
    }

    /**
     * This function
     * @param string $text
     * @param string $option
     * @return array|false|mixed|string
     */
    public static function towin1252($text, $option = self::WITHOUT_ICONV) {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = self::toWin1252($v, $option);
            }
            return $text;
        } else if (is_string($text)) {
            return static::utf8_decode($text, $option);
        } else {
            return $text;
        }
    }

    /**
     * This function
     * @param string $text
     * @param string $option
     * @return array|false|mixed|string
     */
    public static function toiso8859($text, $option = self::WITHOUT_ICONV) {
        return self::toWin1252($text, $option);
    }

    /**
     * This function
     * @param string $text
     * @param string $option
     * @return array|false|mixed|string
     */
    public static function tolatin1($text, $option = self::WITHOUT_ICONV) {
        return self::toWin1252($text, $option);
    }

    /**
     * This function
     * @param string $text
     * @param string $option
     * @return array|mixed|string
     */
    public static function fixutf8($text, $option = self::WITHOUT_ICONV) {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = self::fixUTF8($v, $option);
            }
            return $text;
        }

        if (!is_string($text)) {
            return $text;
        }

        $last = "";
        while ($last <> $text) {
            $last = $text;
            $text = self::toUTF8(static::utf8_decode($text, $option));
        }
        $text = self::toUTF8(static::utf8_decode($text, $option));
        return $text;
    }

    /**
     * This function
     * @param string $text
     * @return array|string|string[]
     */
    public static function utf8fixwin1252chars($text) {
        // If you received an UTF-8 string that was converted from Windows-1252 as it was ISO8859-1
        // (ignoring Windows-1252 chars from 80 to 9F) use this function to fix it.
        // See: http://en.wikipedia.org/wiki/Windows-1252.

        return str_replace(array_keys(self::$brokenutf8toutf8), array_values(self::$brokenutf8toutf8), $text);
    }

    /**
     * This function
     * @param string $str
     * @return false|mixed|string
     */
    public static function removebom($str = "") {
        if (substr($str, 0, 3) == pack("CCC", 0xef, 0xbb, 0xbf)) {
            $str = substr($str, 3);
        }
        return $str;
    }

    /**
     * This function
     * @param string $text
     * @return false|int
     */
    protected static function strlen($text) {
        return mb_strlen($text, '8bit');
    }

    /**
     * This function
     * @param string $encodinglabel
     * @return string
     */
    public static function normalizeencoding($encodinglabel) {
        $encoding = strtoupper($encodinglabel);
        $encoding = preg_replace('/[^a-zA-Z0-9\s]/', '', $encoding);
        $equivalences = [
            'ISO88591' => 'ISO-8859-1',
            'ISO8859'  => 'ISO-8859-1',
            'ISO'      => 'ISO-8859-1',
            'LATIN1'   => 'ISO-8859-1',
            'LATIN'    => 'ISO-8859-1',
            'UTF8'     => 'UTF-8',
            'UTF'      => 'UTF-8',
            'WIN1252'  => 'ISO-8859-1',
            'WINDOWS1252' => 'ISO-8859-1',
        ];

        if (empty($equivalences[$encoding])) {
            return 'UTF-8';
        }

        return $equivalences[$encoding];
    }

    /**
     * This function
     * @param string $encodinglabel
     * @param string $text
     * @return array|false|mixed|string
     */
    public static function encode($encodinglabel, $text) {
        $encodinglabel = self::normalizeEncoding($encodinglabel);
        if ($encodinglabel == 'ISO-8859-1') {
            return self::toLatin1($text);
        }
        return self::toUTF8($text);
    }

    /**
     * This function
     * @param string $text
     * @param string $option
     * @return false|string
     */
    protected static function utf8_decode($text, $option = self::WITHOUT_ICONV) {
        if ($option == self::WITHOUT_ICONV || !function_exists('iconv')) {
            $o = mb_convert_encoding(
                str_replace(array_keys(self::$utf8towin1252), array_values(self::$utf8towin1252), self::toUTF8($text)),
                'ISO-8859-1',
                'UTF-8'
            );
        } else {
            $o = iconv(
                "UTF-8",
                "Windows-1252" . ($option == self::ICONV_TRANSLIT
                    ? '//TRANSLIT' :
                    ($option == self::ICONV_IGNORE ? '//IGNORE' : '')),
                $text
            );
        }
        return $o;
    }
}
