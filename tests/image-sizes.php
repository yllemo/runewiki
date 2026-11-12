<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$parser = new Parser();
foreach (['{{projekt:bild.png?300|Bild}}', '![Bild](/images/projekt/bild.png?300)'] as $markdown) {
    $html = $parser->toHtml($markdown);
    check(str_contains($html, 'src="/images/projekt/bild.png"') && str_contains($html, 'width="300"'), 'Width and clean image source');
    check(str_contains($html, 'gbg-lightbox-img') && str_contains($html, 'height:auto'), 'Lightbox and proportional scaling preserved');
}
foreach (['{{bild.png?300x200}}', '![Bild](/images/bild.png?300x200)'] as $markdown) {
    $html = $parser->toHtml($markdown);
    check(str_contains($html, 'width="300"') && str_contains($html, 'height="200"') && str_contains($html, 'aspect-ratio:300/200'), 'Width/height box');
}
check(str_contains($parser->toHtml('![Bild](https://example.com/image?width=300)'), 'src="https://example.com/image?width=300"'), 'Ordinary query parameters preserved');
check(!str_contains($parser->toHtml('![Bild](/images/a.png?0x200)'), ' width='), 'Invalid zero size ignored');
check(!str_contains($parser->toHtml('{{bild.png}}'), ' width='), 'Unspecified image size unchanged');
echo "PASS: image sizes, clean sources, responsive styling, lightbox and query preservation\n";
