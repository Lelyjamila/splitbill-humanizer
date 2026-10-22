<?php
require __DIR__ . '/lib.php';
$q = fn($sql) => db()->query($sql)->fetchColumn();
$nBill = $q('SELECT COUNT(*) FROM bills');
$sumBill = $q('SELECT COALESCE(SUM(total),0) FROM bills');
$nUn = $q('SELECT COUNT(*) FROM settlements WHERE paid=0');
$sumUn = $q('SELECT COALESCE(SUM(amount),0) FROM settlements WHERE paid=0');
$sumPaid = $q('SELECT COALESCE(SUM(amount),0) FROM settlements WHERE paid=1');
$recent = db()->query('SELECT id,title,total,created_at FROM bills ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
head('Dashboard', 'dashboard');
?>
<h1>Dashboard</h1>
<p class="muted">Ringkasan patungan dan pelunasan Anda.</p>
<div class="grid">
<div class="card"><span class="muted">Total transaksi</span><b><?= (int)$nBill ?></b></div>
<div class="card"><span class="muted">Total nilai</span><b><?= rp($sumBill) ?></b></div>
<div class="card"><span class="muted">Belum lunas (<?= (int)$nUn ?> transfer)</span><b class="no"><?= rp($sumUn) ?></b></div>
<div class="card"><span class="muted">Sudah terkumpul</span><b class="ok"><?= rp($sumPaid) ?></b></div>
</div>
<div class="card">
<h3 style="margin-top:0">Transaksi terbaru</h3>
<?php if (!$recent): ?><p class="muted">Belum ada transaksi.</p>
<?php else: ?><table><tr><th>Tanggal</th><th>Judul</th><th>Total</th></tr>
<?php foreach ($recent as $r): ?><tr><td><?= h($r['created_at']) ?></td><td><a href="riwayat.php?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a></td><td><?= rp($r['total']) ?></td></tr><?php endforeach; ?>
</table><?php endif; ?>
<a class="btn" href="buat.php">+ Buat transaksi</a>
</div>
<?php foot(); ?>