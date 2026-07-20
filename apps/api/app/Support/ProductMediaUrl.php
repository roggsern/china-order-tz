<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ProductMediaUrl
{
    public static function assertSupportedVideoUrl(string $url): void
    {
        if (! self::isYoutube($url) && ! self::isVimeo($url)) {
            throw ValidationException::withMessages([
                'url' => ['Video URL must be a YouTube or Vimeo link.'],
            ]);
        }
    }

    public static function youtubeThumbnail(string $url): ?string
    {
        $id = self::youtubeId($url);

        return $id !== null ? 'https://img.youtube.com/vi/'.$id.'/hqdefault.jpg' : null;
    }

    public static function isYoutube(string $url): bool
    {
        return self::youtubeId($url) !== null;
    }

    public static function isVimeo(string $url): bool
    {
        return (bool) preg_match('#(?:vimeo\.com/)(\d+)#i', $url);
    }

    public static function youtubeId(string $url): ?string
    {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})#i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
