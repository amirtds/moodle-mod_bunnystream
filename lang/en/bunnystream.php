<?php
// This file is part of Moodle - https://moodle.org/
// GPLv3 — see LICENSE.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bunny Stream';
$string['modulename'] = 'Bunny Stream';
$string['modulenameplural'] = 'Bunny Stream videos';
$string['modulename_help'] = 'The Bunny Stream activity lets teachers upload a video directly to a configured Bunny.net Stream library and embed it in a course with a signed, hot-link-protected player.';
$string['bunnystream:addinstance'] = 'Add a new Bunny Stream video';
$string['bunnystream:view'] = 'View Bunny Stream videos';
$string['bunnystream:manage'] = 'Manage Bunny Stream videos (upload, edit, delete)';
$string['pluginadministration'] = 'Bunny Stream administration';

// Settings page.
$string['settings_heading'] = 'Bunny.net Stream credentials';
$string['settings_heading_desc'] = 'These credentials connect this Moodle instance to a single Bunny.net Stream library. After saving, copy the displayed webhook URL into Bunny dashboard → Stream → Library → Webhooks so encoding-status updates flow back.';
$string['setting_library_id'] = 'Library ID';
$string['setting_library_id_desc'] = 'Numeric Bunny.net Stream library ID (Stream → Library → API).';
$string['setting_api_key'] = 'API Key';
$string['setting_api_key_desc'] = 'Bunny.net Stream library API key (Stream → Library → API). Stored encrypted at rest. Leave blank to keep the existing value.';
$string['setting_security_key'] = 'Security (Token Authentication) Key';
$string['setting_security_key_desc'] = 'Bunny.net Token Authentication key (Stream → Library → Security). Optional — without it, embeds fall back to unsigned URLs. Stored encrypted at rest.';
$string['setting_cdn_hostname'] = 'CDN Hostname';
$string['setting_cdn_hostname_desc'] = 'Pull-zone hostname assigned to the library, looks like <code>vz-xxxxxxxx-xxx.b-cdn.net</code>.';
$string['setting_completion_percent'] = 'Default completion threshold (%)';
$string['setting_completion_percent_desc'] = 'Default percentage of the video a learner must watch for the activity to be marked complete. Can be overridden per activity.';
$string['setting_webhook_url'] = 'Webhook URL';
$string['setting_webhook_url_desc'] = 'Paste this into Bunny dashboard → Stream → Library → Webhooks. Rotated automatically when the library ID changes.';
$string['setting_webhook_url_pending'] = 'Save credentials to generate a webhook URL.';

// Activity form.
$string['name'] = 'Activity name';
$string['video'] = 'Video';
$string['completion_percent'] = 'Completion threshold (%)';
$string['completion_percent_help'] = 'Mark the activity complete once the learner has watched at least this percentage of the video.';
$string['video_style'] = 'Video display style';
$string['style_default'] = 'Default';
$string['style_rounded'] = 'Rounded corners';
$string['style_padded'] = 'Padded';
$string['style_cinema'] = 'Cinema';
$string['style_compact'] = 'Compact';

// Author panels.
$string['drop_video_here'] = 'Drop a video file here, or';
$string['choose_video'] = 'Choose video';
$string['uploading_label'] = 'Uploading';
$string['cancel_upload'] = 'Cancel upload';
$string['processing_label'] = 'Bunny is processing your video — this can take a few minutes for longer files.';
$string['processing_elapsed'] = 'Elapsed';
$string['failed_label'] = 'Something went wrong with this video.';
$string['replace_video'] = 'Replace video';
$string['delete_video'] = 'Delete video';
$string['delete_confirm_title'] = 'Delete this video?';
$string['delete_confirm_body'] = 'The video will be removed from Bunny.net and from this activity. This cannot be undone.';
$string['delete_confirm_yes'] = 'Yes, delete';
$string['delete_confirm_no'] = 'Cancel';
$string['video_title'] = 'Video title';
$string['video_duration'] = 'Duration';
$string['video_id'] = 'Bunny GUID';
$string['thumbnail_section'] = 'Thumbnail';
$string['thumbnail_replace'] = 'Replace thumbnail';
$string['captions_section'] = 'Subtitles';
$string['captions_empty'] = 'No subtitle tracks yet.';
$string['caption_add'] = 'Upload .vtt';
$string['caption_transcribe'] = 'Auto-transcribe (en)';
$string['caption_remove'] = 'Remove';
$string['chapters_section'] = 'Chapters';
$string['chapter_add'] = 'Add chapter';
$string['chapter_save'] = 'Save chapters';

// Errors.
$string['error_not_configured'] = 'Bunny Stream is not configured for this site. An administrator must paste credentials in Site administration → Plugins → Activity modules → Bunny Stream.';
$string['error_undecryptable'] = 'Stored Bunny credentials could not be decrypted. Re-enter them in site administration.';
$string['error_no_video'] = 'No video uploaded yet.';
$string['error_failed_upload'] = 'Upload failed. Check your connection and try again.';
$string['error_too_many_uploads'] = 'Too many uploads. Try again in a minute.';
$string['error_too_many_deletes'] = 'Too many delete requests. Try again in a minute.';

// Privacy.
$string['privacy:metadata:bunnystream_progress'] = 'Stores the highest watched percentage per user per video so completion + grading can be computed.';
$string['privacy:metadata:bunnystream_progress:userid'] = 'The Moodle user ID.';
$string['privacy:metadata:bunnystream_progress:bunnystreamid'] = 'The Bunny Stream activity ID.';
$string['privacy:metadata:bunnystream_progress:max_percent'] = 'Highest percentage of the video the user has watched.';
$string['privacy:metadata:bunnystream_progress:timemodified'] = 'When the progress was last updated.';
$string['privacy:metadata:bunny_net'] = 'Bunny.net is the external video streaming provider. Embedding a video causes the learner\'s browser to make a request to iframe.mediadelivery.net and to the configured CDN hostname; no Moodle user identifier is forwarded.';
$string['privacy:metadata:bunny_net:guid'] = 'The Bunny video GUID requested for playback.';
