<?php
require __DIR__ . '/lib.php';
$s = $_GET['s'] ?? 'belum';
if (!in_array($s, ['belum', 'lunas', 'semua'])) $s = 'belum';
$bill = (int)($_GET['bill'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lunas'])) {
    $val = (int)($_POST['nilai'] ?? 1) ? 1 : 0;
    db()->prepare('UPDATE settlements SET paid=?, paid_at=? WHERE id=?')
        ->execute([$val, $val ? date('Y-m-d H:i') : null, (int)$_POST['lunas']]);
    header('Location: pelunasan.php?s=' . $s . '&bill=' . $bill);
    exit;
}

$w = []; $args = [];
if ($s === 'belum') $w[] = 's.paid=0';
if ($s === 'lunas') $w[] = 's.paid=1';
if ($bill) { $w[] = 's.bill_id=?'; $args[] = $bill; }
$st = db()->prepare('SELECT s.*, b.title FROM settlements s JOIN bills b ON b.id=s.bill_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY s.id DESC');
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$unpaid = array_filter($rows, fn($r) => !$r['paid']);
head('Pelunasan', 'pelunasan');
?>
<h1>Pelunasan</h1>
<p class="muted">Pantau siapa yang sudah dan belum transfer.<?= $bill ? ' Menampilkan satu transaksi. <a href="pelunasan.php?s=' . $s . '">Tampilkan semua</a>' : '' ?></p>
<?php foreach (['belum' => 'Belum lunas', 'lunas' => 'Lunas', 'semua' => 'Semua'] as $k => $l): ?>
<a class="btn <?= $k === $s ? '' : 'btn2' ?>" style="margin-top:0" href="pelunasan.php?s=<?= $k ?>&bill=<?= $bill ?>"><?= $l ?></a>
<?php endforeach; ?>
<div class="card">
<?php if (!$rows): ?><p class="muted">Tidak ada data.</p>
<?php else: ?><table><tr><th>Transaksi</th><th>Dari</th><th>Ke</th><th>Jumlah</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr>
<td><a href="riwayat.php?id=<?= (int)$r['bill_id'] ?>"><?= h($r['title']) ?></a></td>
<td><?= h($r['debtor']) ?></td><td><?= h($r['creditor']) ?></td><td><?= rp($r['amount']) ?></td>
<td><?= $r['paid'] ? '<span class="ok">Lunas ✓</span><br><small>' . h($r['paid_at']) . '</small>' : '<span class="no">Belum</span>' ?></td>
<td><form method="post"><input type="hidden" name="nilai" value="<?= $r['paid'] ? 0 : 1 ?>">
<button class="sm <?= $r['paid'] ? 'btn2' : '' ?>" name="lunas" value="<?= (int)$r['id'] ?>"><?= $r['paid'] ? 'Batalkan' : 'Tandai lunas' ?></button></form></td>
</tr><?php endforeach; ?></table><?php endif; ?>
</div>
<?php if ($bill && $unpaid): $rm = reminder(reset($unpaid)['title'], $unpaid); ?>
<div class="card"><h3 style="margin-top:0">Pesan pengingat</h3>
<pre id="rm"><?= h($rm) ?></pre>
<button type="button" onclick="navigator.clipboard.writeText(document.getElementById('rm').innerText);this.textContent='Tersalin ✓'">Salin</button>
<a class="btn" target="_blank" href="https://wa.me/?text=<?= rawurlencode($rm) ?>">Kirim ke WhatsApp</a></div>
<?php endif; ?>
<?php foot(); ?>