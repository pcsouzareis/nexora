<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanyRepository;
use App\Repositories\SupervisorCompanyRepository;
use App\Support\Session;

final class CurrentCompanyContext
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly SupervisorCompanyRepository $supervisorCompanies
    ) {}

    public function availableCompanies(array $user): array
    {
        $role = (string) $user['rol002'];

        if ($role === 'D') {
            return array_values(array_filter(
                $this->companies->findAll(),
                fn (array $company): bool => $this->isActive($company)
            ));
        }

        if ($role === 'S') {
            $companies = $this->supervisorCompanies->findCompanies((int) $user['cod002']);
            $linkedCompany = $this->companies->findByCode((int) $user['cod001']);

            if ($linkedCompany === null || !$this->isActive($linkedCompany)) {
                return $companies;
            }

            foreach ($companies as $company) {
                if ((int) $company['cod001'] === (int) $linkedCompany['cod001']) {
                    return $companies;
                }
            }

            array_unshift($companies, $linkedCompany);

            return $companies;
        }

        $company = $this->companies->findByCode((int) $user['cod001']);

        return $company !== null && $this->isActive($company) ? [$company] : [];
    }

    public function currentCompany(array $user): ?array
    {
        $companies = $this->availableCompanies($user);

        if ($companies === []) {
            return null;
        }

        $selectedCode = Session::currentCompanyCode();

        foreach ($companies as $company) {
            if ((int) $company['cod001'] === $selectedCode) {
                return $company;
            }
        }

        foreach ($companies as $company) {
            if ((int) $company['cod001'] === (int) $user['cod001']) {
                Session::setCurrentCompany((int) $company['cod001']);
                return $company;
            }
        }

        Session::setCurrentCompany((int) $companies[0]['cod001']);

        return $companies[0];
    }

    public function companyCode(array $user): int
    {
        $company = $this->currentCompany($user);

        return $company !== null
            ? (int) $company['cod001']
            : (int) $user['cod001'];
    }

    public function select(array $user, int $companyCode): bool
    {
        foreach ($this->availableCompanies($user) as $company) {
            if ((int) $company['cod001'] === $companyCode) {
                Session::setCurrentCompany($companyCode);
                return true;
            }
        }

        return false;
    }

    private function isActive(array $company): bool
    {
        return in_array(
            $company['sts001'] ?? false,
            [true, 1, '1', 't', 'true'],
            true
        );
    }
}
