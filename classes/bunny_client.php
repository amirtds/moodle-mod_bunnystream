<?php
// GPLv3 — see LICENSE.
//
// Direct port of bunny-xblock/bunny_xblock/bunny_api.py.
// API docs: https://docs.bunny.net/reference/api-overview

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class bunny_client {

    const BUNNY_BASE = 'https://video.bunnycdn.com';
    const BUNNY_EMBED_BASE = 'https://iframe.mediadelivery.net/embed';

    const STATUS_PENDING  = 'pending';
    const STATUS_UPLOADED = 'uploaded';
    const STATUS_ENCODING = 'encoding';
    const STATUS_READY    = 'ready';
    const STATUS_FAILED   = 'failed';

    /** @var \stdClass plaintext-decrypted credentials */
    private $cfg;

    public function __construct(\stdClass $cfg) {
        $this->cfg = $cfg;
    }

    // ---- HTTP -------------------------------------------------------------

    private function request(string $method, string $path, ?array $jsonbody = null, ?string $rawbody = null, ?string $rawcontenttype = null): array {
        $url = self::BUNNY_BASE . $path;
        $curl = new \curl();
        $headers = [
            'AccessKey: ' . $this->cfg->api_key,
            'Accept: application/json',
        ];
        $options = ['CURLOPT_TIMEOUT' => 30, 'CURLOPT_CONNECTTIMEOUT' => 10];
        $body = null;
        if ($jsonbody !== null) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($jsonbody);
        } else if ($rawbody !== null) {
            $headers[] = 'Content-Type: ' . ($rawcontenttype ?: 'application/octet-stream');
            $body = $rawbody;
        }
        $curl->setHeader($headers);
        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, [], $options);
                break;
            case 'POST':
                $response = $curl->post($url, $body, $options);
                break;
            case 'PUT':
                $curl->setopt(['CURLOPT_CUSTOMREQUEST' => 'PUT']);
                $response = $curl->post($url, $body, $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, [], $options);
                break;
            default:
                throw new bunny_api_error(500, 'Unsupported method: ' . $method);
        }
        $info = $curl->get_info();
        $code = (int)($info['http_code'] ?? 0);
        return ['code' => $code, 'body' => (string)$response, 'info' => $info];
    }

    // ---- Video CRUD ------------------------------------------------------

    public function create_video(string $title): string {
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos", ['title' => $title]);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny createVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
        $data = json_decode($res['body'], true);
        $guid = $data['guid'] ?? '';
        if (!$guid) {
            throw new bunny_api_error(502, 'Bunny createVideo returned no guid');
        }
        return $guid;
    }

    public function get_video(string $guid): ?array {
        $res = $this->request('GET', "/library/{$this->cfg->library_id}/videos/{$guid}");
        if ($res['code'] === 404) {
            return null;
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny getVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
        return json_decode($res['body'], true);
    }

    public function update_video(string $guid, ?string $title = null, ?array $chapters = null): void {
        $payload = [];
        if ($title !== null) {
            $payload['title'] = $title;
        }
        if ($chapters !== null) {
            $payload['chapters'] = $chapters;
        }
        if (empty($payload)) {
            return;
        }
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos/{$guid}", $payload);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny updateVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    public function delete_video(string $guid): void {
        $res = $this->request('DELETE', "/library/{$this->cfg->library_id}/videos/{$guid}");
        if (($res['code'] < 200 || $res['code'] >= 300) && $res['code'] !== 404) {
            throw new bunny_api_error($res['code'], "Bunny deleteVideo failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    // ---- Captions --------------------------------------------------------

    public function list_captions(string $guid): array {
        $meta = $this->get_video($guid);
        if (!$meta) {
            return [];
        }
        $captions = $meta['captions'] ?? [];
        $out = [];
        foreach ($captions as $c) {
            if (!is_array($c)) {
                continue;
            }
            $out[] = [
                'srclang' => $c['srclang'] ?? $c['Srclang'] ?? '',
                'label'   => $c['label']   ?? $c['Label']   ?? '',
            ];
        }
        return $out;
    }

    public function upload_caption(string $guid, string $srclang, string $label, string $vttbytes): void {
        $payload = [
            'srclang'      => $srclang,
            'label'        => $label ?: strtoupper($srclang),
            'captionsFile' => base64_encode($vttbytes),
        ];
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos/{$guid}/captions/{$srclang}", $payload);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny uploadCaption failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    public function delete_caption(string $guid, string $srclang): void {
        $res = $this->request('DELETE', "/library/{$this->cfg->library_id}/videos/{$guid}/captions/{$srclang}");
        if (($res['code'] < 200 || $res['code'] >= 300) && $res['code'] !== 404) {
            throw new bunny_api_error($res['code'], "Bunny deleteCaption failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    public function transcribe(string $guid, string $language = 'en', bool $force = false): void {
        $qs = http_build_query(['language' => $language, 'force' => $force ? 'true' : 'false']);
        $res = $this->request('POST', "/library/{$this->cfg->library_id}/videos/{$guid}/transcribe?{$qs}");
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny transcribe failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    // ---- Chapters --------------------------------------------------------

    public function get_chapters(string $guid): array {
        $meta = $this->get_video($guid);
        if (!$meta) {
            return [];
        }
        $chapters = $meta['chapters'] ?? [];
        $out = [];
        foreach ($chapters as $ch) {
            if (!is_array($ch)) {
                continue;
            }
            $out[] = [
                'title' => trim((string)($ch['title'] ?? $ch['Title'] ?? '')),
                'start' => (int)($ch['start'] ?? $ch['Start'] ?? 0),
                'end'   => (int)($ch['end']   ?? $ch['End']   ?? 0),
            ];
        }
        return $out;
    }

    public function set_chapters(string $guid, array $chapters): void {
        $this->update_video($guid, null, $chapters);
    }

    // ---- Thumbnail --------------------------------------------------------

    public function set_thumbnail(string $guid, string $imagebytes, string $contenttype): void {
        $res = $this->request(
            'POST',
            "/library/{$this->cfg->library_id}/videos/{$guid}/thumbnail",
            null,
            $imagebytes,
            $contenttype ?: 'application/octet-stream'
        );
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new bunny_api_error($res['code'], "Bunny setThumbnail failed ({$res['code']}): " . substr($res['body'], 0, 200));
        }
    }

    // ---- Status mapping (Bunny code → our string) -------------------------

    public static function map_status($code): string {
        if ($code === 0)   return self::STATUS_PENDING;
        if ($code === 1)   return self::STATUS_UPLOADED;
        if (in_array($code, [2, 3, 7], true)) return self::STATUS_ENCODING;
        if (in_array($code, [4, 8], true))    return self::STATUS_READY;
        if (in_array($code, [5, 6], true))    return self::STATUS_FAILED;
        return self::STATUS_PENDING;
    }

    public static function is_terminal_status(string $status): bool {
        return $status === self::STATUS_READY || $status === self::STATUS_FAILED;
    }

    // ---- Thumbnail URL helper --------------------------------------------

    public static function thumbnail_url(?string $cdnhostname, string $guid, ?string $filename = null): ?string {
        if (!$cdnhostname) {
            return null;
        }
        $host = rtrim(preg_replace('#^https?://#', '', $cdnhostname), '/');
        $name = $filename ?: 'thumbnail.jpg';
        return "https://{$host}/{$guid}/{$name}";
    }
}

class bunny_api_error extends \moodle_exception {
    public $statuscode;
    public function __construct(int $code, string $message) {
        $this->statuscode = $code;
        parent::__construct('error', 'mod_bunnystream', '', null, $message);
    }
}
