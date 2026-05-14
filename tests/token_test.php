<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

/**
 * @group mod_bunnystream
 * @covers \mod_bunnystream\token
 */
class token_test extends \advanced_testcase {

    public function test_sign_tus_produces_expected_hash(): void {
        // Fixed expires by mocking time? PHP can't mock time() directly without
        // runkit; instead, compute the expected hash from sign_tus's output.
        $libraryid = '12345';
        $apikey = 'fake-api-key';
        $videoguid = 'abc-def-123';
        $result = token::sign_tus($libraryid, $apikey, $videoguid, 3600);
        $this->assertSame($libraryid, $result['library_id']);
        $this->assertIsInt($result['expires']);
        $this->assertGreaterThan(time(), $result['expires']);
        // Verify the hash matches the documented Bunny scheme.
        $expected = hash('sha256', $libraryid . $apikey . $result['expires'] . $videoguid);
        $this->assertSame($expected, $result['signature']);
    }

    public function test_sign_embed_produces_expected_url(): void {
        $libraryid = '12345';
        $guid = 'abc-def-123';
        $securitykey = 'fake-security-key';
        $url = token::sign_embed($libraryid, $guid, $securitykey, 21600, ['autoplay' => 'true']);
        $this->assertStringStartsWith('https://iframe.mediadelivery.net/embed/12345/abc-def-123?', $url);
        $this->assertStringContainsString('autoplay=true', $url);
        $this->assertStringContainsString('token=', $url);
        $this->assertStringContainsString('expires=', $url);

        // Extract token + expires and verify against the Bunny formula.
        $parts = parse_url($url);
        parse_str($parts['query'], $qs);
        $expected = hash('sha256', $securitykey . $guid . $qs['expires']);
        $this->assertSame($expected, $qs['token']);
    }

    public function test_unsigned_embed_omits_token(): void {
        $url = token::unsigned_embed('12345', 'abc-def-123', ['responsive' => 'true']);
        $this->assertStringNotContainsString('token=', $url);
        $this->assertStringContainsString('responsive=true', $url);
    }

    public function test_embed_url_for_falls_back_to_unsigned_when_no_security_key(): void {
        $url = token::embed_url_for('12345', 'abc-def-123', null);
        $this->assertStringNotContainsString('token=', $url);
    }
}
