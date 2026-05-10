<?php
global $wpdb;

// Reset state for the test.
$wpdb->query("DELETE FROM {$wpdb->prefix}crm_notification_log");
$wpdb->query("DELETE FROM {$wpdb->prefix}crm_notif_subs");

// Find or create test users.
$users = [];
foreach ( [ 'opted-in-1', 'opted-in-2', 'opted-out' ] as $login ) {
    $u = get_user_by( 'login', $login );
    if ( ! $u ) {
        $id = wp_create_user( $login, wp_generate_password(), $login . '@example.test' );
        $u  = get_user_by( 'id', $id );
    }
    $users[ $login ] = $u;
}

// Subscriptions: two opted in, one explicitly opted out.
ZippyCrm\Models\NotifSub::upsert( $users['opted-in-1']->ID, true,  true  );
ZippyCrm\Models\NotifSub::upsert( $users['opted-in-2']->ID, true,  false );
ZippyCrm\Models\NotifSub::upsert( $users['opted-out']->ID,  false, false );

// Find/create a published voucher.
$existing = ZippyCrm\Models\Voucher::find_by_code( 'NOTIFTEST' );
if ( $existing ) {
    $voucher_id = (int) $existing['id'];
} else {
    $voucher_id = ZippyCrm\Models\Voucher::create([
        'code'           => 'NOTIFTEST',
        'title'          => 'Notification test 20% off',
        'description'    => 'A round of testing for the email pipeline.',
        'discount_type'  => 'percent',
        'discount_value' => 20,
        'status'         => 'active',
        'expires_at'     => '2026-12-31 23:59:59',
    ], 1);
}
echo "voucher id: $voucher_id\n";

// Capture wp_mail calls instead of actually sending.
$captured_mail = [];
add_filter( 'pre_wp_mail', function ( $short_circuit, $atts ) use ( &$captured_mail ) {
    $captured_mail[] = $atts;
    return true;  // pretend it succeeded
}, 10, 2 );

// 1. Fire the publish action — should queue 2 rows (the two opted-in users) and schedule 1 batch.
echo "\n--- PUBLISH ---\n";
do_action( 'crm_voucher_published', $voucher_id );

$queued = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT user_id, status FROM {$wpdb->prefix}crm_notification_log WHERE voucher_id = %d ORDER BY user_id",
        $voucher_id
    )
);
echo "queued rows: " . count( $queued ) . "\n";
foreach ( $queued as $r ) echo "  user_id=$r->user_id status=$r->status\n";

$crons = _get_cron_array();
$dispatch_count = 0;
foreach ( $crons as $ts => $events ) {
    foreach ( $events as $hook => $_ ) {
        if ( $hook === 'crm_dispatch_voucher_notifications' ) $dispatch_count++;
    }
}
echo "scheduled dispatch events: $dispatch_count\n";

// 2. Run the batch — should send 2 emails.
echo "\n--- DISPATCH ---\n";
$sent = ZippyCrm\Services\NotifEngine::dispatch_batch( $voucher_id, 0 );
echo "dispatched: $sent\n";
echo "captured wp_mail calls: " . count( $captured_mail ) . "\n";
foreach ( $captured_mail as $m ) {
    echo "  to=" . ( is_array( $m['to'] ) ? implode(',', $m['to']) : $m['to'] ) . " subject=$m[subject]\n";
}

$after = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT user_id, status, attempts FROM {$wpdb->prefix}crm_notification_log WHERE voucher_id = %d ORDER BY user_id",
        $voucher_id
    )
);
echo "log after dispatch:\n";
foreach ( $after as $r ) echo "  user_id=$r->user_id status=$r->status attempts=$r->attempts\n";

// 3. Replay publish — should be idempotent (no new rows, no new dispatch events for already-queued users).
echo "\n--- REPLAY PUBLISH ---\n";
$before_replay = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_notification_log WHERE voucher_id = $voucher_id" );
do_action( 'crm_voucher_published', $voucher_id );
$after_replay = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_notification_log WHERE voucher_id = $voucher_id" );
echo "rows before=$before_replay after=$after_replay (should be equal)\n";

// 4. Render the email body for visual inspection.
echo "\n--- EMAIL BODY (first 600 chars) ---\n";
$voucher = ZippyCrm\Models\Voucher::find( $voucher_id );
$user    = $users['opted-in-1'];
$ref     = new ReflectionMethod( ZippyCrm\Services\NotifEngine::class, 'render_template' );
$ref->setAccessible( true );
$body = $ref->invoke( null, $user, $voucher );
echo substr( $body, 0, 600 ) . "...\n[total " . strlen( $body ) . " bytes]\n";

// 5. Failure path — clear, fail wp_mail, retry.
echo "\n--- FAILURE + RETRY ---\n";
$wpdb->query( "UPDATE {$wpdb->prefix}crm_notification_log SET status='queued', attempts=0, sent_at=NULL WHERE voucher_id=$voucher_id" );

$captured_mail = [];
remove_all_filters( 'pre_wp_mail' );
add_filter( 'pre_wp_mail', function () { return false; }, 10, 0 );
add_action( 'wp_mail_failed', function( $err ) { /* swallow — we want mark_failed path */ } );

$sent = ZippyCrm\Services\NotifEngine::dispatch_batch( $voucher_id, 0 );
echo "after force-fail dispatch: sent=$sent\n";
$failed = $wpdb->get_results( "SELECT user_id, status, attempts FROM {$wpdb->prefix}crm_notification_log WHERE voucher_id=$voucher_id" );
foreach ( $failed as $r ) echo "  user_id=$r->user_id status=$r->status attempts=$r->attempts\n";

// Now succeed on retry.
remove_all_filters( 'pre_wp_mail' );
add_filter( 'pre_wp_mail', function () { return true; }, 10, 0 );
$sent = ZippyCrm\Services\NotifEngine::retry_failed();
echo "after retry: sent=$sent\n";
$retried = $wpdb->get_results( "SELECT user_id, status, attempts FROM {$wpdb->prefix}crm_notification_log WHERE voucher_id=$voucher_id" );
foreach ( $retried as $r ) echo "  user_id=$r->user_id status=$r->status attempts=$r->attempts\n";
