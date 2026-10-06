<?php
// Standalone: php tests/css_roundtrip_test.php
// Simulates the core's input layers (HTML-encode '<' '>' + mysqli-style SQL escaping)
// and checks the custom CSS survives add -> DB -> CSS file unchanged.
class CommonDBTM {}
class PluginWhitelabelPalette { const FIELDS = []; }
require __DIR__ . '/../inc/theme.class.php';

class FakeDB { function escape($s) { return strtr($s, ["\\"=>"\\\\","\0"=>"\\0","\n"=>"\\n","\r"=>"\\r","'"=>"\\'",'"'=>'\\"',"\x1a"=>"\\Z"]); } }
$DB = new FakeDB();
function mysqlUnescape($s) { // what MySQL does reading the literal back
    return preg_replace_callback('/\\\\(.)/s', fn($m) => ['n'=>"\n",'r'=>"\r",'0'=>"\0",'Z'=>"\x1a"][$m[1]] ?? $m[1], $s);
}
function viaCore($css) { global $DB; return $DB->escape(str_replace(['<','>'], ['&lt;','&gt;'], $css)); }
function save($css) { global $DB; // prepareInput path, then MySQL storage
    $in = viaCore($css);
    $clean = PluginWhitelabelTheme::sanitizeCustomCss(PluginWhitelabelTheme::decodeSubmittedCss($in));
    return mysqlUnescape($DB->escape($clean));
}
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? "PASS" : "FAIL") . " $name\n"; if (!$ok) $fail++; }

$css = "/* é — note */\r\ntable.table tbody.table-light > tr > td {\r\n  color: #fff !important;\r\n}\r\nb::before { content: \"\\00d7\"; }\r\na[href='x'] { top: 0 }\r\n";
$expected = trim($css);
check('multi-line CSS with >, quotes, \\00d7 round-trips exactly', save($css) === $expected);
check('no literal "rn" corruption', strpos(save($css), 'rn') === false || strpos(save($css), "\r\n") !== false);
check('child combinator kept', strpos(save($css), 'tbody.table-light > tr > td') !== false);
check('CSS escape kept', strpos(save($css), '"\\00d7"') !== false);
check('legacy row with &gt; repaired on regenerate', PluginWhitelabelTheme::sanitizeCustomCss("a &gt; b {x:y}") === "a > b {x:y}");
check('@import stripped', strpos(PluginWhitelabelTheme::sanitizeCustomCss("@import url(//evil.test/x.css);\na{b:c}"), '@import') === false);
check('-moz-binding stripped', strpos(PluginWhitelabelTheme::sanitizeCustomCss("a{-moz-binding: url(x)}"), 'binding') === false);
check('scroll-behavior kept', strpos(PluginWhitelabelTheme::sanitizeCustomCss("html{scroll-behavior: smooth}"), 'scroll-behavior: smooth') !== false);
check('script tag stripped', stripos(save("a{}<script>alert(1)</script>"), '<script') === false);
check('javascript: stripped', stripos(save("a{background:url(javascript:alert(1))}"), 'javascript:') === false);
check('quote cannot break SQL literal', strpos(viaCore("a{content:'x'}"), "\\'") !== false);
exit($fail ? 1 : 0);
