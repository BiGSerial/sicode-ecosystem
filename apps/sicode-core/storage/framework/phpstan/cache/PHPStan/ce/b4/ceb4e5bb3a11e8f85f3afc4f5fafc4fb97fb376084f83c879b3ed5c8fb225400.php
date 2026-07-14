<?php declare(strict_types = 1);

// odsl-/var/www/html/app
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1-enums',
   'data' => 
  array (
    '/var/www/html/app/Http/Controllers/Controller.php' => 
    array (
      0 => '25d1c1ef8e6cc8a376553faacfba2b07d9dfaee9bdbb84f14f77517580e9deb1',
      1 => 
      array (
        0 => 'app\\http\\controllers\\controller',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Providers/AppServiceProvider.php' => 
    array (
      0 => 'caf306ef6a25a547bbb7edd5508a39b51c365083eb6dd58378cf2242018e94c7',
      1 => 
      array (
        0 => 'app\\providers\\appserviceprovider',
      ),
      2 => 
      array (
        0 => 'app\\providers\\register',
        1 => 'app\\providers\\boot',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/Application.php' => 
    array (
      0 => 'b2030ed89bd4e706c60fac4ed63d95cfbfab90c33e083bd0d715f4586c7b994c',
      1 => 
      array (
        0 => 'app\\models\\application',
      ),
      2 => 
      array (
        0 => 'app\\models\\contexts',
        1 => 'app\\models\\clients',
        2 => 'app\\models\\accesses',
        3 => 'app\\models\\contractgrants',
        4 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/ApplicationAccess.php' => 
    array (
      0 => 'c04d3f62b7bf2c0fcaf6d24d7b967299b5afb6d593fcfa04048c237b64fa5412',
      1 => 
      array (
        0 => 'app\\models\\applicationaccess',
      ),
      2 => 
      array (
        0 => 'app\\models\\user',
        1 => 'app\\models\\application',
        2 => 'app\\models\\context',
        3 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/ApplicationClient.php' => 
    array (
      0 => '6830018fa681ad560c990fa835a565371a0a1023eace5ebf7d57122bc6bb61c2',
      1 => 
      array (
        0 => 'app\\models\\applicationclient',
      ),
      2 => 
      array (
        0 => 'app\\models\\application',
        1 => 'app\\models\\context',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/ApplicationContext.php' => 
    array (
      0 => '244cac3a98c253e2b95d3b101b03763939c3ce44e974c7148c6f2c9e9d18cc9a',
      1 => 
      array (
        0 => 'app\\models\\applicationcontext',
      ),
      2 => 
      array (
        0 => 'app\\models\\application',
        1 => 'app\\models\\clients',
        2 => 'app\\models\\accesses',
        3 => 'app\\models\\contractgrants',
        4 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/Contract.php' => 
    array (
      0 => '4a0f4946963ee78cb6b992f028db0ed186380f4efdee37f8ca5fac8180fc68fa',
      1 => 
      array (
        0 => 'app\\models\\contract',
      ),
      2 => 
      array (
        0 => 'app\\models\\organization',
        1 => 'app\\models\\applicationgrants',
        2 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/ContractApplicationGrant.php' => 
    array (
      0 => '8f7d80c9fee81b0550dc2c176846a3b7f7526d5cb1356f913c1ed80fd941d8bb',
      1 => 
      array (
        0 => 'app\\models\\contractapplicationgrant',
      ),
      2 => 
      array (
        0 => 'app\\models\\contract',
        1 => 'app\\models\\application',
        2 => 'app\\models\\context',
        3 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/CoreModel.php' => 
    array (
      0 => '6cf93d49284db209c275fe34b44fb2290f12afb08a3334a6ada2ceeaf76c17cf',
      1 => 
      array (
        0 => 'app\\models\\coremodel',
      ),
      2 => 
      array (
        0 => 'app\\models\\performinsert',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/ExternalIdentity.php' => 
    array (
      0 => '57f694f331f07cc8d9667908c14ff862d41082fabb111e1d386aabdc01cd0565',
      1 => 
      array (
        0 => 'app\\models\\externalidentity',
      ),
      2 => 
      array (
        0 => 'app\\models\\user',
        1 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/Organization.php' => 
    array (
      0 => '281704b6d87536154a5376062b88959e93f7544e00d915270034e85777499f3a',
      1 => 
      array (
        0 => 'app\\models\\organization',
      ),
      2 => 
      array (
        0 => 'app\\models\\memberships',
        1 => 'app\\models\\contracts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/OrganizationMembership.php' => 
    array (
      0 => '628f5420b08203862f2514130ed033917b0d47095878df4f191bbebfd1226ca4',
      1 => 
      array (
        0 => 'app\\models\\organizationmembership',
      ),
      2 => 
      array (
        0 => 'app\\models\\user',
        1 => 'app\\models\\organization',
        2 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/User.php' => 
    array (
      0 => '9490d17ba237ad7c8edbbb5640d356fe8bdf701811a60ae421db3a8386719cdf',
      1 => 
      array (
        0 => 'app\\models\\user',
      ),
      2 => 
      array (
        0 => 'app\\models\\externalidentities',
        1 => 'app\\models\\localpasswordcredential',
        2 => 'app\\models\\organizationmemberships',
        3 => 'app\\models\\applicationaccesses',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/ApplicationEntry/ApplicationEntryDecision.php' => 
    array (
      0 => '260c19f4a75791d4192642a369f9a47b1d773a2044557345574ea5f886eab564',
      1 => 
      array (
        0 => 'app\\applicationentry\\applicationentrydecision',
      ),
      2 => 
      array (
        0 => 'app\\applicationentry\\__construct',
        1 => 'app\\applicationentry\\allowed',
        2 => 'app\\applicationentry\\denied',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/ApplicationEntry/ApplicationEntryReason.php' => 
    array (
      0 => '010d9ad752da17f0eb302677cf5c0ab0ab24fc5bbb7b8d7234090c57b1a8ba6f',
      1 => 
      array (
        0 => 'app\\applicationentry\\applicationentryreason',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/ApplicationEntry/EvaluateApplicationEntry.php' => 
    array (
      0 => '838496ddfba1a26183a50425a88cb66b408dbb899334b63778726155b58d149b',
      1 => 
      array (
        0 => 'app\\applicationentry\\evaluateapplicationentry',
      ),
      2 => 
      array (
        0 => 'app\\applicationentry\\__invoke',
        1 => 'app\\applicationentry\\applicationhascontexts',
        2 => 'app\\applicationentry\\applicationaccessexists',
        3 => 'app\\applicationentry\\effectiveapplicationaccessexists',
        4 => 'app\\applicationentry\\applicationaccessquery',
        5 => 'app\\applicationentry\\requiresorganization',
        6 => 'app\\applicationentry\\requirescontract',
        7 => 'app\\applicationentry\\resolveeffectivemembership',
        8 => 'app\\applicationentry\\effectivecontractexists',
        9 => 'app\\applicationentry\\effectivegrantexists',
        10 => 'app\\applicationentry\\effectivecontractquery',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/CoreAudit/CoreAuditAction.php' => 
    array (
      0 => 'dd18abeb8c865db5ff5a3ad036fd441026d69badf4e7f0e80d07e1619d9f50bf',
      1 => 
      array (
        0 => 'app\\coreaudit\\coreauditaction',
      ),
      2 => 
      array (
        0 => 'app\\coreaudit\\values',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/CoreAudit/CoreAuditActorType.php' => 
    array (
      0 => 'aacff1204d56fc0cfcc8517de65911c6d92d4067e571b89d41c1bd029dbf6be9',
      1 => 
      array (
        0 => 'app\\coreaudit\\coreauditactortype',
      ),
      2 => 
      array (
        0 => 'app\\coreaudit\\values',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/CoreAudit/CoreAuditRecord.php' => 
    array (
      0 => 'fac9ae0281b9f3d634cb0a4417dc76b1b1f1ba13384975eefaef52ea4d6c5864',
      1 => 
      array (
        0 => 'app\\coreaudit\\coreauditrecord',
      ),
      2 => 
      array (
        0 => 'app\\coreaudit\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/CoreAudit/CoreAuditSubjectType.php' => 
    array (
      0 => '3db020cba7c3439c0748a48bf535b7242bc8a29247a2e8b8637d907979762e33',
      1 => 
      array (
        0 => 'app\\coreaudit\\coreauditsubjecttype',
      ),
      2 => 
      array (
        0 => 'app\\coreaudit\\values',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/CoreAudit/RecordCoreAuditEvent.php' => 
    array (
      0 => '5cd835fbd0d20fd703813ec0dd990a6c0f303f24fbf78ad8c11c1782045e40c5',
      1 => 
      array (
        0 => 'app\\coreaudit\\recordcoreauditevent',
      ),
      2 => 
      array (
        0 => 'app\\coreaudit\\__invoke',
        1 => 'app\\coreaudit\\validateactor',
        2 => 'app\\coreaudit\\validatereason',
        3 => 'app\\coreaudit\\validatedetails',
        4 => 'app\\coreaudit\\assertnosensitivedetailkeys',
        5 => 'app\\coreaudit\\issensitivedetailkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/CoreAuditEvent.php' => 
    array (
      0 => 'c1c83f0b1c16e80faf4bac18a971f9ceaff7fc9e2e2901639262c56326925c5f',
      1 => 
      array (
        0 => 'app\\models\\coreauditevent',
      ),
      2 => 
      array (
        0 => 'app\\models\\application',
        1 => 'app\\models\\context',
        2 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalPassword/DisableLocalPasswordCredential.php' => 
    array (
      0 => '3b5e20448e8d3576189a4e32ebd7d83d85feb2c9a16b81e88d3e3414e18e47c5',
      1 => 
      array (
        0 => 'app\\localpassword\\disablelocalpasswordcredential',
      ),
      2 => 
      array (
        0 => 'app\\localpassword\\__construct',
        1 => 'app\\localpassword\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalPassword/LocalPasswordPolicy.php' => 
    array (
      0 => 'baf0ecc281ffd509042d2400ca7d381a968d0a63279e486c0e195fd54829108c',
      1 => 
      array (
        0 => 'app\\localpassword\\localpasswordpolicy',
      ),
      2 => 
      array (
        0 => 'app\\localpassword\\rules',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalPassword/LocalPasswordVerification.php' => 
    array (
      0 => 'a56dcda63b59b34b044bcf2ec5a07e6ab1350107229347ed0eb86c40af817a4e',
      1 => 
      array (
        0 => 'app\\localpassword\\localpasswordverification',
      ),
      2 => 
      array (
        0 => 'app\\localpassword\\__construct',
        1 => 'app\\localpassword\\verified',
        2 => 'app\\localpassword\\denied',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalPassword/LocalPasswordVerificationReason.php' => 
    array (
      0 => 'f44b916e68f797f342da9abfb0efef3e0ac669dfc43610d91eb5882011351fc5',
      1 => 
      array (
        0 => 'app\\localpassword\\localpasswordverificationreason',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalPassword/SetLocalPasswordCredential.php' => 
    array (
      0 => 'd10428b7ac0367295630bdbed99ccdd4bb1c78c0a57b6019d83192863d3aa3f3',
      1 => 
      array (
        0 => 'app\\localpassword\\setlocalpasswordcredential',
      ),
      2 => 
      array (
        0 => 'app\\localpassword\\__construct',
        1 => 'app\\localpassword\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalPassword/VerifyLocalPasswordCredential.php' => 
    array (
      0 => '5cb9671f68b82b50757a3235276fa46b9f3bd6617f241d37e15f79a705186978',
      1 => 
      array (
        0 => 'app\\localpassword\\verifylocalpasswordcredential',
      ),
      2 => 
      array (
        0 => 'app\\localpassword\\__construct',
        1 => 'app\\localpassword\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/LocalPasswordCredential.php' => 
    array (
      0 => '2df903e013bbc2a784770a4378a7839362256a7a1bc938f6f2fd07fa0532f26c',
      1 => 
      array (
        0 => 'app\\models\\localpasswordcredential',
      ),
      2 => 
      array (
        0 => 'app\\models\\user',
        1 => 'app\\models\\isactive',
        2 => 'app\\models\\casts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/Models/LocalPasswordCredentialStatus.php' => 
    array (
      0 => '8b3907ad17b46a71993815125a29c06f8654551678116c7f2649f5757d5d6bd8',
      1 => 
      array (
        0 => 'app\\models\\localpasswordcredentialstatus',
      ),
      2 => 
      array (
        0 => 'app\\models\\values',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalAuthentication/AuthenticateLocalUser.php' => 
    array (
      0 => '37cd75f681ad995766925ec4348b5a68c7c7776ce60ddd58d84f247a53b2127d',
      1 => 
      array (
        0 => 'app\\localauthentication\\authenticatelocaluser',
      ),
      2 => 
      array (
        0 => 'app\\localauthentication\\__construct',
        1 => 'app\\localauthentication\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalAuthentication/LocalAuthenticationDecision.php' => 
    array (
      0 => 'efa8699fc4dd6b347578092d4ce63efeff3040475799c48f69a394ca7b7d04f0',
      1 => 
      array (
        0 => 'app\\localauthentication\\localauthenticationdecision',
      ),
      2 => 
      array (
        0 => 'app\\localauthentication\\__construct',
        1 => 'app\\localauthentication\\authenticated',
        2 => 'app\\localauthentication\\denied',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalAuthentication/LocalAuthenticationDummyHash.php' => 
    array (
      0 => 'b79276c34da7c2a016698553f90b70bb49d775e3acec35f8fc061201b53d44ec',
      1 => 
      array (
        0 => 'app\\localauthentication\\localauthenticationdummyhash',
      ),
      2 => 
      array (
        0 => 'app\\localauthentication\\__construct',
        1 => 'app\\localauthentication\\hash',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalAuthentication/LocalAuthenticationReason.php' => 
    array (
      0 => '24a540dac18b106820bc64891dc3f1262043c8edbe7c5a408a71567562ef7b19',
      1 => 
      array (
        0 => 'app\\localauthentication\\localauthenticationreason',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/app/LocalAuthentication/LocalLoginIdentifierNormalizer.php' => 
    array (
      0 => '03e080737726cb865f7c17269b2dad55372e2c8febfe7e3f97bba5ce0efcbfa1',
      1 => 
      array (
        0 => 'app\\localauthentication\\localloginidentifiernormalizer',
      ),
      2 => 
      array (
        0 => 'app\\localauthentication\\normalize',
      ),
      3 => 
      array (
      ),
    ),
  ),
));