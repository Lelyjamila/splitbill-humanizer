<?php
require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hapus'])) db()->prepare('DELETE FROM grup WHERE id=?')->execute([(int)$_POST['hapus']]);
    elseif (isset($_POST['tambah'])) {
        $m = parse_people($_POST['members'] ?? '');
        $n = trim($_POST['name'] ?? '');
        if ($n !== '' && count($m) >= 2) db()->prepare('INSERT INTO grup(name,members) VALUES(?,?)')->execute([$n, implode(', ', $m)]);
    }
    header('Location: grup.php');
    exit;
}
$rows = db()->query('SELECT * FROM grup ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
head('Grup', 'grup');
?>
<h1>Grup</h1>
<p class="muted">Simpan daftar anggota yang sering patungan bareng, lalu pilih di halaman Buat transaksi.</p>
<div class="card"><form method="post">
<label>Nama grup</label><input name="name" required placeholder="Geng Kuliah">
<label>Anggota (pisahkan koma, minimal 2)</label><input name="members" required placeholder="Andi, Budi, Citra">
<button name="tambah" value="1">Simpan grup</button>
</form></div>
<div class="card">
<?php if (!$rows): ?><p class="muted">Belum ada grup.</p>
<?php else: ?><table><tr><th>Grup</th><th>Anggota</th><th></th></tr>
<?php foreach ($rows as $g): ?><tr><td><?= h($g['name']) ?></td><td><?= h($g['members']) ?></td>
<td><form method="post" onsubmit="return confirm('Hapus grup ini?')"><button class="del" name="hapus" value="<?= (int)$g['id'] ?>">Hapus</button></form></td></tr>
<?php endforeach; ?></table><?php endif; ?>
</div>
<?php foot(); ?>