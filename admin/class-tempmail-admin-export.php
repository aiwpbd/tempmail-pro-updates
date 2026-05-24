<?php
/**
 * TempMail Pro – Admin Export / Import
 * Handles full backup and restore of all plugin user data.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Admin_Export {

    private static ?self $instance = null;
    const EXPORT_VERSION = '1.0';

    public static function instance(): self {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_tmpmp_export_users', [ $this, 'ajax_export' ] );
        add_action( 'wp_ajax_tmpmp_import_users', [ $this, 'ajax_import' ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT
    // ─────────────────────────────────────────────────────────────────

    public function ajax_export(): void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        global $wpdb;
        $p = $wpdb->prefix;

        // Options from request
        $inc_users       = ! empty( $_POST['inc_users'] );
        $inc_subs        = ! empty( $_POST['inc_subs'] );
        $inc_payments    = ! empty( $_POST['inc_payments'] );
        $inc_api_keys    = ! empty( $_POST['inc_api_keys'] );
        $inc_addresses   = ! empty( $_POST['inc_addresses'] );
        $inc_blocked_ips = ! empty( $_POST['inc_blocked_ips'] );
        $inc_plans       = ! empty( $_POST['inc_plans'] );

        $cap_key   = $p . 'capabilities';
        $user_ids  = [];

        $export = [
            'meta' => [
                'version'     => self::EXPORT_VERSION,
                'plugin_ver'  => TMPMP_VERSION,
                'exported_at' => gmdate( 'c' ),
                'site_url'    => get_site_url(),
                'site_name'   => get_bloginfo( 'name' ),
            ],
        ];

        // ── Plans ─────────────────────────────────────────────────────
        if ( $inc_plans ) {
            $export['plans'] = $wpdb->get_results(
                "SELECT * FROM {$p}tmpmp_plans ORDER BY sort_order ASC",
                ARRAY_A
            ) ?: [];
        }

        // ── Users ─────────────────────────────────────────────────────
        if ( $inc_users ) {
            $raw_users = $wpdb->get_results( $wpdb->prepare(
                "SELECT u.ID, u.user_email, u.display_name, u.user_registered, u.user_login
                 FROM {$wpdb->users} u
                 WHERE u.ID NOT IN (
                     SELECT user_id FROM {$wpdb->usermeta}
                     WHERE meta_key = %s AND meta_value LIKE %s
                 )
                 ORDER BY u.ID ASC",
                $cap_key,
                '%administrator%'
            ), ARRAY_A ) ?: [];

            $users_out = [];
            foreach ( $raw_users as $u ) {
                $uid  = (int) $u['ID'];
                $user_ids[] = $uid;
                $row = [
                    'wp_user' => [
                        'ID'               => $uid,
                        'user_login'       => $u['user_login'],
                        'user_email'       => $u['user_email'],
                        'display_name'     => $u['display_name'],
                        'user_registered'  => $u['user_registered'],
                    ],
                    'user_meta' => [],
                ];
                // Relevant meta keys
                foreach ( [ 'tmpmp_avatar', 'tmpmp_avatar_url', 'first_name', 'last_name', 'description' ] as $mk ) {
                    $v = get_user_meta( $uid, $mk, true );
                    if ( $v !== '' && $v !== false ) $row['user_meta'][ $mk ] = $v;
                }
                $users_out[] = $row;
            }
            $export['users'] = $users_out;
            $export['meta']['total_users'] = count( $users_out );
        }

        // ── Subscriptions ─────────────────────────────────────────────
        if ( $inc_subs ) {
            $where = $user_ids
                ? "WHERE user_id IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ")"
                : '';
            $export['subscriptions'] = $wpdb->get_results(
                "SELECT * FROM {$p}tmpmp_subscriptions $where ORDER BY id ASC",
                ARRAY_A
            ) ?: [];
        }

        // ── Payments ──────────────────────────────────────────────────
        if ( $inc_payments ) {
            $where = $user_ids
                ? "WHERE user_id IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ")"
                : '';
            $export['payments'] = $wpdb->get_results(
                "SELECT * FROM {$p}tmpmp_payments $where ORDER BY id ASC",
                ARRAY_A
            ) ?: [];
        }

        // ── API Keys ──────────────────────────────────────────────────
        if ( $inc_api_keys ) {
            $where = $user_ids
                ? "WHERE user_id IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ")"
                : '';
            $export['api_keys'] = $wpdb->get_results(
                "SELECT * FROM {$p}tmpmp_api_keys $where ORDER BY id ASC",
                ARRAY_A
            ) ?: [];
        }

        // ── Addresses (inboxes) ───────────────────────────────────────
        if ( $inc_addresses ) {
            $where = $user_ids
                ? "WHERE user_id IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ") AND user_id > 0"
                : 'WHERE user_id > 0';
            $export['addresses'] = $wpdb->get_results(
                "SELECT * FROM {$p}tmpmp_addresses $where ORDER BY id ASC",
                ARRAY_A
            ) ?: [];
        }

        // ── Blocked IPs ───────────────────────────────────────────────
        if ( $inc_blocked_ips ) {
            $export['blocked_ips'] = $wpdb->get_results(
                "SELECT * FROM {$p}tmpmp_blocked_ips ORDER BY id ASC",
                ARRAY_A
            ) ?: [];
        }

        $json = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        wp_send_json_success( [ 'json' => $json ] );
    }

    // ─────────────────────────────────────────────────────────────────
    // IMPORT
    // ─────────────────────────────────────────────────────────────────

    public function ajax_import(): void {
        check_ajax_referer( 'tempmail_pro_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', 403 );

        // Read uploaded file
        if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'tempmail-pro' ) ] );
        }

        $raw = file_get_contents( $_FILES['import_file']['tmp_name'] );
        if ( ! $raw ) {
            wp_send_json_error( [ 'message' => __( 'Could not read uploaded file.', 'tempmail-pro' ) ] );
        }

        $data = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['meta'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid JSON file. Please upload a valid export file.', 'tempmail-pro' ) ] );
        }

        global $wpdb;
        $p = $wpdb->prefix;

        $stats = [
            'users_created'    => 0,
            'users_updated'    => 0,
            'subs_imported'    => 0,
            'payments_imported'=> 0,
            'api_keys_imported'=> 0,
            'addresses_imported'=> 0,
            'blocked_ips_imported' => 0,
            'plans_imported'   => 0,
            'errors'           => [],
        ];

        $uid_map = []; // old_uid → new_uid

        // ── Plans ─────────────────────────────────────────────────────
        if ( ! empty( $data['plans'] ) && is_array( $data['plans'] ) ) {
            foreach ( $data['plans'] as $plan ) {
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$p}tmpmp_plans WHERE slug = %s", $plan['slug']
                ) );
                unset( $plan['id'] );
                if ( $existing ) {
                    $wpdb->update( $p . 'tmpmp_plans', $plan, [ 'id' => $existing ] );
                } else {
                    $wpdb->insert( $p . 'tmpmp_plans', $plan );
                }
                $stats['plans_imported']++;
            }
        }

        // ── Users ─────────────────────────────────────────────────────
        if ( ! empty( $data['users'] ) && is_array( $data['users'] ) ) {
            foreach ( $data['users'] as $udata ) {
                $wu      = $udata['wp_user']   ?? [];
                $meta    = $udata['user_meta'] ?? [];
                $old_uid = (int) ( $wu['ID'] ?? 0 );
                $email   = sanitize_email( $wu['user_email'] ?? '' );
                if ( ! $email ) {
                    $stats['errors'][] = "Skipped user with invalid email: " . esc_html( $wu['user_email'] ?? '' );
                    continue;
                }

                $existing_uid = (int) email_exists( $email );
                if ( $existing_uid ) {
                    // Update existing WP user
                    wp_update_user( [
                        'ID'           => $existing_uid,
                        'display_name' => sanitize_text_field( $wu['display_name'] ?? '' ),
                    ] );
                    $uid_map[ $old_uid ] = $existing_uid;
                    $stats['users_updated']++;
                } else {
                    // Create new WP user
                    $login = sanitize_user( $wu['user_login'] ?? strtok( $email, '@' ) );
                    if ( username_exists( $login ) ) {
                        $login = $login . '_' . wp_generate_password( 4, false );
                    }
                    $new_uid = wp_insert_user( [
                        'user_login'      => $login,
                        'user_email'      => $email,
                        'display_name'    => sanitize_text_field( $wu['display_name'] ?? '' ),
                        'user_registered' => $wu['user_registered'] ?? current_time( 'mysql' ),
                        'user_pass'       => wp_generate_password( 18 ),
                        'role'            => 'subscriber',
                    ] );
                    if ( is_wp_error( $new_uid ) ) {
                        $stats['errors'][] = "Failed to create user {$email}: " . $new_uid->get_error_message();
                        continue;
                    }
                    $uid_map[ $old_uid ] = $new_uid;
                    $stats['users_created']++;
                }

                // User meta
                $new_uid_for_meta = $uid_map[ $old_uid ];
                foreach ( $meta as $mk => $mv ) {
                    update_user_meta( $new_uid_for_meta, sanitize_key( $mk ), $mv );
                }
            }
        }

        // Helper: remap old user_id to new user_id
        $remap = function( int $old_uid ) use ( &$uid_map ): int {
            return $uid_map[ $old_uid ] ?? $old_uid;
        };

        // ── Subscriptions ─────────────────────────────────────────────
        if ( ! empty( $data['subscriptions'] ) && is_array( $data['subscriptions'] ) ) {
            foreach ( $data['subscriptions'] as $sub ) {
                $new_uid = $remap( (int) $sub['user_id'] );
                $sub['user_id'] = $new_uid;
                // Upsert by user_id (keep latest active)
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$p}tmpmp_subscriptions WHERE user_id = %d AND status = 'active' LIMIT 1",
                    $new_uid
                ) );
                unset( $sub['id'] );
                if ( $existing ) {
                    $wpdb->update( $p . 'tmpmp_subscriptions', $sub, [ 'id' => $existing ] );
                } else {
                    $wpdb->insert( $p . 'tmpmp_subscriptions', $sub );
                }
                $stats['subs_imported']++;
            }
        }

        // ── Payments ──────────────────────────────────────────────────
        if ( ! empty( $data['payments'] ) && is_array( $data['payments'] ) ) {
            foreach ( $data['payments'] as $pay ) {
                $pay['user_id'] = $remap( (int) $pay['user_id'] );
                $txn_id = $pay['gateway_txn_id'] ?? '';
                unset( $pay['id'] );
                if ( $txn_id ) {
                    $existing = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$p}tmpmp_payments WHERE gateway_txn_id = %s LIMIT 1",
                        $txn_id
                    ) );
                    if ( $existing ) {
                        $wpdb->update( $p . 'tmpmp_payments', $pay, [ 'id' => $existing ] );
                    } else {
                        $wpdb->insert( $p . 'tmpmp_payments', $pay );
                    }
                } else {
                    $wpdb->insert( $p . 'tmpmp_payments', $pay );
                }
                $stats['payments_imported']++;
            }
        }

        // ── API Keys ──────────────────────────────────────────────────
        if ( ! empty( $data['api_keys'] ) && is_array( $data['api_keys'] ) ) {
            foreach ( $data['api_keys'] as $key ) {
                $key['user_id'] = $remap( (int) $key['user_id'] );
                $api_key = $key['api_key'] ?? '';
                unset( $key['id'] );
                if ( $api_key ) {
                    $existing = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$p}tmpmp_api_keys WHERE api_key = %s LIMIT 1",
                        $api_key
                    ) );
                    if ( $existing ) {
                        $wpdb->update( $p . 'tmpmp_api_keys', $key, [ 'id' => $existing ] );
                    } else {
                        $wpdb->insert( $p . 'tmpmp_api_keys', $key );
                    }
                } else {
                    $wpdb->insert( $p . 'tmpmp_api_keys', $key );
                }
                $stats['api_keys_imported']++;
            }
        }

        // ── Addresses ─────────────────────────────────────────────────
        if ( ! empty( $data['addresses'] ) && is_array( $data['addresses'] ) ) {
            foreach ( $data['addresses'] as $addr ) {
                $addr['user_id'] = $remap( (int) $addr['user_id'] );
                $address = $addr['address'] ?? '';
                unset( $addr['id'] );
                if ( $address ) {
                    $existing = $wpdb->get_var( $wpdb->prepare(
                        "SELECT id FROM {$p}tmpmp_addresses WHERE address = %s LIMIT 1",
                        $address
                    ) );
                    if ( $existing ) {
                        $wpdb->update( $p . 'tmpmp_addresses', $addr, [ 'id' => $existing ] );
                    } else {
                        $wpdb->insert( $p . 'tmpmp_addresses', $addr );
                    }
                } else {
                    $wpdb->insert( $p . 'tmpmp_addresses', $addr );
                }
                $stats['addresses_imported']++;
            }
        }

        // ── Blocked IPs ───────────────────────────────────────────────
        if ( ! empty( $data['blocked_ips'] ) && is_array( $data['blocked_ips'] ) ) {
            foreach ( $data['blocked_ips'] as $ip_row ) {
                $ip = $ip_row['ip_address'] ?? '';
                if ( ! $ip ) continue;
                unset( $ip_row['id'] );
                $existing = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$p}tmpmp_blocked_ips WHERE ip_address = %s LIMIT 1", $ip
                ) );
                if ( ! $existing ) {
                    $wpdb->insert( $p . 'tmpmp_blocked_ips', $ip_row );
                }
                $stats['blocked_ips_imported']++;
            }
        }

        wp_send_json_success( [
            'message' => __( 'Import completed successfully.', 'tempmail-pro' ),
            'stats'   => $stats,
        ] );
    }
}
