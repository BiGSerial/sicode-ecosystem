<?php

namespace App\Http\Middleware;

use App\Models\Service;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckServiceOrDispatchPermission
{
    public function handle(Request $request, Closure $next, $type)
    {
        if (!Auth::check()) {
            return redirect('/');
        }

        $user = Auth::user();
        $serviceParam = $request->route('service');
        $type = $request->segment(1);

        // Verifica se o usuário é SUPERADM
        if ($user?->superadm) {
            return $next($request);
        }

        if ($user?->ToServices->isEmpty()) {
            abort(403, 'Você não tem permissão para realizar nenhum serviço ou despacho no sistema. Por favor, entre em contato com o administrador do SICODE ou seu Responsável.');
        }

        // Resolve o serviço por ID numérico ou UUID
        $service = Service::where('uuid', $serviceParam)
            ->orWhere('id', $serviceParam)
            ->first();

        $serviceName = $service?->service ?? 'Serviço';

        // Busca o relacionamento de serviços do usuário flexivelmente por ID, UUID ou rota
        $servicePermission = $user?->ToServices->first(function ($item) use ($serviceParam, $service) {
            return (string)$item->service_id === (string)$serviceParam
                || ($service && (string)$item->service_id === (string)$service->id)
                || ($service && (string)$item->service_id === (string)$service->uuid);
        });

        if (!$servicePermission) {
            abort(403, 'Você não tem permissão para realizar serviço em ' . $serviceName);
        }

        // Verifica a permissão com base no tipo de rota (services, construction, dispatch)
        if ($type == 'dispatch' && !$servicePermission->dispatch) {
            abort(403, 'Você não tem permissão para despacho em ' . $serviceName);
        }

        if (($type == 'services' || $type == 'construction') && !$servicePermission->service) {
            abort(403, 'Você não tem permissão para serviço em ' . $serviceName);
        }

        return $next($request);
    }
}
