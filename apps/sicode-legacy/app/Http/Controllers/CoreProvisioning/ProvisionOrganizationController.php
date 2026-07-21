<?php

namespace App\Http\Controllers\CoreProvisioning;

use App\CoreProvisioning\AuthenticateProvisioningClient;
use App\CoreProvisioning\LegacyProvisioningAuditLogger;
use App\CoreProvisioning\ProvisionLegacyOrganization;
use App\CoreProvisioning\ProvisioningAuthenticationFailed;
use App\CoreProvisioning\ProvisioningConflict;
use App\CoreProvisioning\ProvisioningException;
use App\CoreProvisioning\ProvisioningRejected;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ProvisionOrganizationController extends Controller
{
    public function __invoke(
        Request $request,
        AuthenticateProvisioningClient $authenticateClient,
        ProvisionLegacyOrganization $provisionOrganization,
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
            'core_organization_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,suspended'],
        ]);

        if ($validator->fails()) {
            $audit->warning('organization.rejected', [
                'correlation_id' => $correlationId,
                'result' => 'rejected',
                'reason' => 'VALIDATION_FAILED',
                'resource_type' => 'organization',
                'application_context' => $context,
            ]);

            return $this->rejected(422);
        }

        $validated = $validator->validated();
        $clientIdentifier = (string) $validated['client_identifier'];

        $audit->info('organization.requested', [
            'correlation_id' => $correlationId,
            'resource_type' => 'organization',
            'client_identifier' => $clientIdentifier,
            'core_issuer' => (string) $validated['core_issuer'],
            'core_organization_id' => (string) $validated['core_organization_id'],
            'application_context' => $context,
        ]);

        try {
            $authenticateClient($clientIdentifier, (string) $validated['client_secret']);

            $outcome = $provisionOrganization($validated, $clientIdentifier, $context);

            $audit->info('organization.completed', [
                'correlation_id' => $correlationId,
                'result' => $outcome->result,
                'resource_type' => 'organization',
                'client_identifier' => $clientIdentifier,
                'core_issuer' => (string) $validated['core_issuer'],
                'core_organization_id' => (string) $validated['core_organization_id'],
                'application_context' => $context,
            ]);

            return response()->json($outcome->toArray());
        } catch (ProvisioningAuthenticationFailed $exception) {
            $audit->warning('authentication.rejected', [
                'correlation_id' => $correlationId,
                'result' => 'rejected',
                'reason' => $exception->reason,
                'resource_type' => 'organization',
                'client_identifier' => $clientIdentifier,
                'application_context' => $context,
            ]);

            return $this->rejected(401);
        } catch (ProvisioningConflict $exception) {
            $audit->warning('organization.conflict', [
                'correlation_id' => $correlationId,
                'result' => 'conflict',
                'reason' => $exception->reason,
                'resource_type' => 'organization',
                'client_identifier' => $clientIdentifier,
                'core_issuer' => (string) $validated['core_issuer'],
                'core_organization_id' => (string) $validated['core_organization_id'],
                'application_context' => $context,
            ]);

            return $this->rejected(409, 'conflict');
        } catch (ProvisioningRejected $exception) {
            $audit->warning('organization.rejected', [
                'correlation_id' => $correlationId,
                'result' => 'rejected',
                'reason' => $exception->reason,
                'resource_type' => 'organization',
                'client_identifier' => $clientIdentifier,
                'core_issuer' => (string) $validated['core_issuer'],
                'core_organization_id' => (string) $validated['core_organization_id'],
                'application_context' => $context,
            ]);

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
