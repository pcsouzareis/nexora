<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository
    ) {
    }

    /**
     * Retorna os dados do Dashboard da organização.
     */
    public function getDashboard(int $cod001): array
    {
        return [
            'resumo' => $this->dashboardRepository->getResumo($cod001),
        ];
    }
}