<?php

namespace App\Traits;

trait FilterCompany
{
    protected function getData($query, $company = null)
    {
        if ($company)
            $query->where('company_id', $company->id);

        return $query;
    }

    protected function getTrashedRecords($modelClass, $company = null)
    {
        $query = $modelClass::onlyTrashed();

        if ($company) {
            $companyId = is_object($company) ? $company->id : $company;
            $query->where('company_id', $companyId)->count();
        }

        return $query->count();
    }
}
