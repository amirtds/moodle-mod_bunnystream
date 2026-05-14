<?php
// GPLv3 — see LICENSE.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

/**
 * @group mod_bunnystream
 * @covers \mod_bunnystream\bunny_client
 */
class bunny_client_test extends \advanced_testcase {

    public function test_map_status_handles_all_bunny_codes(): void {
        $this->assertSame('pending',  bunny_client::map_status(0));
        $this->assertSame('uploaded', bunny_client::map_status(1));
        $this->assertSame('encoding', bunny_client::map_status(2));
        $this->assertSame('encoding', bunny_client::map_status(3));
        $this->assertSame('ready',    bunny_client::map_status(4));
        $this->assertSame('failed',   bunny_client::map_status(5));
        $this->assertSame('failed',   bunny_client::map_status(6));
        $this->assertSame('encoding', bunny_client::map_status(7));
        $this->assertSame('ready',    bunny_client::map_status(8));
        $this->assertSame('pending',  bunny_client::map_status(null));
        $this->assertSame('pending',  bunny_client::map_status(999));
    }

    public function test_is_terminal_status(): void {
        $this->assertTrue(bunny_client::is_terminal_status('ready'));
        $this->assertTrue(bunny_client::is_terminal_status('failed'));
        $this->assertFalse(bunny_client::is_terminal_status('encoding'));
        $this->assertFalse(bunny_client::is_terminal_status('pending'));
        $this->assertFalse(bunny_client::is_terminal_status(''));
    }

    public function test_thumbnail_url_strips_protocol(): void {
        $url = bunny_client::thumbnail_url('https://vz-abc.b-cdn.net/', 'guid-1', null);
        $this->assertSame('https://vz-abc.b-cdn.net/guid-1/thumbnail.jpg', $url);
    }

    public function test_thumbnail_url_uses_provided_filename(): void {
        $url = bunny_client::thumbnail_url('vz-abc.b-cdn.net', 'guid-1', 'custom.jpg');
        $this->assertSame('https://vz-abc.b-cdn.net/guid-1/custom.jpg', $url);
    }

    public function test_thumbnail_url_returns_null_without_host(): void {
        $this->assertNull(bunny_client::thumbnail_url(null, 'guid-1', null));
        $this->assertNull(bunny_client::thumbnail_url('', 'guid-1', null));
    }
}
