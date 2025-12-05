<?php
// reporting.php  (controller)
require_once __DIR__ . '/../src/bootstrap.php';
include __DIR__ . "/../includes/topbar.php";

// parse incoming values (datetime-local returns YYYY-MM-DDTHH:MM)
$rawStart = $_GET['start'] ?? null;
$rawEnd   = $_GET['end']   ?? null;

// defaults: last 30 days
$defaultStart = date('Y-m-d 00:00:00', strtotime('-30 days'));
$defaultEnd   = date('Y-m-d 23:59:59');

// convert to MySQL datetime (seconds appended)
$start = parseDateInput($rawStart, $defaultStart);
$end   = parseDateInput($rawEnd,   $defaultEnd);

// fetch rows and build nested report
$rows = getReportRows($start, $end);
$report = buildReport($rows);


// views/reporting.php  — expects $report, $start, $end from controller
// Convert datetimes back to "datetime-local" format for form values
function toDatetimeLocal(string $dt): string {
    $t = strtotime($dt);
    if ($t === false) return '';
    return date('Y-m-d\TH:i', $t);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Reporting — Offer → Campaign → OS → Browser</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#f6f7fb;--card:#fff;--muted:#666;--accent:#1f8feb}
  body{font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);margin:0;padding:18px;color:#222}
  h1{margin:0 0 14px 0;font-size:20px}
  .controls{display:flex;gap:10px;align-items:center;margin-bottom:12px}
  .card{background:var(--card);border-radius:8px;padding:10px;box-shadow:0 1px 2px rgba(0,0,0,0.05)}
  .offer{background:#e6ffef;border-left:4px solid #2ecc71;padding:10px;margin:8px 0;cursor:pointer}
  .campaign{background:#eef7ff;border-left:4px solid var(--accent);padding:8px;margin:6px 12px;cursor:pointer}
  .os{background:#fafafa;padding:8px;margin:6px 24px;cursor:pointer;border-left:3px solid #ddd}
  .browser{background:#fff;padding:8px;margin:6px 36px;border-left:1px solid #eee}
  .meta{font-size:13px;color:var(--muted);margin-left:8px}
  .numbers{font-weight:600;margin-left:8px}
  .muted{color:var(--muted);font-size:13px;margin-top:6px}
  form input{padding:6px;border-radius:4px;border:1px solid #ddd}
  button{padding:7px 10px;border-radius:6px;border:none;background:var(--accent);color:#fff;cursor:pointer}
  @media (max-width:720px){ .controls{flex-direction:column;align-items:flex-start} }
</style>

<script>
function toggleId(id){
  const el = document.getElementById(id);
  if(!el) return;
  el.style.display = el.style.display === 'none' || el.style.display === '' ? 'block' : 'none';
}
</script>
</head>
<body>
  <h1>Reporting</h1>

  <div class="card controls">
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <label>Start</label>
      <input type="datetime-local" name="start" value="<?= htmlspecialchars(toDatetimeLocal($start)) ?>">
      <label>End</label>
      <input type="datetime-local" name="end" value="<?= htmlspecialchars(toDatetimeLocal($end)) ?>">
      <button type="submit">Apply</button>
      <button type="button" onclick="location.href='reporting.php'">Reset</button>
    </form>
    <div class="muted">Showing clicks between <strong><?= htmlspecialchars($start) ?></strong> and <strong><?= htmlspecialchars($end) ?></strong></div>
  </div>

  <?php if (empty($report)): ?>
    <div class="card muted" style="margin-top:12px">No clicks found in this date range.</div>
  <?php else: ?>
    <?php foreach ($report as $offerId => $offer):
        $offerDom = "offer_{$offerId}";
        $offerProfit = $offer['revenue'] - $offer['cost'];
        $offerCr = $offer['clicks'] ? ($offer['conversions'] / $offer['clicks'] * 100) : 0;
        $offerRoi = $offer['cost'] ? ($offerProfit / $offer['cost'] * 100) : 0;
    ?>
      <div class="offer" onclick="toggleId('<?= $offerDom ?>')">
        <strong><?= htmlspecialchars($offer['offer_name']) ?></strong>
        <span class="meta">Clicks: <span class="numbers"><?= $offer['clicks'] ?></span></span>
        <span class="meta">Conv: <span class="numbers"><?= $offer['conversions'] ?></span></span>
        <span class="meta">Revenue: <span class="numbers">$<?= number_format($offer['revenue'],2) ?></span></span>
        <span class="meta">Cost: <span class="numbers">$<?= number_format($offer['cost'],2) ?></span></span>
        <span class="meta">Profit: <span class="numbers">$<?= number_format($offerProfit,2) ?></span></span>
        <span class="meta">CR: <span class="numbers"><?= number_format($offerCr,2) ?>%</span></span>
        <span class="meta">ROI: <span class="numbers"><?= number_format($offerRoi,2) ?>%</span></span>
      </div>

      <div id="<?= $offerDom ?>" style="display:none">
        <?php foreach ($offer['campaigns'] as $campId => $camp):
            $campDom = "{$offerDom}_camp_{$campId}";
            $campProfit = $camp['revenue'] - $camp['cost'];
            $campCr = $camp['clicks'] ? ($camp['conversions'] / $camp['clicks'] * 100) : 0;
            $campRoi = $camp['cost'] ? ($campProfit / $camp['cost'] * 100) : 0;
        ?>
          <div class="campaign" onclick="toggleId('<?= $campDom ?>')">
            <strong>Campaign:</strong> <?= htmlspecialchars($campId) ?>
            <span class="meta">Clicks: <span class="numbers"><?= $camp['clicks'] ?></span></span>
            <span class="meta">Conv: <span class="numbers"><?= $camp['conversions'] ?></span></span>
            <span class="meta">Revenue: <span class="numbers">$<?= number_format($camp['revenue'],2) ?></span></span>
            <span class="meta">Cost: <span class="numbers">$<?= number_format($camp['cost'],2) ?></span></span>
            <span class="meta">Profit: <span class="numbers">$<?= number_format($campProfit,2) ?></span></span>
            <span class="meta">CR: <span class="numbers"><?= number_format($campCr,2) ?>%</span></span>
            <span class="meta">ROI: <span class="numbers"><?= number_format($campRoi,2) ?>%</span></span>
          </div>

          <div id="<?= $campDom ?>" style="display:none">
            <?php foreach ($camp['os'] as $osName => $osData):
                $osDom = "{$campDom}_os_" . preg_replace('/[^a-z0-9_]/i','_',$osName);
                $osProfit = $osData['revenue'] - $osData['cost'];
                $osCr = $osData['clicks'] ? ($osData['conversions'] / $osData['clicks'] * 100) : 0;
                $osRoi = $osData['cost'] ? ($osProfit / $osData['cost'] * 100) : 0;
            ?>
              <div class="os" onclick="toggleId('<?= $osDom ?>')">
                <strong>OS:</strong> <?= htmlspecialchars($osName) ?>
                <span class="meta">Clicks: <span class="numbers"><?= $osData['clicks'] ?></span></span>
                <span class="meta">Conv: <span class="numbers"><?= $osData['conversions'] ?></span></span>
                <span class="meta">Revenue: <span class="numbers">$<?= number_format($osData['revenue'],2) ?></span></span>
                <span class="meta">Cost: <span class="numbers">$<?= number_format($osData['cost'],2) ?></span></span>
                <span class="meta">Profit: <span class="numbers">$<?= number_format($osProfit,2) ?></span></span>
                <span class="meta">CR: <span class="numbers"><?= number_format($osCr,2) ?>%</span></span>
                <span class="meta">ROI: <span class="numbers"><?= number_format($osRoi,2) ?>%</span></span>
              </div>

              <div id="<?= $osDom ?>" style="display:none">
                <?php foreach ($osData['browsers'] as $brName => $brData):
                    $brProfit = $brData['revenue'] - $brData['cost'];
                    $brCr = $brData['clicks'] ? ($brData['conversions'] / $brData['clicks'] * 100) : 0;
                ?>
                  <div class="browser">
                    <strong>Browser:</strong> <?= htmlspecialchars($brName) ?>
                    <span class="meta">Clicks: <span class="numbers"><?= $brData['clicks'] ?></span></span>
                    <span class="meta">Conv: <span class="numbers"><?= $brData['conversions'] ?></span></span>
                    <span class="meta">Revenue: <span class="numbers">$<?= number_format($brData['revenue'],2) ?></span></span>
                    <span class="meta">Cost: <span class="numbers">$<?= number_format($brData['cost'],2) ?></span></span>
                    <span class="meta">Profit: <span class="numbers">$<?= number_format($brProfit,2) ?></span></span>
                    <span class="meta">CR: <span class="numbers"><?= number_format($brCr,2) ?>%</span></span>
                  </div>
                <?php endforeach; ?>
              </div>

            <?php endforeach; ?>
          </div>

        <?php endforeach; ?>
      </div>

    <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
