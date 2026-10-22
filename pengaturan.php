<?php
require __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('INSERT OR REPLACE INTO settings(k,v) VALUES(?,?)');
    foreach (['tax', 'svc', 'step'] as $k) $st->execute([$k, max(0, (float)($_POST[$k] ?? 0))]);
    $model = trim($_POST['gemini_model'] ?? '');
    if (preg_match('/^[A-Za-z0-9._-]+$/', $model)) $st->execute(['gemini_model', $model]);
    $key = trim($_POST['gemini_key'] ?? '');
    if ($key !== '') $st->execute(['gemini_key', $key]);
    if (isset($_POST['hapus_key'])) db()->prepare("DELETE FROM settings WHERE k='gemini_key'")->execute();
    header('Location: pengaturan.php?ok=1');
    exit;
}
$key = setting('gemini_key', '');
head('Pengaturan', 'pengaturan');
?>
<h1>Pengaturan</h1>
<p class="muted">Nilai bawaan dan koneksi AI.</p>
<?php if (isset($_GET['ok'])): ?><p class="ok">Pengaturan tersimpan ✓</p><?php endif; ?>
<form method="post">
<div class="card"><h3 style="margin-top:0">Nilai bawaan transaksi</h3><div class="row">
<div><label>Pajak bawaan %</label><input type="number" step="any" name="tax" value="<?= h(setting('tax', 10)) ?>"></div>
<div><label>Service bawaan %</label><input type="number" step="any" name="svc" value="<?= h(setting('svc', 5)) ?>"></div>
<div><label>Pembulatan Rp</label><input type="number" name="step" value="<?= h(setting('step', 100)) ?>"></div>
</div></div>
<div class="card"><h3 style="margin-top:0">Gemini API</h3>
<p class="muted">Buat key di <a target="_blank" href="https://aistudio.google.com/apikey">aistudio.google.com/apikey</a>.
Alternatif yang lebih aman: set environment variable <code>GEMINI_API_KEY</code> di server.</p>
<?php if (getenv('GEMINI_API_KEY')): ?><p class="ok">Memakai GEMINI_API_KEY dari environment ✓</p>
<?php elseif ($key !== ''): ?><p class="ok">Key tersimpan (berakhiran <?= h(substr($key, -4)) ?>)</p>
<?php else: ?><p class="no">Belum ada key.</p><?php endif; ?>
<label>API key (kosongkan jika tidak ingin mengubah)</label><input type="password" name="gemini_key" autocomplete="off" placeholder="AIza...">
<label>Nama model</label><input name="gemini_model" value="<?= h(setting('gemini_model', 'gemini-2.5-flash')) ?>">
<?php if ($key !== ''): ?><label style="font-weight:400"><input type="checkbox" name="hapus_key" value="1" style="width:auto"> Hapus key yang tersimpan</label><?php endif; ?>
</div>
<button>Simpan pengaturan</button>
</form>
<?php foot(); ?>