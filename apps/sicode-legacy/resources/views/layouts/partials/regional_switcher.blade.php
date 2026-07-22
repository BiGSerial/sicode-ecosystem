@php
    $currentUnit = config('sicode.unit', 'es');
    $unitName = match(strtolower($currentUnit)) {
        'es' => 'EDP ES (Espírito Santo)',
        'sp' => 'EDP SP (São Paulo)',
        default => 'EDP (' . strtoupper($currentUnit) . ')'
    };
    $unitBadge = strtoupper($currentUnit);

    $esUrl = env('SICODE_URL_ES', 'http://localhost:8084');
    $spUrl = env('SICODE_URL_SP', 'http://localhost:8083');
@endphp

<div class="dropdown ms-3 d-inline-block align-middle">
    <button class="btn btn-sm btn-outline-light dropdown-toggle d-inline-flex align-items-center gap-1 py-1 px-2.5 rounded-pill shadow-sm" type="button" id="regionalSwitcherDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Regional ativa: {{ $unitName }}. Clique para alternar.">
        <i class="bi bi-geo-alt-fill text-warning me-1"></i>
        <span class="fw-bold me-1" style="font-size: 0.85rem;">{{ $unitName }}</span>
    </button>
    <ul class="dropdown-menu shadow border-0 mt-1" aria-labelledby="regionalSwitcherDropdown" style="min-width: 240px; z-index: 1070;">
        <li><h6 class="dropdown-header text-uppercase fw-bold text-muted py-1" style="font-size: 0.68rem; letter-spacing: 0.5px;">Alternar Regional</h6></li>
        <li>
            <a class="dropdown-item d-flex align-items-center justify-content-between py-2 {{ strtolower($currentUnit) === 'es' ? 'active bg-primary text-white fw-bold' : '' }}" href="{{ $esUrl }}">
                <span class="d-flex align-items-center">
                    <i class="bi bi-building me-2 text-warning"></i> EDP Espírito Santo (ES)
                </span>
                @if(strtolower($currentUnit) === 'es')
                    <span class="badge bg-light text-primary ms-2"><i class="bi bi-check-circle-fill me-1"></i> Ativa</span>
                @endif
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center justify-content-between py-2 {{ strtolower($currentUnit) === 'sp' ? 'active bg-primary text-white fw-bold' : '' }}" href="{{ $spUrl }}">
                <span class="d-flex align-items-center">
                    <i class="bi bi-building me-2 text-warning"></i> EDP São Paulo (SP)
                </span>
                @if(strtolower($currentUnit) === 'sp')
                    <span class="badge bg-light text-primary ms-2"><i class="bi bi-check-circle-fill me-1"></i> Ativa</span>
                @endif
            </a>
        </li>
    </ul>
</div>
