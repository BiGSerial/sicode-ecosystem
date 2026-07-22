<?php

namespace App\Http\Controllers\CoreProvisioning;

use App\CoreProvisioning\AuthenticateProvisioningClient;
use App\CoreProvisioning\LegacyProvisioningAuditLogger;
use App\CoreProvisioning\ProvisioningAuthenticationFailed;
use App\CoreProvisioning\ProvisioningConflict;
use App\CoreProvisioning\ProvisioningException;
use App\CoreProvisioning\ProvisioningRejected;
use App\CoreProvisioning\SuspendLegacyOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SuspendOrganizationController extends Controller
{
    public function __invoke(
        string $coreOrganizationId,
        Request $request,
        AuthenticateProvisioningClient $authenticateClient,
        SuspendLegacyOrganization $suspendOrganization,
        LegacyProvisioningAuditLogger $audit,
    ): JsonResponse {
        $correlationId = (string) Str::uuid();
        $context = (string) config('sicode.core.expected_context');

        $validator = Validator::make($request->all(), [
            'client_identifier' => ['required', 'string', 'max:120'],
            'client_secret' => ['required', 'string', 'max:500'],
            'contract_version' => ['required', 'string', 'max:20', 'in:'.(string) config('core_provisioning.contract_version')],
            'idempotency_key' => ['required', 'string', 'max:120'],
            'core_issuer' => ['required', 'string', 'max:120'],
        ]);

        if ($validator->fails()) {
            return $this->rejected(422);
        }

        $validated = $validator->validated();
        $clientIdentifier = (string) $validated['client_identifier'];

        try {
            $authenticateClient($clientIdentifier, (string) $validated['client_secret']);

            $outcome = $suspendOrganization($coreOrganizationId, $validated, $clientIdentifier, $context);

            $audit->info('organization.suspended', [
                'correlation_id' => $correlationId,
                'result' => $outcome->result,
                'resource_type' => 'organization',
                'client_identifier' => $clientIdentifier,
                'core_organization_id' => $coreOrganizationId,
                'application_context' => $context,
            ]);

            return response()->json($outcome->toArray());
        } catch (ProvisioningAuthenticationFailed) {
            return $this->rejected(401);
        } catch (ProvisioningConflict $exception) {
            return $this->rejected(409, 'conflict');
        } catch (ProvisioningRejected $exception) {
            return $this->rejected(403);
        } catch (ProvisioningException) {
            return $this->rejected(422);
        }
    }

    private function rejected(int $status, string $result = 'rejected'): JsonResponse
    {
        return response()->json([
            'message' => 'Provisioning request rejected.',
            'result' => $result,
        ], $status);
    }
}
