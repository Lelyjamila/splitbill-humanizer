<?php
require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus'])) {
    $id = (int)$_POST['hapus'];
    db()->prepare('DELETE FROM settlements WHERE bill_id=?')->execute([$id]);
    db()->prepare('DELETE FROM bills WHERE id=?')->execute([$id]);
    header('Location: riwayat.php');
    exit;
}
head('Riwayat', 'riwayat');

if (isset($_GET['id'])) {
    $st = db()->prepare('SELECT * FROM bills WHERE id=?');
    $st->execute([(int)$_GET['id']]);
    $b = $st->fetch(PDO::FETCH_ASSOC);
    if (!$b) { echo '<div class="err">Data tidak ditemukan.</div>'; foot(); exit; } ?>
    <h1><?= h($b['title']) ?></h1>
    <p class="muted">Total <?= rp($b['total']) ?> · disimpan <?= h($b['created_at']) ?> · peserta: <?= h($b['people']) ?></p>
    <div class="card"><pre id="msg"><?= h($b['message']) ?></pre>
    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('msg').innerText);this.textContent='Tersalin ✓'">Salin</button>
    <a class="btn" target="_blank" href="https://wa.me/?text=<?= rawurlencode($b['message']) ?>">Kirim ke WhatsApp</a>
    <a class="btn btn2" href="pelunasan.php?s=semua&bill=<?= (int)$b['id'] ?>">Lihat pelunasan</a>
    <a class="btn btn2" href="buat.php?id=<?= (int)$b['id'] ?>">Buka &amp; edit ulang</a></div>
    <?php foot(); exit;
}

$rows = db()->query('SELECT b.id,b.title,b.people,b.total,b.created_at,
    (SELECT COUNT(*) FROM settlements s WHERE s.bill_id=b.id) AS n,
    (SELECT COUNT(*) FROM settlements s WHERE s.bill_id=b.id AND s.paid=1) AS l
    FROM bills b ORDER BY b.id DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<h1>Riwayat</h1>
<p class="muted">Semua transaksi yang pernah disimpan.</p>
<div class="card">
<?php if (!$rows): ?><p class="muted">Belum ada transaksi tersimpan.</p>
<?php else: ?><table><tr><th>Tanggal</th><th>Judul</th><th>Total</th><th>Pelunasan</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><?= h($r['created_at']) ?></td>
<td><a href="riwayat.php?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a><br><small><?= h($r['people']) ?></small></td>
<td><?= rp($r['total']) ?></td>
<td><?php if ($r['n'] == 0) echo '<span class="ok">Impas</span>';
elseif ($r['l'] == $r['n']) echo '<span class="ok">Lunas ✓</span>';
else echo '<span class="no">' . (int)$r['l'] . '/' . (int)$r['n'] . ' lunas</span>'; ?></td>
<td><form method="post" onsubmit="return confirm('Hapus transaksi ini beserta data pelunasannya?')"><button class="del" name="hapus" value="<?= (int)$r['id'] ?>">Hapus</button></form></td>
</tr><?php endforeach; ?></table><?php endif; ?>
</div>
<?php foot(); ?>