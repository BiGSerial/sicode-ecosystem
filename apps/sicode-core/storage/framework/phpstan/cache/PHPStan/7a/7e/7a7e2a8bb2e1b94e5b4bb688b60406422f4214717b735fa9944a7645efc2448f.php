<?php declare(strict_types = 1);

// odsl-/var/www/html/tests
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1-enums',
   'data' => 
  array (
    '/var/www/html/tests/Feature/CoreSchemaConstraintsTest.php' => 
    array (
      0 => 'a0434d1bd109c7d019d4ff6e1460be9f267f11d356eaeb049b9b9fd9737e0282',
      1 => 
      array (
        0 => 'tests\\feature\\coreschemaconstraintstest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_user_status_is_constrained',
        3 => 'tests\\feature\\test_external_identity_is_unique_by_provider_context_and_subject',
        4 => 'tests\\feature\\test_external_identity_allows_same_subject_in_different_provider_contexts',
        5 => 'tests\\feature\\test_application_code_is_unique',
        6 => 'tests\\feature\\test_application_code_format_is_constrained',
        7 => 'tests\\feature\\test_application_client_identifier_is_unique',
        8 => 'tests\\feature\\test_context_code_can_repeat_between_applications',
        9 => 'tests\\feature\\test_context_code_is_unique_within_application',
        10 => 'tests\\feature\\test_context_must_belong_to_same_application_for_access',
        11 => 'tests\\feature\\test_context_must_belong_to_same_application_for_client',
        12 => 'tests\\feature\\test_context_must_belong_to_same_application_for_contract_grant',
        13 => 'tests\\feature\\test_only_one_active_equivalent_application_access_is_allowed',
        14 => 'tests\\feature\\test_only_one_active_equivalent_contract_grant_is_allowed',
        15 => 'tests\\feature\\test_invalid_contract_period_is_rejected',
        16 => 'tests\\feature\\test_invalid_application_access_period_is_rejected',
        17 => 'tests\\feature\\test_invalid_contract_grant_period_is_rejected',
        18 => 'tests\\feature\\test_application_access_status_is_constrained',
        19 => 'tests\\feature\\test_contract_grant_status_is_constrained',
        20 => 'tests\\feature\\test_membership_status_and_dates_must_be_coherent',
        21 => 'tests\\feature\\test_user_can_exist_without_organization_membership',
        22 => 'tests\\feature\\test_ended_membership_remains_stored_and_allows_new_active_membership',
        23 => 'tests\\feature\\test_multiple_active_memberships_in_different_organizations_are_allowed',
        24 => 'tests\\feature\\test_duplicate_active_membership_for_same_user_and_organization_is_rejected',
        25 => 'tests\\feature\\test_known_organization_document_is_unique',
        26 => 'tests\\feature\\createuser',
        27 => 'tests\\feature\\createmembership',
        28 => 'tests\\feature\\createorganization',
        29 => 'tests\\feature\\createcontract',
        30 => 'tests\\feature\\createcoreapplication',
        31 => 'tests\\feature\\createcontext',
        32 => 'tests\\feature\\createapplicationclient',
        33 => 'tests\\feature\\createapplicationaccess',
        34 => 'tests\\feature\\createcontractapplicationgrant',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/ExampleTest.php' => 
    array (
      0 => 'cbf0b44e101223613c83eceff20156f32e90071a3870bf8a705eb8d7a99ef5ed',
      1 => 
      array (
        0 => 'tests\\feature\\exampletest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\test_health_check_returns_ok',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/TestCase.php' => 
    array (
      0 => 'c7c209f5579c42647c1f7b2a79ac828e8b54ac62a98442045a6e94fa2c0d0ebc',
      1 => 
      array (
        0 => 'tests\\testcase',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/ExampleTest.php' => 
    array (
      0 => '555c5c211637f3b51908ce3dbc989f3e7bedd48d833ce37c46be0a719cde2b67',
      1 => 
      array (
        0 => 'tests\\unit\\exampletest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\test_php_runtime_meets_core_requirement',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/CoreModelsTest.php' => 
    array (
      0 => '8215336c3178cfda5121017bb73fecd7224cbea509e20f30c393e6922c8ff282',
      1 => 
      array (
        0 => 'tests\\feature\\coremodelstest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_user_persists_with_postgresql_generated_uuid_without_password',
        3 => 'tests\\feature\\test_user_relates_external_identities_memberships_and_accesses',
        4 => 'tests\\feature\\test_external_identity_belongs_to_user_and_does_not_mass_assign_user_id',
        5 => 'tests\\feature\\test_organization_relates_memberships_and_contracts',
        6 => 'tests\\feature\\test_membership_belongs_to_user_and_organization',
        7 => 'tests\\feature\\test_contract_belongs_to_organization_and_relates_grants',
        8 => 'tests\\feature\\test_application_relates_contexts_clients_accesses_and_grants',
        9 => 'tests\\feature\\test_client_and_context_relationships_follow_schema',
        10 => 'tests\\feature\\test_application_access_relates_user_application_and_context',
        11 => 'tests\\feature\\test_contract_application_grant_relates_contract_application_and_context',
        12 => 'tests\\feature\\createuser',
        13 => 'tests\\feature\\createorganization',
        14 => 'tests\\feature\\createcontract',
        15 => 'tests\\feature\\createcoreapplication',
        16 => 'tests\\feature\\createcontext',
        17 => 'tests\\feature\\createclient',
        18 => 'tests\\feature\\createaccess',
        19 => 'tests\\feature\\creategrant',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/CoreModelUuidLifecycleTest.php' => 
    array (
      0 => 'd8ed2605ea50973a72bbc3bba9ca751f52dcf6c7204c3bba34f1ff19794fa937',
      1 => 
      array (
        0 => 'tests\\feature\\coremodeluuidlifecycletest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_create_hydrates_postgresql_uuid_and_marks_model_as_persisted',
        3 => 'tests\\feature\\test_save_hydrates_postgresql_uuid_on_new_model',
        4 => 'tests\\feature\\test_model_events_see_uuid_only_after_database_insert_returns',
        5 => 'tests\\feature\\test_refresh_update_and_find_use_hydrated_uuid_key',
        6 => 'tests\\feature\\test_relationship_create_hydrates_related_model_uuid',
        7 => 'tests\\feature\\test_create_quietly_and_save_quietly_hydrate_uuid_without_events',
        8 => 'tests\\feature\\test_uuid_lifecycle_regression_for_user_external_identity_and_application_access',
        9 => 'tests\\feature\\eventsnapshot',
        10 => 'tests\\feature\\createuser',
        11 => 'tests\\feature\\newuser',
        12 => 'tests\\feature\\newuserattributes',
        13 => 'tests\\feature\\createcoreapplication',
        14 => 'tests\\feature\\createcontext',
        15 => 'tests\\feature\\assertuuid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/ApplicationEntryEvaluationTest.php' => 
    array (
      0 => '1090d8997dbc0e3418b068c9598c88ebbfc28ee01adde1685f4509c7897ebe55',
      1 => 
      array (
        0 => 'tests\\feature\\applicationentryevaluationtest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_a_allows_active_user_with_effective_application_access_when_no_institutional_requirement_exists',
        3 => 'tests\\feature\\test_b_denies_inactive_user_before_institutional_queries',
        4 => 'tests\\feature\\test_c_denies_inactive_application_before_institutional_queries',
        5 => 'tests\\feature\\test_d_denies_missing_context_when_application_has_contexts',
        6 => 'tests\\feature\\test_e_denies_context_from_another_application_as_structured_decision',
        7 => 'tests\\feature\\test_f_access_for_es_does_not_authorize_sp',
        8 => 'tests\\feature\\test_g_denies_when_application_access_exists_but_is_not_effective',
        9 => 'tests\\feature\\test_h_denies_when_organization_is_required_and_no_membership_is_effective',
        10 => 'tests\\feature\\test_i_denies_when_multiple_memberships_are_equally_eligible',
        11 => 'tests\\feature\\test_j_denies_when_contract_is_required_and_no_contract_is_effective',
        12 => 'tests\\feature\\test_k_denies_when_contract_is_effective_but_grant_is_not_effective',
        13 => 'tests\\feature\\test_l_allows_when_access_membership_contract_and_grant_are_effective',
        14 => 'tests\\feature\\test_m_grant_for_es_does_not_authorize_sp',
        15 => 'tests\\feature\\test_n_same_instant_produces_same_decision',
        16 => 'tests\\feature\\test_o_starts_at_boundary_is_inclusive',
        17 => 'tests\\feature\\test_p_ends_at_boundary_is_inclusive',
        18 => 'tests\\feature\\test_q_one_second_after_ends_at_is_not_effective',
        19 => 'tests\\feature\\test_r_institutional_grant_does_not_replace_individual_application_access',
        20 => 'tests\\feature\\test_s_individual_access_does_not_replace_required_institutional_grant',
        21 => 'tests\\feature\\test_evaluation_without_institutional_requirement_does_not_query_contracts_or_grants',
        22 => 'tests\\feature\\evaluate',
        23 => 'tests\\feature\\assertdecision',
        24 => 'tests\\feature\\capturequeries',
        25 => 'tests\\feature\\assertqueriesdonotmention',
        26 => 'tests\\feature\\createuser',
        27 => 'tests\\feature\\createcoreapplication',
        28 => 'tests\\feature\\createcontext',
        29 => 'tests\\feature\\createorganization',
        30 => 'tests\\feature\\createmembership',
        31 => 'tests\\feature\\createcontract',
        32 => 'tests\\feature\\createaccess',
        33 => 'tests\\feature\\creategrant',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/CoreAuditFoundationTest.php' => 
    array (
      0 => 'b8697f963ccfde62e4125e35778c4d6aece926e0a8435a4a89f7840e2e269335',
      1 => 
      array (
        0 => 'tests\\feature\\coreauditfoundationtest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_schema_a_rejects_action_outside_catalog',
        3 => 'tests\\feature\\test_schema_b_rejects_actor_type_outside_catalog',
        4 => 'tests\\feature\\test_schema_c_rejects_subject_type_outside_catalog',
        5 => 'tests\\feature\\test_schema_d_system_allows_null_actor_id',
        6 => 'tests\\feature\\test_schema_e_identifiable_actor_requires_actor_id',
        7 => 'tests\\feature\\test_schema_f_details_accepts_valid_json_object',
        8 => 'tests\\feature\\test_schema_g_details_rejects_json_list_at_root',
        9 => 'tests\\feature\\test_schema_h_rejects_context_application_mismatch',
        10 => 'tests\\feature\\test_schema_i_accepts_valid_correlation_id',
        11 => 'tests\\feature\\test_schema_j_database_generates_uuid_and_core_model_hydrates_it',
        12 => 'tests\\feature\\test_schema_k_update_is_blocked_by_append_only_trigger',
        13 => 'tests\\feature\\test_schema_l_delete_is_blocked_by_append_only_trigger',
        14 => 'tests\\feature\\test_recorder_m_records_minimum_valid_event',
        15 => 'tests\\feature\\test_recorder_n_records_user_actor',
        16 => 'tests\\feature\\test_recorder_o_records_system_actor_without_actor_id',
        17 => 'tests\\feature\\test_recorder_p_records_subject',
        18 => 'tests\\feature\\test_recorder_q_records_application_and_context',
        19 => 'tests\\feature\\test_recorder_r_records_reason',
        20 => 'tests\\feature\\test_recorder_s_records_structured_details',
        21 => 'tests\\feature\\test_recorder_t_records_provided_correlation_id',
        22 => 'tests\\feature\\test_recorder_u_uses_same_correlation_id_when_caller_provides_same_operation_id',
        23 => 'tests\\feature\\test_recorder_v_inside_committed_transaction_persists_event',
        24 => 'tests\\feature\\test_recorder_w_inside_rolled_back_transaction_does_not_persist_event',
        25 => 'tests\\feature\\test_recorder_x_does_not_start_isolated_transaction_surviving_outer_rollback',
        26 => 'tests\\feature\\test_recorder_y_rejects_sensitive_detail_keys',
        27 => 'tests\\feature\\test_enum_catalog_values_are_accepted_by_schema',
        28 => 'tests\\feature\\recordevent',
        29 => 'tests\\feature\\validauditpayload',
        30 => 'tests\\feature\\createuser',
        31 => 'tests\\feature\\createcoreapplication',
        32 => 'tests\\feature\\createcontext',
        33 => 'tests\\feature\\assertuuid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/LocalPasswordCredentialTest.php' => 
    array (
      0 => 'f3ecdcb9f4e44de8d6b08288902f2953a39015a6a04751f7e29491c60aac00fd',
      1 => 
      array (
        0 => 'tests\\feature\\localpasswordcredentialtest',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_schema_a_user_can_exist_without_local_credential',
        3 => 'tests\\feature\\test_schema_b_credential_belongs_to_user',
        4 => 'tests\\feature\\test_schema_c_allows_at_most_one_credential_per_user',
        5 => 'tests\\feature\\test_schema_d_password_hash_is_required',
        6 => 'tests\\feature\\test_schema_e_status_outside_catalog_is_rejected',
        7 => 'tests\\feature\\test_schema_f_uuid_is_generated_by_postgresql_and_hydrated_by_core_model',
        8 => 'tests\\feature\\test_schema_g_user_delete_is_restricted_when_credential_exists',
        9 => 'tests\\feature\\test_schema_h_password_hash_has_no_index',
        10 => 'tests\\feature\\test_set_i_defines_password_for_user_without_credential',
        11 => 'tests\\feature\\test_set_j_k_l_persists_only_hash_and_hash_check_accepts_correct_password',
        12 => 'tests\\feature\\test_set_m_n_o_replacing_password_updates_hash_and_old_password_stops_validating',
        13 => 'tests\\feature\\test_set_p_updates_password_changed_at',
        14 => 'tests\\feature\\test_set_q_r_registers_creation_and_change_audit_events',
        15 => 'tests\\feature\\test_set_s_does_not_store_password_or_hash_in_audit_reason_or_details',
        16 => 'tests\\feature\\test_set_t_audit_failure_rolls_back_mutation',
        17 => 'tests\\feature\\test_password_policy_rejects_short_password',
        18 => 'tests\\feature\\test_verify_u_user_without_credential_returns_not_found',
        19 => 'tests\\feature\\test_verify_v_disabled_credential_returns_not_active',
        20 => 'tests\\feature\\test_verify_w_wrong_password_returns_mismatch',
        21 => 'tests\\feature\\test_verify_x_y_correct_password_returns_stable_verified_reason',
        22 => 'tests\\feature\\test_verify_z_does_not_alter_database',
        23 => 'tests\\feature\\test_verify_aa_does_not_register_audit',
        24 => 'tests\\feature\\test_verify_ab_reports_rehash_requirement',
        25 => 'tests\\feature\\test_disable_ac_ad_active_credential_can_be_disabled_and_no_longer_verifies',
        26 => 'tests\\feature\\test_disable_ae_user_remains_existing_and_status_is_unchanged',
        27 => 'tests\\feature\\test_disable_af_external_identity_remains_unchanged',
        28 => 'tests\\feature\\test_disable_ag_requires_reason',
        29 => 'tests\\feature\\test_disable_ah_registers_audit_event',
        30 => 'tests\\feature\\test_disable_ai_audit_failure_rolls_back_mutation',
        31 => 'tests\\feature\\setpassword',
        32 => 'tests\\feature\\verifypassword',
        33 => 'tests\\feature\\disablepassword',
        34 => 'tests\\feature\\createuser',
        35 => 'tests\\feature\\assertuuid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Feature/LocalAuthenticationTest.php' => 
    array (
      0 => 'e76ca87da1299d44183734eb76a85f5ad0fa9792d0b6697dfa327987ea80d828',
      1 => 
      array (
        0 => 'tests\\feature\\localauthenticationtest',
        1 => 'tests\\feature\\spyhasher',
        2 => 'tests\\feature\\missinguserspyhasher',
        3 => 'tests\\feature\\dummycachespyhasher',
      ),
      2 => 
      array (
        0 => 'tests\\feature\\setup',
        1 => 'tests\\feature\\teardown',
        2 => 'tests\\feature\\test_a_active_user_with_active_credential_and_correct_password_authenticates',
        3 => 'tests\\feature\\test_b_identifier_is_trimmed_before_resolution',
        4 => 'tests\\feature\\test_c_identifier_case_variation_resolves_normalized_email',
        5 => 'tests\\feature\\test_d_nonexistent_user_returns_invalid_credentials',
        6 => 'tests\\feature\\test_e_existing_user_without_local_password_credential_returns_invalid_credentials',
        7 => 'tests\\feature\\test_f_wrong_password_returns_invalid_credentials',
        8 => 'tests\\feature\\test_g_disabled_credential_returns_local_credential_not_active',
        9 => 'tests\\feature\\test_h_blocked_and_disabled_users_return_user_not_active',
        10 => 'tests\\feature\\test_i_application_entry_authorization_tables_are_not_consulted',
        11 => 'tests\\feature\\test_j_user_authenticates_without_organization',
        12 => 'tests\\feature\\test_k_user_authenticates_without_application_access',
        13 => 'tests\\feature\\test_l_authenticated_decision_returns_user',
        14 => 'tests\\feature\\test_m_denied_decision_returns_no_user',
        15 => 'tests\\feature\\test_n_decision_never_returns_credential_or_hash',
        16 => 'tests\\feature\\test_o_requires_password_rehash_is_false_for_current_hash',
        17 => 'tests\\feature\\test_p_requires_password_rehash_is_true_for_outdated_hash',
        18 => 'tests\\feature\\test_q_authenticate_local_user_does_not_alter_database',
        19 => 'tests\\feature\\test_r_authenticate_local_user_does_not_register_audit_event',
        20 => 'tests\\feature\\test_s_authenticate_local_user_does_not_create_session_data',
        21 => 'tests\\feature\\test_t_authenticate_local_user_does_not_use_laravel_auth_facade',
        22 => 'tests\\feature\\test_u_nonexistent_user_executes_dummy_hash_check',
        23 => 'tests\\feature\\test_v_dummy_hash_is_not_generated_per_authentication_call',
        24 => 'tests\\feature\\test_w_dummy_hash_is_valid_for_configured_driver',
        25 => 'tests\\feature\\test_x_presented_password_is_not_returned_and_capability_has_no_logging_calls',
        26 => 'tests\\feature\\authenticate',
        27 => 'tests\\feature\\setpassword',
        28 => 'tests\\feature\\disablepassword',
        29 => 'tests\\feature\\createuser',
        30 => 'tests\\feature\\assertauthenticateddecision',
        31 => 'tests\\feature\\assertdenieddecision',
        32 => 'tests\\feature\\info',
        33 => 'tests\\feature\\make',
        34 => 'tests\\feature\\check',
        35 => 'tests\\feature\\needsrehash',
      ),
      3 => 
      array (
      ),
    ),
  ),
));