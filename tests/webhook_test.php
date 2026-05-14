<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

/**
 * @group mod_bunnystream
 * @covers \mod_bunnystream\webhook_processor
 */
class webhook_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Capture echoed JSON body + HTTP code from webhook_processor::process().
     */
    private function call_process(string $token, string $body): array {
        ob_start();
        try {
            webhook_processor::process($token, $body);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $output = ob_get_clean();
        return json_decode($output ?: '{}', true) ?: [];
    }

    public function test_unknown_token_rejected(): void {
        $result = $this->call_process('aaaaaaaaaaaaaaaaaaaaaa', '{}');
        $this->assertSame('unknown_token', $result['error']);
    }

    public function test_known_token_with_malformed_payload_rejected(): void {
        global $DB;
        $token = str_repeat('a', 64);
        $row = (object)[
            'id' => 1,
            'library_id' => '12345',
            'api_key_ciphertext' => '',
            'security_key_ciphertext' => '',
            'cdn_hostname' => '',
            'webhook_secret' => $token,
            'timemodified' => time(),
        ];
        if ($DB->record_exists('bunnystream_config', ['id' => 1])) {
            $DB->update_record('bunnystream_config', $row);
        } else {
            $DB->insert_record_raw('bunnystream_config', $row, false, false, true);
        }

        $result = $this->call_process($token, 'not-json');
        $this->assertSame('invalid_json', $result['error']);

        $result2 = $this->call_process($token, json_encode(['VideoGuid' => 'g']));
        $this->assertSame('malformed_payload', $result2['error']);
    }

    public function test_library_mismatch_rejected(): void {
        global $DB;
        $token = str_repeat('b', 64);
        $row = (object)[
            'id' => 1,
            'library_id' => '12345',
            'api_key_ciphertext' => '',
            'security_key_ciphertext' => '',
            'cdn_hostname' => '',
            'webhook_secret' => $token,
            'timemodified' => time(),
        ];
        if ($DB->record_exists('bunnystream_config', ['id' => 1])) {
            $DB->update_record('bunnystream_config', $row);
        } else {
            $DB->insert_record_raw('bunnystream_config', $row, false, false, true);
        }
        $result = $this->call_process($token, json_encode([
            'VideoGuid' => 'guid-1', 'VideoLibraryId' => 99999, 'Status' => 4,
        ]));
        $this->assertSame('library_mismatch', $result['error']);
    }

    public function test_terminal_state_regression_rejected(): void {
        global $DB;
        $token = str_repeat('c', 64);
        $row = (object)[
            'id' => 1,
            'library_id' => '12345',
            'api_key_ciphertext' => '',
            'security_key_ciphertext' => '',
            'cdn_hostname' => '',
            'webhook_secret' => $token,
            'timemodified' => time(),
        ];
        if ($DB->record_exists('bunnystream_config', ['id' => 1])) {
            $DB->update_record('bunnystream_config', $row);
        } else {
            $DB->insert_record_raw('bunnystream_config', $row, false, false, true);
        }
        $DB->insert_record('bunnystream_videos', (object)[
            'guid' => 'guid-1', 'library_id' => '12345', 'title' => '',
            'status' => 'ready', 'created_by' => null,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $result = $this->call_process($token, json_encode([
            'VideoGuid' => 'guid-1', 'VideoLibraryId' => 12345, 'Status' => 2,
        ]));
        $this->assertSame('terminal_state_regression', $result['error']);
    }
}
