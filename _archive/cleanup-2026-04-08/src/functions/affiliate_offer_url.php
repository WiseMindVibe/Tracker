<?php

/**
 * Build tracked affiliate URLs (shared by redirect.php and go.php).
 */

function affiliateProgramSlugFromAccountId(int $accountId): string
{
    try {
        $stmt = db()->prepare('SELECT LOWER(TRIM(affiliate_program)) AS slug FROM affiliate_accounts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $accountId]);
        $slug = (string) ($stmt->fetchColumn() ?: '');
    } catch (Throwable $e) {
        return 'default';
    }
    if ($slug === '') {
        return 'default';
    }
    if (strpos($slug, 'oponia') !== false) {
        return 'oponia';
    }
    if (strpos($slug, 'yieldkit') !== false) {
        return 'yieldkit';
    }

    return 'default';
}

function buildCustomAffiliateUrl(string $affiliateLink, string $program, string $clickId): string
{
    $sep = strpos($affiliateLink, '?') === false ? '?' : '&';
    switch (strtolower($program)) {
        case 'oponia':
            return $affiliateLink . $sep . 'placementId=' . urlencode($clickId);
        case 'yieldkit':
            return $affiliateLink . $sep . 'yk_tag=' . urlencode($clickId);
        default:
            return $affiliateLink . $sep . 'subid=' . urlencode($clickId);
    }
}

