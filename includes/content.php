<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Editable site content.
 *
 * The static HTML pages stay the source of the original wording. Every editable
 * element carries a data-edit="key" attribute; text saved by staff is stored in
 * the site_content table and applied in the browser by js/content.js. Removing
 * an override restores the original wording from the HTML file.
 */
final class SiteContent
{
    public const MAX_LENGTH = 2000;

    /** Page slug => label shown in the editor (slug = HTML file name without .html). */
    public const PAGES = [
        'index' => 'Home',
        'about' => 'About',
        'services' => 'Services',
        'gallery' => 'Gallery',
        'menu' => 'Menu / Dining',
        'restaurant' => 'Restaurant',
        'room' => 'Rooms',
        'pricing' => 'Pricing',
        'reservation' => 'Reservation page',
        'book-table' => 'Book a table page',
        'contact' => 'Contact',
        'faq' => 'FAQs',
        'blog' => 'Journal / Visit',
        'blog-post' => 'Journal article',
        'case-studies' => 'Case studies',
        'testimonials' => 'Testimonials',
        'privacy' => 'Privacy policy',
        'thank-you' => 'Thank-you page',
        '404' => 'Page not found (404)',
        'home-2' => 'Home (alternate 2)',
        'home-3' => 'Home (alternate 3)',
    ];

    /** Details shown in the footer and contact pages on every page of the site. */
    public const SITE_FIELDS = [
        'phone' => ['label' => 'Main phone number', 'default' => '+232 30 555 551', 'max' => 30],
        'phone2' => ['label' => 'Second phone / WhatsApp', 'default' => '+232 78 767 876', 'max' => 30],
        'email' => ['label' => 'Email address', 'default' => 'Companeroresort.sl@gmail.com', 'max' => 254],
        'address' => ['label' => 'Address', 'default' => '4 Main Kent, Bureh Town', 'max' => 120],
    ];

    public static function ensureTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS site_content (
                page VARCHAR(40) NOT NULL,
                content_key VARCHAR(60) NOT NULL,
                content_value TEXT NOT NULL,
                updated_by INT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (page, content_key),
                CONSTRAINT fk_site_content_admin FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function isPage(string $slug): bool
    {
        return isset(self::PAGES[$slug]);
    }

    /** Clean text submitted from the editor: LF line breaks, no control characters, trimmed. */
    public static function normalize(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/(?!\n)\p{Cc}/u', '', $value);
        $lines = array_map('rtrim', explode("\n", $value));
        return trim(implode("\n", $lines));
    }

    /** Returns an error message, or null when the site-wide value is acceptable. */
    public static function validateSiteValue(string $key, string $value): ?string
    {
        $field = self::SITE_FIELDS[$key] ?? null;
        if ($field === null) {
            return 'Unknown field.';
        }
        if ($value === '' || mb_strlen($value) > $field['max'] || str_contains($value, "\n")) {
            return $field['label'] . ' must be between 1 and ' . $field['max'] . ' characters on one line.';
        }
        if ($key === 'phone' || $key === 'phone2') {
            if (!preg_match('/^\+?[0-9][0-9 ()\-]{4,28}$/', $value)) {
                return $field['label'] . ' can only contain digits, spaces, brackets, dashes and a leading +.';
            }
        } elseif ($key === 'email') {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                return 'Enter a valid email address.';
            }
        }
        return null;
    }

    /** @return array<string,string> key => saved text for one page ('site' for site-wide details) */
    public static function overrides(PDO $pdo, string $page): array
    {
        $stmt = $pdo->prepare('SELECT content_key, content_value FROM site_content WHERE page = ?');
        $stmt->execute([$page]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['content_key']] = (string) $row['content_value'];
        }
        return $out;
    }

    /** @return array<string,int> page => number of customised items */
    public static function overrideCounts(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT page, COUNT(*) AS n FROM site_content GROUP BY page')->fetchAll() as $row) {
            $out[(string) $row['page']] = (int) $row['n'];
        }
        return $out;
    }

    /**
     * Reads the original wording from the page's HTML file.
     *
     * @return list<array{key:string,type:string,group:string,default:string}>
     */
    public static function fields(string $slug): array
    {
        if (!self::isPage($slug)) {
            return [];
        }
        $file = dirname(__DIR__) . '/' . $slug . '.html';
        $html = is_file($file) ? file_get_contents($file) : false;
        if ($html === false) {
            return [];
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $fields = [];
        $seen = [];
        $sectionNames = [];
        foreach ($xpath->query('//main//*[@data-edit]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $key = $node->getAttribute('data-edit');
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,59}$/', $key) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $section = $node;
            while ($section !== null && !($section instanceof DOMElement && $section->tagName === 'section')) {
                $section = $section->parentNode;
            }
            if ($section === null) {
                $group = 'Top of page';
            } else {
                $id = $section->getNodePath();
                if (!isset($sectionNames[$id])) {
                    $sectionNames[$id] = self::sectionName($xpath, $section, count($sectionNames) + 1);
                }
                $group = $sectionNames[$id];
            }

            $fields[] = [
                'key' => $key,
                'type' => self::typeLabel($node),
                'group' => $group,
                'default' => self::serialize($node),
            ];
        }
        return $fields;
    }

    private static function sectionName(DOMXPath $xpath, DOMElement $section, int $number): string
    {
        $heading = $xpath->query('.//h1|.//h2|.//h3', $section)->item(0);
        if ($heading !== null) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $heading->textContent));
            if ($text !== '') {
                return 'Section ' . $number . ': ' . (mb_strlen($text) > 60 ? mb_substr($text, 0, 57) . '…' : $text);
            }
        }
        return 'Section ' . $number;
    }

    private static function typeLabel(DOMElement $node): string
    {
        $tag = $node->tagName;
        if (preg_match('/^h[1-6]$/', $tag)) {
            return 'Heading';
        }
        if ($tag === 'p' || $tag === 'blockquote') {
            return 'Paragraph';
        }
        if ($tag === 'a' || $tag === 'button') {
            return 'Button / link text';
        }
        if ($tag === 'li') {
            return 'List item';
        }
        return 'Text';
    }

    /** Original text of an element: line breaks as "\n", em/strong kept as tags. */
    private static function serialize(DOMElement $element): string
    {
        $out = '';
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= preg_replace('/\s+/u', ' ', $child->nodeValue ?? '');
            } elseif ($child instanceof DOMElement) {
                if ($child->tagName === 'br') {
                    $out .= "\n";
                } elseif ($child->tagName === 'em' || $child->tagName === 'strong') {
                    $out .= '<' . $child->tagName . '>'
                        . preg_replace('/\s+/u', ' ', $child->textContent) . '</' . $child->tagName . '>';
                }
            }
        }
        $out = (string) preg_replace('/ *\n */', "\n", $out);
        return trim($out);
    }
}
