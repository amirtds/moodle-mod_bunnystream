<?php
// GPLv3 — see LICENSE.
//
// TUS upload + iframe embed signing for Bunny.net Stream.
// Hash inputs are dictated by Bunny — do NOT reorder fields or add separators.

namespace mod_bunnystream;

defined('MOODLE_INTERNAL') || die();

class token {

    /** Embed URLs are valid for 6h — matches Bunny's MediaCage recommendation. */
    const EMBED_TTL_SECONDS = 21600;

    /** TUS upload signatures live for 1h — plenty for any single-file upload. */
    const TUS_TTL_SECONDS = 3600;

    /**
     * Sign a TUS upload.
     *
     * Bunny's TUS endpoint authenticates each upload with:
     *   AuthorizationSignature = sha256(libraryId + apiKey + expires + videoId)
     */
    public static function sign_tus(string $libraryid, string $apikey, string $videoguid, int $ttl = self::TUS_TTL_SECONDS): array {
        $expires = time() + $ttl;
        $raw = $libraryid . $apikey . $expires . $videoguid;
        return [
            'library_id' => $libraryid,
            'expires'    => $expires,
            'signature'  => hash('sha256', $raw),
        ];
    }

    /**
     * Sign a Bunny Stream iframe embed URL.
     *
     *   token = sha256(securityKey + videoId + expirationUnix)  (hex)
     */
    public static function sign_embed(string $libraryid, string $guid, string $securitykey, int $ttl = self::EMBED_TTL_SECONDS, array $extra = []): string {
        $expires = time() + $ttl;
        $token = hash('sha256', $securitykey . $guid . $expires);
        $params = array_merge(['token' => $token, 'expires' => (string)$expires], $extra);
        return bunny_client::BUNNY_EMBED_BASE . "/{$libraryid}/{$guid}?" . http_build_query($params);
    }

    /**
     * Build an unsigned embed URL (only works if Bunny library has token auth off).
     */
    public static function unsigned_embed(string $libraryid, string $guid, array $extra = []): string {
        $base = bunny_client::BUNNY_EMBED_BASE . "/{$libraryid}/{$guid}";
        if (empty($extra)) {
            return $base;
        }
        return $base . '?' . http_build_query($extra);
    }

    /**
     * Server-side: pick the right URL flavour given the configured security_key.
     *
     * Mirrors bunny_api.get_embed_url_for_video() in the XBlock.
     */
    public static function embed_url_for(string $libraryid, string $guid, ?string $securitykey, array $extra = []): string {
        if ($securitykey) {
            return self::sign_embed($libraryid, $guid, $securitykey, self::EMBED_TTL_SECONDS, $extra);
        }
        return self::unsigned_embed($libraryid, $guid, $extra);
    }
}
