<?php

namespace App\Models;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Admin-authored HTML with scripts, event handlers and javascript: URLs removed (defence in depth). */
    public function safeBody(): string
    {
        $html = $this->withTokens((string) $this->body);
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xp = new DOMXPath($dom);
        foreach ($xp->query('//script|//style|//iframe|//object|//embed|//form|//link|//meta') as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach ($xp->query('//*') as $el) {
            /** @var DOMElement $el */
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = strtolower(preg_replace('/[\s\x00-\x1f]+/', '', $attr->value));
                if (str_starts_with($name, 'on') || (in_array($name, ['href', 'src', 'xlink:href', 'action', 'formaction'], true) && preg_match('/^(javascript|data|vbscript):/', $value))) {
                    $el->removeAttribute($attr->name);
                }
            }
        }

        $root = $dom->getElementById('root');
        $out = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    /** Replaces {store_name}, {contact_email}, {contact_phone}, {contact_link}, {updated_date} with live store settings. */
    private function withTokens(string $html): string
    {
        $email = StoreSetting::get('contact_email');
        $phone = StoreSetting::get('contact_phone');
        $link = '<a href="'.e(route('contact')).'">our contact page</a>';

        return strtr($html, [
            '{store_name}' => e(StoreSetting::get('store_name', config('app.name'))),
            '{contact_email}' => $email ? e($email) : $link,
            '{contact_phone}' => $phone ? e($phone) : $link,
            '{contact_link}' => $email ? '<a href="mailto:'.e($email).'">'.e($email).'</a> or '.$link : $link,
            '{updated_date}' => ($this->updated_at ?? now())->format('j F Y'),
        ]);
    }
}
