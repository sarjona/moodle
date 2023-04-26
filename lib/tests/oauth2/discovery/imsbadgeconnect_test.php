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

namespace core\oauth2\discovery;

use core\oauth2\api;

/**
 * Unit tests for {@see imsbadgeconnect}.
 *
 * @coversDefaultClass \core\oauth2\discovery\imsbadgeconnect
 * @package core
 * @copyright 2023 Sara Arjona <sara@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imsbadgeconnect_test extends \advanced_testcase {

    /**
     * Test the method to get discovery endpoing URL.
     *
     * @dataProvider issuers_provider
     * @covers ::get_discovery_endpoint_url
     * @param string|null $baseurl The issuer baseurl.
     * @param string $expected The expected URL to be returned by get_discovery_endpoint_url().
     * @param string $type The issuer type. If not defined, it's set to 'imsobv2p1'.
     */
    public function test_get_discovery_endpoint_url(?string $baseurl, string $expected, string $type = 'imsobv2p1'): void {
        $this->resetAfterTest();

        // Mark test as long because it connects with external services.
        if (!PHPUNIT_LONGTEST) {
            $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
        }

        $this->setAdminUser();

        $issuer = api::create_standard_issuer($type, $baseurl);
        $result = imsbadgeconnect::get_discovery_endpoint_url($issuer);
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for test_get_discovery_endpoint_url.
     *
     * @return array
     */
    public function issuers_provider(): array {
        return [
            'Issuer with baseurl' => [
                'url' => 'https://dc.imsglobal.org',
                'expected' => 'https://dc.imsglobal.org/.well-known/badgeconnect.json',
            ],
            'Issuer with baseurl ended with slash' => [
                'url' => 'https://dc.imsglobal.org/',
                'expected' => 'https://dc.imsglobal.org/.well-known/badgeconnect.json',
            ],
            'Issuer with baseurl with path component' => [
                'url' => 'https://certification.imsglobal.org/badgeconnect',
                'expected' => 'https://certification.imsglobal.org/.well-known/badgeconnect.json',
            ],
            'Issuer with null baseurl' => [
                'url' => null,
                'expected' => '',
                'type' => 'facebook',
            ],
        ];
    }

}
