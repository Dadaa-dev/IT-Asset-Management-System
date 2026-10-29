<?php
/**
 * Utility helpers for AssetVault.
 */

require_once __DIR__ . '/constants.php';

/**
 * Build a pre‑filled Google Form URL for a given asset.
 *
 * @param int    $assetId   The database ID of the asset.
 * @param string $assetName Human‑readable asset name.
 * @return string Fully‑qualified URL that opens the form with the fields pre‑filled.
 */
function asset_report_url(int $assetId, string $assetName): string
{
    $params = [];
    $params[FORM_FIELD_ASSET_ID]   = $assetId;
    $params[FORM_FIELD_ASSET_NAME] = $assetName;
    // optional fields left empty – user can fill them in the form.
    $query = http_build_query($params);
    return GOOGLE_FORM_BASE . '&' . $query;
}

/**
 * Return an <img> tag that displays a QR code for the pre‑filled form.
 * Uses the free Google Chart API (no extra dependencies).
 *
 * @param int    $assetId
 * @param string $assetName
 * @param int    $size      Pixel size of the QR image (default 200).
 * @return string HTML <img> element.
 */
function asset_report_qr_img(int $assetId, string $assetName, int $size = 200): string
{
    $url = asset_report_url($assetId, $assetName);
    $qr = 'https://chart.googleapis.com/chart?chs=' . $size . 'x' . $size . '&cht=qr&chl=' . rawurlencode($url);
    return '<img src="' . $qr . '" alt="Report QR" width="' . $size . '" height="' . $size . '">';
}
?>
