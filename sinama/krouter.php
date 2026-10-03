<?php
$u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$d = getenv('KTEST_DIR') ?: '/home/claude/ktest';
if (preg_match('#^/api/(.*)$#', $u, $m)) { $_SERVER['PATH_INFO'] = '/' . $m[1]; require $d . '/api/index.php'; return true; }
if ($u === '/sitemap.xml') { require $d . '/sitemap.php'; return true; }
if ($u === '/robots.txt') { require $d . '/robots.php'; return true; }
if ($u === '/license.xml') { require $d . '/lisans.php'; return true; }
if ($u === '/llms.txt') { require $d . '/llms.php'; return true; }
if (preg_match('#^/([a-f0-9]{8,128})\.txt$#', $u, $mIN)) { $_GET['k'] = $mIN[1]; require $d . '/indexnow.php'; return true; }
if ($u === '/oai') { require $d . '/oai.php'; return true; }
if (preg_match('#^/10\.00001/(.+)$#', $u, $m)) { $_GET['doi'] = $m[1]; require $d . '/yazi.php'; return true; }
if (preg_match('#^/tamga/(.+)$#', $u, $m)) { $_GET['doi'] = $m[1]; require $d . '/yazi.php'; return true; }
if (preg_match('#^/kisi/([a-z0-9\-]+)/?$#', $u, $m)) { $_GET['k'] = $m[1]; require $d . '/kisi.php'; return true; }
if (preg_match('#^/resim/(.+)$#', $u, $m)) { $_GET['r'] = $m[1]; require $d . '/resim.php'; return true; }
if ($u === '/' ) { require $d . '/index.php'; return true; }
if (is_file($d . $u) && !is_dir($d . $u)) return false;
if (is_file($d . $u . '.php')) { require $d . $u . '.php'; return true; }
require $d . '/hata.php';
return true;
