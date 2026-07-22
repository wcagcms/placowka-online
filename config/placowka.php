<?php

return [
    'default_missing_after_minutes' => (int) env('PLACOWKA_MISSING_AFTER_MINUTES', 3),

    'default_alert_after_minutes' => (int) env('PLACOWKA_ALERT_AFTER_MINUTES', 5),

    'email_alerts_enabled' => filter_var(env('PLACOWKA_EMAIL_ALERTS_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'alert_email_to' => env('PLACOWKA_ALERT_EMAIL_TO'),

    'agent_package_retention_hours' => (int) env('PLACOWKA_AGENT_PACKAGE_RETENTION_HOURS', 24),

    'heartbeat_max_payload_bytes' => (int) env('PLACOWKA_HEARTBEAT_MAX_PAYLOAD_BYTES', 262144),

    'diagnostic_incident_open_after' => (int) env('PLACOWKA_DIAGNOSTIC_INCIDENT_OPEN_AFTER', 3),

    'diagnostic_incident_resolve_after' => (int) env('PLACOWKA_DIAGNOSTIC_INCIDENT_RESOLVE_AFTER', 5),

    'agent_minimum_go_version' => env('PLACOWKA_AGENT_MINIMUM_GO_VERSION', '1.26.5'),

    'agent_latest_version' => env('PLACOWKA_AGENT_LATEST_VERSION', 'exe-1.9.3'),

    'agent_self_check_interval_minutes' => (int) env('PLACOWKA_AGENT_SELF_CHECK_INTERVAL_MINUTES', 30),

    'agent_allow_interim_build' => filter_var(
        env('PLACOWKA_AGENT_ALLOW_INTERIM_BUILD', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'agent_enrollment_enabled' => filter_var(
        env('PLACOWKA_AGENT_ENROLLMENT_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    'agent_enrollment_code_ttl_minutes' => (int) env('PLACOWKA_AGENT_ENROLLMENT_CODE_TTL_MINUTES', 15),

    'agent_enrollment_session_ttl_minutes' => (int) env('PLACOWKA_AGENT_ENROLLMENT_SESSION_TTL_MINUTES', 10),

    'agent_enrollment_token_replay_minutes' => (int) env('PLACOWKA_AGENT_ENROLLMENT_TOKEN_REPLAY_MINUTES', 5),

    'agent_enrollment_max_attempts' => (int) env('PLACOWKA_AGENT_ENROLLMENT_MAX_ATTEMPTS', 5),

    'agent_setup_version' => env('PLACOWKA_AGENT_SETUP_VERSION', '1.0.6'),

    'agent_installer_storage_path' => env(
        'PLACOWKA_AGENT_INSTALLER_STORAGE_PATH',
        'app/agent-installer/PlacowkaOnlineSetup.exe'
    ),


    'offline_queue_max_items' => (int) env('PLACOWKA_OFFLINE_QUEUE_MAX_ITEMS', 100),

    'offline_queue_max_age_days' => (int) env('PLACOWKA_OFFLINE_QUEUE_MAX_AGE_DAYS', 7),

    'offline_queue_flush_per_cycle' => (int) env('PLACOWKA_OFFLINE_QUEUE_FLUSH_PER_CYCLE', 10),

    'windows_update_interval_minutes' => (int) env('PLACOWKA_WINDOWS_UPDATE_INTERVAL_MINUTES', 720),

    'defender_interval_minutes' => (int) env('PLACOWKA_DEFENDER_INTERVAL_MINUTES', 360),

    'windows_update_stale_days' => (int) env('PLACOWKA_WINDOWS_UPDATE_STALE_DAYS', 45),

    'defender_max_signature_age_days' => (int) env('PLACOWKA_DEFENDER_MAX_SIGNATURE_AGE_DAYS', 3),

    'backup_enabled' => filter_var(env('PLACOWKA_BACKUP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'backup_retention_days' => (int) env('PLACOWKA_BACKUP_RETENTION_DAYS', 14),

    'legacy_agent_packages_enabled' => filter_var(
        env('PLACOWKA_LEGACY_AGENT_PACKAGES_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN
    ),

];
