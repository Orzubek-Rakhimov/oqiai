<?php

namespace App\Concerns;

trait AudioClipValidationRules
{
    /**
     * The content types a recording made in a browser is detected as on the
     * server: WebM or Ogg (Opus), MP4 (AAC, as Safari records), WAV and MPEG.
     * An audio-only WebM or MP4 file is detected as video/webm or video/mp4.
     *
     * @var list<string>
     */
    private const array AUDIO_CLIP_MIME_TYPES = [
        'audio/webm', 'video/webm',
        'audio/ogg', 'audio/opus', 'application/ogg',
        'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'audio/aac', 'audio/x-hx-aac-adts',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave',
        'audio/mpeg',
    ];

    /**
     * Get the validation rules for a clip the Child read aloud: a recording of
     * at most 5 MB whose content, not its label, is a browser audio format.
     *
     * @return array<int, string>
     */
    protected function audioClipRules(): array
    {
        return ['required', 'file', 'max:5120', 'mimetypes:'.implode(',', self::AUDIO_CLIP_MIME_TYPES)];
    }
}
