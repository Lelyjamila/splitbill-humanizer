<?php
require __DIR__ . '/lib.php';

$v = [
    'title' => 'Makan Bareng', 'people' => 'Andi, Budi, Citra, Dewi',
    'items' => "Nasi Goreng | 25000 | Andi | Andi\nSate Ayam | 40000 | Andi | Budi, Citra\nEs Teh 4 gelas | 20000 | Budi\nPizza | 120000 | Citra",
    'tax' => setting('tax', 10), 'svc' => setting('svc', 5), 'disc' => 0, 'step' => setting('step', 100),
];

// Buka ulang dari riwayat: index.php?id=3
if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM bills WHERE id=?');
    $st->execute([(int)$_GET['id']]);
    if ($r = $st->fetch(PDO::FETCH_ASSOC)) $v = array_intersect_key($r, $v);
}

$err = null; $res = null; $msg = ''; $notice = ''; $story = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analisis'])) {
    $story = trim($_POST['cerita'] ?? '');
    try {
        if ($story === '') throw new Exception("Tulis ceritanya dulu.");
        $d = gemini_parse($story);
        $lines = []; $ppl = (array)($d['people'] ?? []);
        foreach ($d['items'] as $it) {
            $sh = array_map('trim', (array)($it['sharers'] ?? []));
            $ppl = array_merge($ppl, [$it['payer'] ?? ''], $sh);
            $lines[] = implode(' | ', [trim($it['name'] ?? ''), (int)($it['price'] ?? 0), trim($it['payer'] ?? ''), implode(', ', $sh)]);
        }
        $v['items'] = implode("\n", $lines);
        $v['people'] = implode(', ', array_unique(array_filter(array_map('trim', $ppl))));
        if (!empty($d['title'])) $v['title'] = $d['title'];
        foreach (['tax', 'svc', 'disc'] as $k) if (isset($d[$k]) && is_numeric($d[$k])) $v[$k] = $d[$k];
        $notice = "Hasil analisis AI sudah mengisi form di bawah. Periksa dan koreksi dulu sebelum menghitung.";
    } catch (Exception $e) { $err = $e->getMessage(); }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = array_merge($v, array_intersect_key($_POST, $v));
    try {
        $people = parse_people($v['people']);
        if (count($people) < 2) throw new Exception("Minimal 2 orang.");
        $items = parse_items($v['items'], $people);
        $step = max(1, (int)$v['step']);
        $res = calc($people, $items, (float)$v['tax'], (float)$v['svc'], (float)$v['disc'], $step);
        $msg = humanize($v['title'], $res[3], $res[2]);

        if (isset($_POST['simpan'])) {
            $pdo = db();
            $pdo->beginTransaction();
            $st = $pdo->prepare('INSERT INTO bills(title,people,items,tax,svc,disc,step,total,message) VALUES(?,?,?,?,?,?,?,?,?)');
            $st->execute([$v['title'], $v['people'], $v['items'], (float)$v['tax'], (float)$v['svc'], (float)$v['disc'], $step, $res[3], $msg]);
            $bid = $pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO settlements(bill_id,debtor,creditor,amount) VALUES(?,?,?,?)');
            foreach ($res[2] as [$d, $c, $a]) $ins->execute([$bid, $d, $c, $a]);
            $pdo->commit();
            header('Location: pelunasan.php?s=semua&bill=' . $bid);
            exit;
        }
    } catch (Exception $e) { $err = $e->getMessage(); }
}

head('Buat transaksi', 'buat');
?>
<h1>Buat transaksi</h1>
<p class="muted">Isi rincian transaksi, lalu hitung. Hasilnya bisa disimpan ke riwayat dan pelunasan.</p>
<div class="card"><form method="post">
<label>Ceritakan transaksinya</label>
<textarea name="cerita" rows="5" placeholder="Contoh: Caca bayar tiket masuk 3 orang Rp450.000. Budi bayar bensin dan tol Rp150.000. Tambahkan pajak 10%."><?= h($story) ?></textarea>
<button name="analisis" value="1">✨ Analisis dengan AI</button>
</form></div>
<?php if ($err && isset($_POST['analisis'])): ?><div class="err">⚠️ <?= h($err) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="card" style="border-color:var(--ac)">✨ <?= h($notice) ?></div><?php endif; ?>
<div class="card"><form method="post">
<label>Judul acara</label><input name="title" value="<?= h($v['title']) ?>" required>
<label>Pilih dari grup (opsional)</label>
<select onchange="if(this.value)document.getElementById('people').value=this.value"><option value="">— pilih grup —</option>
<?php foreach (db()->query('SELECT name,members FROM grup ORDER BY name') as $g): ?><option value="<?= h($g['members']) ?>"><?= h($g['name']) ?></option><?php endforeach; ?></select>
<label>Daftar orang (pisahkan koma)</label><input id="people" name="people" value="<?= h($v['people']) ?>" required>
<label>Item (satu baris: item | harga | pembayar | [dibagi ke, kosong = semua])</label>
<textarea name="items" rows="7" required><?= h($v['items']) ?></textarea>
<div class="row">
<div><label>Pajak %</label><input type="number" step="any" name="tax" value="<?= h($v['tax']) ?>"></div>
<div><label>Service %</label><input type="number" step="any" name="svc" value="<?= h($v['svc']) ?>"></div>
<div><label>Diskon %</label><input type="number" step="any" name="disc" value="<?= h($v['disc']) ?>"></div>
<div><label>Pembulatan Rp</label><input type="number" name="step" value="<?= h($v['step']) ?>"></div>
</div>
<button>Hitung &amp; Humanize</button>
<button name="simpan" value="1" class="btn2">Hitung &amp; Simpan ke Riwayat</button>
</form></div>

<?php if ($err && !isset($_POST['analisis'])): ?><div class="err">⚠️ <?= h($err) ?></div><?php endif; ?>
<?php if ($res): ?>
<h2>Rincian per orang</h2>
<table><tr><th>Nama</th><th>Harus bayar</th><th>Sudah bayar</th></tr>
<?php foreach ($res[0] as $p => $o): ?>
<tr><td><?= h($p) ?></td><td><?= rp($o) ?></td><td><?= rp($res[1][$p]) ?></td></tr>
<?php endforeach; ?></table>
<h2>Pesan siap kirim</h2>
<pre id="msg"><?= h($msg) ?></pre>
<button type="button" onclick="navigator.clipboard.writeText(document.getElementById('msg').innerText);this.textContent='Tersalin ✓'">Salin</button>
<a class="btn" target="_blank" href="https://wa.me/?text=<?= rawurlencode($msg) ?>">Kirim ke WhatsApp</a>
<?php endif; ?>
<?php foot(); ?>