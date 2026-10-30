<?php
/** Import the owner's explicitly requested existing bank details without logging them. */
if (!defined('WP_CLI') || !WP_CLI || !defined('ABSPATH')) { exit(1); }
try {
    $require = static function ($ok, $message) { if (!$ok) { throw new RuntimeException($message); } };
    $require(realpath(ABSPATH) === '/www/wwwroot/fubaojewelry.com' && defined('DB_NAME') && DB_NAME === 'sql_fubaojewelry', 'Wrong destination installation.');
    $require(untrailingslashit(home_url()) === 'https://fubaojewelry.com' && current_user_can('manage_woocommerce'), 'Confirmed FUBAO origin and administrator are required.');
    $require(getenv('FUBAO_APPROVE_SHARED_BANK') === '1', 'Owner approval to reuse the existing bank details is required.');
    if (get_option('fubao_bank_setup_v1')) { WP_CLI::success('Bank transfer was already configured; later dashboard edits preserved.'); return; }
    $file = '/tmp/fubao-bank-import-private.json';
    $require(is_file($file) && !is_link($file) && realpath($file) === $file && (fileperms($file) & 0077) === 0, 'A private mode-0600 bank transfer source is required.');
    $source = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $require(is_array($source), 'Invalid bank account source format.');
    $accounts = array();
    foreach ($source as $row) {
        $require(is_array($row), 'Invalid bank account row.');
        $account = array();
        foreach (array('account_name', 'account_number', 'bank_name', 'sort_code', 'iban', 'bic') as $key) {
            $account[$key] = isset($row[$key]) && is_scalar($row[$key]) ? sanitize_text_field((string) $row[$key]) : '';
        }
        if ($account['account_name'] === '' && $account['account_number'] === '' && $account['iban'] === '') { continue; }
        $require($account['account_name'] !== '' && $account['bank_name'] !== '' && ($account['account_number'] !== '' || $account['iban'] !== ''), 'The existing account is incomplete; no bank details were changed.');
        $accounts[] = $account;
    }
    $require(count($accounts) > 0, 'No complete existing bank account was found.');
    $existing = get_option('woocommerce_bacs_accounts', array());
    foreach ((array) $existing as $row) {
        if (!empty($row['account_number']) || !empty($row['iban'])) {
            $require($existing === $accounts, 'Destination already contains bank details; refusing to overwrite them.');
            break;
        }
    }
    update_option('woocommerce_bacs_accounts', $accounts);
    $settings = get_option('woocommerce_bacs_settings', array());
    $settings = is_array($settings) ? $settings : array();
    $settings['enabled'] = 'yes';
    $settings['title'] = 'Direct bank transfer';
    $settings['description'] = 'Transfer directly to the bank account shown after placing your order. Use your order number as the payment reference.';
    $settings['instructions'] = 'Use your order number as the payment reference. Your order is processed after the payment is confirmed.';
    update_option('woocommerce_bacs_settings', $settings);
    $require(get_option('woocommerce_bacs_accounts') === $accounts, 'Bank account verification failed.');
    update_option('fubao_bank_setup_v1', array('source' => 'owner-requested-existing-store', 'accounts' => count($accounts), 'configured_at' => gmdate('c')), false);
    WP_CLI::success('Direct bank transfer configured from the existing owner-approved account. Account values were not logged; no order was created.');
} catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
