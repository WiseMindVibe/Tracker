<?php
require_once "../src/bootstrap.php";
include "../includes/topbar.php";



/**
 * ============================================================
 * 2. FETCH LAST 100 NOTIFICATIONS
 * ============================================================
 */
$stmt = db()->prepare("
    SELECT
        n.*,
        o.name AS offer_name,
        a.name AS affiliate_name
    FROM notifications n
    JOIN offers o ON o.id = n.offer_id
    JOIN affiliate_programs a ON a.id = n.affiliate_id
    ORDER BY n.created_at DESC
    LIMIT 100
");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ============================================================
 * 1. MARK ALL UNREAD NOTIFICATIONS AS READ
 *    (Happens ONCE when user opens this page)
 * ============================================================
 */
db()->prepare("
    UPDATE notifications
    SET is_read = 1,
        read_at = NOW()
    WHERE is_read = 0
")->execute();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications</title>

<style>
/* ================= Notifications UI ================= */

.notifications-wrapper {
    max-width: 1400px;
    margin: 30px auto;
    padding: 0 20px;
}

.notifications-card {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,.08);
    overflow: hidden;
}

.notifications-header {
    padding: 18px 22px;
    border-bottom: 1px solid #eef0f3;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.notifications-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.notifications-header span {
    color: #6b7280;
    font-size: 13px;
}

.notifications-table {
    width: 100%;
    border-collapse: collapse;
}

.notifications-table thead th {
    text-align: left;
    font-size: 12px;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #6b7280;
    background: #fafafa;
    padding: 14px 16px;
    border-bottom: 1px solid #eef0f3;
}

.notifications-table tbody tr {
    transition: background .15s ease;
}

.notifications-table tbody tr:hover {
    background: #f9fafb;
}

.notifications-table td {
    padding: 14px 16px;
    font-size: 14px;
    border-bottom: 1px solid #f1f3f6;
    vertical-align: middle;
}

/* ===== UNREAD ===== */
.notifications-table tr.unread {
    background: #57b0c7ff;
    font-weight: 600;
}

.unread-dot {
    color: #2563eb;
    font-size: 10px;
}

/* ===== STATUS BADGES ===== */
.status-badge {
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    display: inline-block;
}

.status-confirmed { background: #e6f9f0; color: #067647; }
.status-paid { background: #ecfdf3; color: #027a48; }
.status-open { background: #fff7ed; color: #b45309; }
.status-rejected { background: #fee2e2; color: #991b1b; }

.payout {
    font-weight: 600;
    color: #111827;
}

.time {
    font-family: monospace;
    font-size: 13px;
    color: #374151;
}
</style>
</head>

<body>

<div class="notifications-wrapper">
    <div class="notifications-card">

        <div class="notifications-header">
            <h2>Conversion Notifications</h2>
            <span>Last 100 conversions</span>
        </div>

        <table class="notifications-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Offer</th>
                    <th>Affiliate</th>
                    <th>Status</th>
                    <th>Payout</th>
                    <th>Clicked</th>
                    <th>Received</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($notifications as $n): ?>
                <tr class="<?= $n['is_read'] ? '' : 'unread' ?>">

                    <td>
                        <?php if (!$n['is_read']): ?>
                            <span class="unread-dot">●</span>
                        <?php endif; ?>
                    </td>

                    <td><?= htmlspecialchars($n['offer_name']) ?></td>

                    <td><?= htmlspecialchars($n['affiliate_name']) ?></td>

                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($n['status']) ?>">
                            <?= strtoupper($n['status']) ?>
                        </span>
                    </td>

                    <td class="payout">
                        $<?= number_format($n['payout'], 2) ?>
                    </td>

                    <td class="time"><?= htmlspecialchars($n['click_created_at']) ?></td>

                    <td class="time"><?= htmlspecialchars($n['click_updated_at']) ?></td>

                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>

</body>
</html>