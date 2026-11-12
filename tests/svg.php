<?php
spl_autoload_register(static function ($class) { require_once __DIR__ . '/../core/' . $class . '.php'; });
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/wiki-svg-' . bin2hex(random_bytes(8));
mkdir($root);
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><linearGradient id="g"><stop stop-color="#fff"/></linearGradient></defs><rect width="100" height="100" fill="url(#g)"/></svg>';
try {
    check(SvgDocument::validate($svg) === $svg, 'Static SVG preserved');
    foreach ([
        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject/></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><use href="https://example.com/x.svg#a"/></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:url(https://example.com/x)"/></svg>',
        '<!DOCTYPE svg [<!ENTITY test SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&test;</svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="href"/></svg>',
        '<svg>',
    ] as $bad) {
        $denied = false; try { SvgDocument::validate($bad); } catch (RuntimeException) { $denied = true; }
        check($denied, 'Active, external or invalid SVG rejected');
    }
    foreach (['../bad.svg', 'project:../bad.svg', 'a\\b.svg', ':bad.svg', '.hidden.svg', 'bad.php'] as $bad) {
        $denied = false; try { SvgDocument::id($bad); } catch (RuntimeException) { $denied = true; }
        check($denied, 'Invalid media path rejected');
    }
    $id = SvgDocument::id('project:diagram.svg');
    $saved = SvgDocument::save($root . '/images', $root . '/locks', $id, $svg, '');
    check(file_get_contents($root . '/images/project/diagram.svg') === $svg, 'Correct folder and exact bytes');
    check(SvgDocument::read($root . '/images', $id)['revision'] === $saved['revision'], 'Read revision');
    $denied = false; try { SvgDocument::save($root . '/images', $root . '/locks', $id, $svg, ''); } catch (RuntimeException) { $denied = true; }
    check($denied, 'Accidental overwrite blocked');
    $updated = str_replace('100', '200', $svg);
    SvgDocument::save($root . '/images', $root . '/locks', $id, $updated, $saved['revision']);
    $denied = false; try { SvgDocument::save($root . '/images', $root . '/locks', $id, $svg, $saved['revision']); } catch (RuntimeException) { $denied = true; }
    check($denied, 'Stale edit blocked');
    $parser = new Parser();
    check(str_contains($parser->toHtml("```svg\n$svg\n```"), 'src="data:image/svg+xml;base64,'), 'SVG code renders as isolated image');
    $html = $parser->toHtml("```svg\n<svg onload=\"alert(1)\"/>\n```");
    check(!str_contains($html, '<svg') && str_contains($html, '&lt;svg'), 'Unsafe SVG shown as escaped code');
    echo "PASS: static SVG validation, paths, storage, conflict detection and isolated Markdown rendering\n";
} finally {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) { if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($root);
}
