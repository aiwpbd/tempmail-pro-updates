<?php
/**
 * TempMail Pro — Domains manager + DNS/MX Verification
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class TempMail_Domains {

    private static ?self $instance = null;
    public static function instance() : self { return self::$instance ??= new self(); }

    private function __construct() {}

    /** Returns domains accessible for a given plan slug */
    public static function get_for_plan( string $plan_slug ) : array {
        $plan = TempMail_Database::get_plan_by_slug( $plan_slug );
        $cats = $plan ? json_decode( $plan->domains_allowed ?? '["free"]', true ) : ['free'];
        return TempMail_Database::get_all_domains() ? array_filter(
            TempMail_Database::get_all_domains(),
            fn($d) => in_array( $d->category, $cats, true )
        ) : [];
    }

    public static function get_all() : array {
        return TempMail_Database::get_all_domains();
    }

    public static function get_full( int $id ) : ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tmpmp_domains WHERE id = %d", $id
        ) ) ?: null;
    }

    public static function add( string $domain, string $category = 'free', string $desc = '' ) : mixed {
        global $wpdb;
        if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain ) ) {
            return new WP_Error( 'invalid_domain', __( 'Invalid domain format.', 'tempmail-pro' ) );
        }
        $wpdb->insert( $wpdb->prefix . 'tmpmp_domains', [
            'domain'      => strtolower( $domain ),
            'category'    => $category,
            'description' => $desc,
            'is_active'   => 1,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ] );
        return (int) $wpdb->insert_id;
    }

    public static function update( int $id, array $data ) : bool {
        global $wpdb;
        return (bool) $wpdb->update(
            $wpdb->prefix . 'tmpmp_domains',
            $data,
            ['id' => $id]
        );
    }

    public static function delete( int $id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'tmpmp_domains', ['id' => $id] );
    }

    // =========================================================================
    // DNS / MX Verification
    // =========================================================================

    /**
     * Run all DNS checks for a domain.
     * Returns: [ domain, overall (healthy|warning|error), checks[], mx_record, summary ]
     */
    public static function verify_dns( string $domain ) : array {
        $checks    = [];
        $mx_record = '';
        $mx_host   = '';

        // ── 1. MX Record ─────────────────────────────────────────────────────
        $mx_records = @dns_get_record( $domain, DNS_MX );
        if ( ! empty( $mx_records ) ) {
            usort( $mx_records, fn($a,$b) => $a['pri'] <=> $b['pri'] );
            $mx_host   = $mx_records[0]['target'] ?? '';
            $mx_record = $mx_host . ' (pri=' . ( $mx_records[0]['pri'] ?? '?' ) . ')';
            $all_mx    = implode(', ', array_map( fn($r) => $r['target'].' (pri='.$r['pri'].')', $mx_records ));
            $checks[]  = [
                'name'   => 'MX Record',
                'status' => 'pass',
                'icon'   => 'pass',
                'detail' => count($mx_records) . ' MX record(s) found. All: ' . $all_mx,
            ];
        } else {
            $checks[] = [
                'name'   => 'MX Record',
                'status' => 'fail',
                'icon'   => 'fail',
                'detail' => 'No MX records found for ' . $domain . '. Emails cannot be received.',
            ];
        }

        // ── 2. MX Host Resolvability ──────────────────────────────────────────
        if ( $mx_host ) {
            $mx_a = @dns_get_record( $mx_host, DNS_A );
            $mx_aaaa = @dns_get_record( $mx_host, DNS_AAAA );
            $all_ip  = array_merge( (array)$mx_a, (array)$mx_aaaa );
            if ( ! empty( $all_ip ) ) {
                $ips = array_map( fn($r) => $r['ip'] ?? $r['ipv6'] ?? '', $all_ip );
                $checks[] = [
                    'name'   => 'MX Host Resolves',
                    'status' => 'pass',
                    'icon'   => 'pass',
                    'detail' => $mx_host . ' resolves to: ' . implode(', ', array_filter($ips)),
                ];
            } else {
                $checks[] = [
                    'name'   => 'MX Host Resolves',
                    'status' => 'warn',
                    'icon'   => 'warn',
                    'detail' => $mx_host . ' does not resolve to an IP. DNS propagation may be pending.',
                ];
            }
        } else {
            $checks[] = [
                'name'   => 'MX Host Resolves',
                'status' => 'skip',
                'icon'   => 'skip',
                'detail' => 'Skipped — no MX host to resolve.',
            ];
        }

        // ── 3. A Record ───────────────────────────────────────────────────────
        $a_records = @dns_get_record( $domain, DNS_A );
        if ( ! empty( $a_records ) ) {
            $ips = array_column( $a_records, 'ip' );
            $checks[] = [
                'name'   => 'A Record',
                'status' => 'pass',
                'icon'   => 'pass',
                'detail' => 'Domain resolves to: ' . implode(', ', $ips),
            ];
        } else {
            $checks[] = [
                'name'   => 'A Record',
                'status' => 'warn',
                'icon'   => 'warn',
                'detail' => 'No A record found. Optional for mail-only domains, but recommended.',
            ];
        }

        // ── 4. SPF TXT Record ─────────────────────────────────────────────────
        $txt_records = @dns_get_record( $domain, DNS_TXT );
        $spf = '';
        foreach ( (array) $txt_records as $t ) {
            $val = is_array($t['txt'] ?? null) ? implode('', $t['txt']) : ($t['txt'] ?? '');
            if ( str_starts_with( $val, 'v=spf1' ) ) { $spf = $val; break; }
        }
        if ( $spf ) {
            $checks[] = [
                'name'   => 'SPF Record',
                'status' => 'pass',
                'icon'   => 'pass',
                'detail' => $spf,
            ];
        } else {
            $checks[] = [
                'name'   => 'SPF Record',
                'status' => 'warn',
                'icon'   => 'warn',
                'detail' => 'No SPF record found. Recommended TXT: v=spf1 mx ~all',
            ];
        }

        // ── 5. DMARC TXT Record ───────────────────────────────────────────────
        $dmarc_txt = @dns_get_record( '_dmarc.' . $domain, DNS_TXT );
        $dmarc = '';
        foreach ( (array) $dmarc_txt as $t ) {
            $val = is_array($t['txt'] ?? null) ? implode('', $t['txt']) : ($t['txt'] ?? '');
            if ( str_starts_with( $val, 'v=DMARC1' ) ) { $dmarc = $val; break; }
        }
        if ( $dmarc ) {
            $checks[] = [
                'name'   => 'DMARC Record',
                'status' => 'pass',
                'icon'   => 'pass',
                'detail' => $dmarc,
            ];
        } else {
            $checks[] = [
                'name'   => 'DMARC Record',
                'status' => 'warn',
                'icon'   => 'warn',
                'detail' => 'No DMARC at _dmarc.' . $domain . '. Recommended: v=DMARC1; p=none; rua=mailto:dmarc@' . $domain,
            ];
        }

        // ── Overall health ────────────────────────────────────────────────────
        $statuses = array_column( $checks, 'status' );
        if ( in_array('fail', $statuses, true) ) {
            $overall = 'error';
        } elseif ( in_array('warn', $statuses, true) ) {
            $overall = 'warning';
        } else {
            $overall = 'healthy';
        }

        // ── Persist to DB ─────────────────────────────────────────────────────
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'tmpmp_domains',
            [
                'health_status' => $overall,
                'mx_record'     => $mx_record,
                'last_checked'  => gmdate('Y-m-d H:i:s'),
            ],
            [ 'domain' => $domain ]
        );

        return [
            'domain'    => $domain,
            'overall'   => $overall,
            'mx_record' => $mx_record,
            'checks'    => $checks,
            'summary'   => sprintf(
                '%d passed, %d warnings, %d failed',
                count( array_filter($statuses, fn($s) => $s === 'pass') ),
                count( array_filter($statuses, fn($s) => $s === 'warn') ),
                count( array_filter($statuses, fn($s) => $s === 'fail') )
            ),
        ];
    }

    /**
     * Verify all domains — returns array keyed by domain name.
     */
    public static function verify_all() : array {
        $results = [];
        foreach ( TempMail_Database::get_all_domains() as $d ) {
            $results[ $d->domain ] = self::verify_dns( $d->domain );
        }
        return $results;
    }
}
