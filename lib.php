<?php
// lib.php - logika inti, database SQLite, layout
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp' . number_format(round($n), 0, ',', '.'); }

function db() {
    static $pdo;
    if (!$pdo) {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        $pdo = new PDO('sqlite:' . $dir . '/splitbill.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS bills(
            id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, people TEXT, items TEXT,
            tax REAL, svc REAL, disc REAL, step INTEGER, total REAL, message TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settlements(
            id INTEGER PRIMARY KEY AUTOINCREMENT, bill_id INTEGER NOT NULL, debtor TEXT, creditor TEXT,
            amount REAL, paid INTEGER DEFAULT 0, paid_at TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS grup(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, members TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT)");
    }
    return $pdo;
}

function parse_people($text) {
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $text)))));
}

function parse_items($text, $people) {
    $map = [];
    foreach ($people as $p) $map[strtolower($p)] = $p;
    $items = [];
    foreach (preg_split('/\R/', trim($text)) as $i => $line) {
        $line = trim($line);
        if ($line === '') continue;
        $n = $i + 1;
        $p = array_map('trim', explode('|', $line));
        if (count($p) < 3) throw new Exception("Baris $n: format 'item | harga | pembayar | [dibagi ke]'");
        $payer = $map[strtolower($p[2])] ?? null;
        if (!$payer) throw new Exception("Baris $n: pembayar '{$p[2]}' tidak ada di daftar orang");
        $sharers = [];
        if (!empty($p[3])) {
            foreach (explode(',', $p[3]) as $s) {
                $s = $map[strtolower(trim($s))] ?? null;
                if (!$s) throw new Exception("Baris $n: nama pada 'dibagi ke' tidak dikenal");
                $sharers[] = $s;
            }
        }
        $items[] = ['name' => $p[0], 'price' => (float)preg_replace('/\D/', '', $p[1]), 'payer' => $payer, 'sharers' => $sharers];
    }
    if (!$items) throw new Exception("Belum ada item.");
    return $items;
}

function calc($people, $items, $tax, $svc, $disc, $step) {
    $f = (1 + $tax / 100 + $svc / 100) * (1 - $disc / 100);
    $owed = array_fill_keys($people, 0.0);
    $paid = $owed;
    foreach ($items as $it) {
        $sh = $it['sharers'] ?: $people;
        foreach ($sh as $p) $owed[$p] += $it['price'] * $f / count($sh);
        $paid[$it['payer']] += $it['price'] * $f;
    }
    $cr = []; $db = [];
    foreach ($people as $p) {
        $b = $paid[$p] - $owed[$p];
        if ($b > 0.5) $cr[$p] = $b; elseif ($b < -0.5) $db[$p] = -$b;
    }
    $tx = [];
    while ($cr && $db) {
        arsort($cr); arsort($db);
        $c = array_key_first($cr); $d = array_key_first($db);
        $a = min($cr[$c], $db[$d]);
        $r = round($a / $step) * $step;
        if ($r > 0) $tx[] = [$d, $c, $r];
        $cr[$c] -= $a; $db[$d] -= $a;
        if ($cr[$c] < 0.5) unset($cr[$c]);
        if ($db[$d] < 0.5) unset($db[$d]);
    }
    return [$owed, $paid, $tx, array_sum(array_column($items, 'price')) * $f];
}

function humanize($title, $total, $tx) {
    $open = ["Halo geng! Makasih ya udah seru-seruan bareng 🙌", "Hai semua! Waktunya beresin tagihan, santai aja ya 😄", "Guys, rekap patungan udah jadi nih ✨"];
    $line = ["%s, tolong transfer %s ke %s ya 🙏", "%s kirim %s ke %s dulu ya, makasih banyak!", "%s, jangan lupa transfer %s ke %s ya 😊"];
    $m = "🧾 *$title*\nTotal: " . rp($total) . "\n\n" . $open[array_rand($open)] . "\n\n";
    if (!$tx) return $m . "Semua sudah impas, tidak ada yang perlu transfer 🎉";
    foreach ($tx as [$d, $c, $a]) $m .= "• " . sprintf($line[array_rand($line)], $d, rp($a), $c) . "\n";
    return $m . "\nKalau ada yang keliru kabarin ya. Salam kenyang! 🍽️";
}

date_default_timezone_set('Asia/Jakarta');

function reminder($title, $unpaid) {
    $m = "⏰ *Pengingat: $title*\n\nHalo! Mau ngingetin pelan-pelan, yang belum lunas:\n\n";
    foreach ($unpaid as $s) $m .= "• {$s['debtor']} → {$s['creditor']}: " . rp($s['amount']) . "\n";
    return $m . "\nKalau sudah transfer, kabarin ya. Makasih banyak! 🙏";
}

function gemini_parse($story) {
    $key = getenv('GEMINI_API_KEY') ?: setting('gemini_key', '');
    if ($key === '') throw new Exception("API key Gemini belum diisi. Isi di menu Pengaturan.");
    if (!function_exists('curl_init')) throw new Exception("Ekstensi cURL PHP belum aktif (aktifkan extension=curl di php.ini).");
    $model = setting('gemini_model', 'gemini-2.5-flash');
    $prompt = "Ubah cerita transaksi patungan berikut menjadi JSON VALID tanpa teks lain, dengan bentuk:\n"
        . '{"title":string,"people":[string],"items":[{"name":string,"price":integer,"payer":string,"sharers":[string]}],"tax":number|null,"svc":number|null,"disc":number|null}' . "\n"
        . "Aturan: price = total harga item dalam rupiah, bilangan bulat tanpa titik. payer = orang yang membayar item itu. "
        . "sharers = orang yang ikut menanggung item; kosongkan [] jika dibagi rata ke semua orang atau tidak disebut. "
        . "people = semua nama yang disebut, ejaan konsisten dengan payer dan sharers. "
        . "tax/svc/disc dalam persen; isi null jika tidak disebut. Jangan mengarang item atau angka yang tidak ada di cerita.\n\nCERITA:\n" . $story;
    $body = json_encode(['contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1]]);
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key]]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    if ($out === false) throw new Exception("Gagal terhubung ke Gemini: $cerr");
    $j = json_decode($out, true);
    if ($code === 429) throw new Exception("Kuota atau batas laju Gemini terlampaui (429). Tunggu sebentar lalu coba lagi.");
    if ($code === 403 || ($code === 400 && stripos($j['error']['message'] ?? '', 'API key') !== false))
        throw new Exception("API key tidak valid atau tidak punya akses ($code). Periksa di menu Pengaturan.");
    if ($code >= 400) throw new Exception("Gemini menolak permintaan ($code): " . ($j['error']['message'] ?? 'tanpa pesan'));
    $txt = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $txt = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $txt));
    $d = json_decode($txt, true);
    if (!is_array($d) || empty($d['items'])) throw new Exception("AI belum menghasilkan rincian yang valid. Coba tulis ceritanya lebih jelas.");
    return $d;
}

function setting($k, $d) {
    $s = db()->prepare('SELECT v FROM settings WHERE k=?');
    $s->execute([$k]);
    $r = $s->fetchColumn();
    return $r === false ? $d : $r;
}

function head($t, $active = '') {
    $nav = ['index.php' => ['dashboard', 'Dashboard'], 'grup.php' => ['grup', 'Grup'], 'buat.php' => ['buat', 'Buat transaksi'],
            'riwayat.php' => ['riwayat', 'Riwayat'], 'pelunasan.php' => ['pelunasan', 'Pelunasan'], 'pengaturan.php' => ['pengaturan', 'Pengaturan']]; ?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($t) ?> - SplitBill Humanizer</title>
<style>
:root{--bg:#0e0f1d;--card:#1a1b30;--side:#151629;--line:#2a2b45;--tx:#f2f3ff;--mu:#a0a3c0;--ac:#8b8cf7;--ok:#4ade80;--no:#ff7a5c}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--tx);font-family:system-ui,sans-serif}
.app{display:flex;min-height:100vh}
aside{width:240px;background:var(--side);border-right:1px solid var(--line);padding:24px 16px;flex-shrink:0}
.brand{font-size:22px;font-weight:700;padding:0 8px 24px}.brand small{display:block;font-size:14px;font-weight:400;color:var(--mu)}
aside a{display:block;padding:12px 16px;margin-bottom:6px;border-radius:10px;color:var(--mu);text-decoration:none;font-size:17px}
aside a:hover{color:var(--tx)}aside a.on{background:var(--ac);color:#12132a;font-weight:600}
main{flex:1;max-width:1000px;padding:32px 40px}
h1{font-size:34px;margin:0 0 6px}.muted,small{color:var(--mu)}a{color:var(--ac)}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;margin-top:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:20px}.grid .card{margin:0}.grid b{font-size:26px;display:block;margin-top:6px}
label{display:block;margin:14px 0 6px;font-weight:600}
input,textarea,select{width:100%;padding:10px 12px;background:var(--bg);color:var(--tx);border:1px solid var(--line);border-radius:10px;font:inherit}
.row{display:flex;gap:12px}.row>div{flex:1}
button,.btn{margin:16px 8px 0 0;padding:12px 20px;border:0;border-radius:10px;background:var(--ac);color:#12132a;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.btn2{background:#2a2b45;color:var(--tx)}.del{background:#5a2330;color:#ffb3b3;margin:0;padding:6px 12px}.sm{margin:0;padding:6px 12px}
table{width:100%;border-collapse:collapse}th,td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left}th{color:var(--mu);font-weight:500}
.ok{color:var(--ok);font-weight:600}.no{color:var(--no);font-weight:600}
.err{background:#3a2020;color:var(--no);padding:12px;border-radius:10px;margin-top:16px}
pre{background:var(--bg);border:1px solid var(--line);padding:14px;border-radius:10px;white-space:pre-wrap}
@media(max-width:760px){.app{flex-direction:column}aside{width:auto;display:flex;flex-wrap:wrap;gap:6px;padding:12px}.brand{width:100%;padding:0 8px 8px}aside a{margin:0;padding:8px 12px;font-size:14px}main{padding:20px 16px}.row{flex-direction:column}}
</style></head><body><div class="app">
<aside><div class="brand">SplitBill<small>Humanizer</small></div>
<?php foreach ($nav as $url => [$key, $label]): ?><a href="<?= $url ?>" class="<?= $key === $active ? 'on' : '' ?>"><?= $label ?></a><?php endforeach; ?>
</aside><main>
<?php }

function foot() { echo '</main></div></body></html>'; }