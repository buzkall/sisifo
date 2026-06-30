<?php

namespace Arzcode\Sisifo\Support;

class PushoverHtml
{
    /**
     * Escape AI/email-derived content, then re-allow only the Pushover-compatible
     * formatting tags so the preview renders the same subset Pushover supports
     * (<b>, <i>, <u>, <br>, <hr>, <a href="http...">) without exposing stored XSS.
     * Plain newlines are turned into <br> so the preview keeps its line breaks.
     */
    public static function sanitize(string $html): string
    {
        $escaped = e($html);

        $escaped = preg_replace('~&lt;(/?)(b|i|u)&gt;~i', '<$1$2>', $escaped);
        $escaped = preg_replace('~&lt;br\s*/?&gt;~i', '<br>', $escaped);
        $escaped = preg_replace('~&lt;hr\s*/?&gt;~i', '<hr>', $escaped);

        $escaped = preg_replace_callback(
            '~&lt;a href=(?:&quot;|&#039;)(.*?)(?:&quot;|&#039;)&gt;(.*?)&lt;/a&gt;~is',
            function(array $match): string {
                $url = html_entity_decode($match[1], ENT_QUOTES);

                if (! preg_match('~^https?://~i', $url)) {
                    return $match[0];
                }

                return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . $match[2] . '</a>';
            },
            $escaped
        );

        return nl2br($escaped);
    }
}
