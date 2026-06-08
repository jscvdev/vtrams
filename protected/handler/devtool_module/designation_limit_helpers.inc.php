<?php

if (!function_exists('remove_udc_and_office')) {
    /**
     * Remove a UDC and its corresponding office by index from parallel comma-separated lists.
     */
    function remove_udc_and_office(string $udcList, string $officeList, string $udcToRemove): array
    {
        $udcs = array_map('trim', explode(',', $udcList));
        $offices = array_map('trim', explode(',', $officeList));
        $newUdcs = [];
        $newOffices = [];

        foreach ($udcs as $index => $u) {
            if ($u !== $udcToRemove) {
                $newUdcs[] = $u;
                $newOffices[] = ($offices[$index] ?? '') === '' ? 'None' : $offices[$index];
            }
        }

        return [implode(',', $newUdcs), implode(',', $newOffices)];
    }
}

if (!function_exists('designation_office_already_listed')) {
    /**
     * Whether an office value is already present in a designated_office CSV list.
     */
    function designation_office_already_listed(array $offices, string $office): bool
    {
        $office = trim($office);
        foreach ($offices as $listedOffice) {
            if (strcasecmp(trim((string) $listedOffice), $office) === 0) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('append_designated_udc_office')) {
    /**
     * Append a UDC to designated_udc without duplicating offices in designated_office.
     */
    function append_designated_udc_office(array $existingUDCs, array $existingOffices, string $udc, string $office): array
    {
        $udc = trim($udc);
        if ($udc === '' || in_array($udc, $existingUDCs, true)) {
            return [$existingUDCs, $existingOffices];
        }

        $existingUDCs[] = $udc;
        if (!designation_office_already_listed($existingOffices, $office)) {
            $existingOffices[] = $office;
        }

        return [$existingUDCs, $existingOffices];
    }
}

if (!function_exists('normalize_designated_offices')) {
    /**
     * Normalize designated_office values for parallel UDC lists.
     */
    function normalize_designated_offices(array $offices): array
    {
        foreach ($offices as &$office) {
            if (trim((string) $office) === '') {
                $office = 'None';
            }
        }
        unset($office);

        return $offices;
    }
}
