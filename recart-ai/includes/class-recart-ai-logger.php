<?php
/**
 * Logger class for ReCart AI events.
 *
 * @package ReCart_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recart_AI_Logger {

    /**
     * Log an event.
     */
    public function log( string $event_type, string $message, ?string $fingerprint_id = null, ?string $email = null, ?array $data = null ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_logs';

        $ip_hash = $this->get_ip_hash();

        $wpdb->insert(
            $table,
            array(
                'event_type'     => sanitize_text_field( $event_type ),
                'fingerprint_id' => $fingerprint_id ? sanitize_text_field( $fingerprint_id ) : null,
                'email'          => $email ? sanitize_email( $email ) : null,
                'message'        => sanitize_text_field( $message ),
                'data'           => $data ? wp_json_encode( $data ) : null,
                'ip_hash'        => $ip_hash,
                'created_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Get logs with pagination.
     */
    public function get_logs( int $page = 1, int $per_page = 50, ?string $event_type = null ): array {
        global $wpdb;

        $table  = $wpdb->prefix . 'recart_logs';
        $offset = ( $page - 1 ) * $per_page;

        $where = '';
        $params = array();

        if ( $event_type ) {
            $where = 'WHERE event_type = %s';
            $params[] = $event_type;
        }

        $params[] = $per_page;
        $params[] = $offset;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                ...$params
            )
        );

        $total_where = $event_type ? $wpdb->prepare( "WHERE event_type = %s", $event_type ) : '';
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$total_where}" );

        return array(
            'items' => $results,
            'total' => $total,
            'pages' => ceil( $total / $per_page ),
        );
    }

    /**
     * Clean old logs (older than 90 days).
     */
    public function cleanup_old_logs(): int {
        global $wpdb;

        $table = $wpdb->prefix . 'recart_logs';

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) )
            )
        );
    }

    /**
     * Get hashed IP address.
     */
    private function get_ip_hash(): string {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) )[0];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return hash( 'sha256', $ip . wp_salt( 'auth' ) );
    }
}
