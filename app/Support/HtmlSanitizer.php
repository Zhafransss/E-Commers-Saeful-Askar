<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Cleans HTML that came from outside before it is stored: product descriptions
 * from Jubelio (INT-JB-04).
 *
 * Only text, heading, list, link, image, and table markup survives: no
 * <script>, <iframe>, style, or on* attributes. Links open with
 * rel="noopener"; only http(s), mailto, and tel URLs are kept.
 */
class HtmlSanitizer
{
    private const ALLOWED_ELEMENTS = 'p,br,hr,strong,b,em,i,u,s,sub,sup,blockquote,h1,h2,h3,h4,h5,h6,ul,ol,li,'
        .'a[href|title|target],img[src|alt|width|height],'
        .'table,thead,tbody,tfoot,tr,th[colspan|rowspan],td[colspan|rowspan],span,div';

    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        return trim($this->purifier()->purify($html));
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier === null) {
            $cache = storage_path('app/purifier');
            is_dir($cache) || mkdir($cache, 0775, true);

            $config = HTMLPurifier_Config::createDefault();
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('Cache.SerializerPath', $cache);
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.Allowed', self::ALLOWED_ELEMENTS);
            $config->set('HTML.TargetBlank', true);
            $config->set('HTML.TargetNoopener', true);
            $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
            $config->set('AutoFormat.RemoveEmpty', false);

            $this->purifier = new HTMLPurifier($config);
        }

        return $this->purifier;
    }
}
