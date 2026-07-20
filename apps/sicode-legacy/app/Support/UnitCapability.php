<?php

namespace App\Support;

enum UnitCapability: string
{
    case ADS_DELIVERY = 'ads.delivery';
    case LEGACY_LOCAL_LOGIN = 'legacy.local_login';
    case PRODUCTION_CONTRACT_COMPANY_FALLBACK = 'production.contract_company_fallback';
    case WORK_REPORT_TACIT_APPROVAL = 'work_report.tacit_approval';
}
