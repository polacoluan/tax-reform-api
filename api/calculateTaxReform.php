<?php

declare(strict_types=1);

class CalculateTaxReform
{
    public function calculate(?array $payload): array
    {
        $data = $this->sanitizePayload($payload ?? []);

        // Antes da Reforma
        $beforeEntries = $this->buildBeforeEntries($data);
        $beforeCredits = $this->indexCreditsByCode($beforeEntries);
        $beforeExits = $this->buildBeforeExits($data, $beforeCredits);
        $beforeTotal = $this->sumDue($beforeExits);

        // Depois da Reforma (projeção LC 214/2025: 26.5% = 8.8% CBS + 17.7% IBS, IS = 1%)
        $projected = $this->resolveProjectedRates($data);
        $afterEntries = $this->buildAfterEntries($data, $projected);
        $afterCredits = $this->indexCreditsByCode($afterEntries);
        $afterExits = $this->buildAfterExits($data, $afterCredits, $projected);
        $afterTotal = $this->sumDue($afterExits);
        $afterTotalWithReduction = $afterTotal * (1 - $projected['reduction_percent']);

        return [
            'inputs' => $data,
            'before' => [
                'entries' => $beforeEntries,
                'exits' => $beforeExits,
                'total_due' => $beforeTotal,
            ],
            'after' => [
                'entries' => $afterEntries,
                'exits' => $afterExits,
                'totals' => [
                    'due' => $afterTotal,
                    'reduction_percent' => $projected['reduction_percent'] * 100,
                    'due_with_reduction' => $afterTotalWithReduction,
                ],
                'projected_rates' => $projected,
            ],
            'comparison' => [
                'difference' => $afterTotal - $beforeTotal,
                'difference_percent_points' => $this->calculateDifferencePercentPoints($beforeTotal, $afterTotal),
                'classification' => $this->classifyDifference($afterTotal - $beforeTotal),
            ],
        ];
    }

    private function sanitizePayload(array $payload): array
    {
        $fields = [
            'segment',
            'pis_pasep_aliquot_entry',
            'pis_pasep_base_entry',
            'pis_pasep_aliquot_exit',
            'pis_pasep_base_exit',
            'cofins_aliquot_entry',
            'cofins_base_entry',
            'cofins_aliquot_exit',
            'cofins_base_exit',
            'ipi_aliquot_entry',
            'ipi_base_entry',
            'ipi_aliquot_exit',
            'ipi_base_exit',
            'icms_aliquot_entry',
            'icms_base_entry',
            'icms_aliquot_exit',
            'icms_base_exit',
            'iss_aliquot_exit',
            'iss_base_exit',
            'cbs_aliquot_entry',
            'cbs_base_entry',
            'cbs_aliquot_exit',
            'cbs_base_exit',
            'ibs_aliquot_entry',
            'ibs_base_entry',
            'ibs_aliquot_exit',
            'ibs_base_exit',
        ];

        $sanitized = [];
        foreach ($fields as $field) {
            $value = $payload[$field] ?? 0;
            $sanitized[$field] = is_numeric($value) ? (float)$value : 0.0;
        }

        $sanitized['segment'] = (int)($payload['segment'] ?? 0);

        return $sanitized;
    }

    private function buildBeforeEntries(array $data): array
    {
        $taxes = [
            'pis_pasep' => [
                'label' => 'PIS/PASEP',
                'aliquot_key' => 'pis_pasep_aliquot_entry',
                'base_key' => 'pis_pasep_base_entry',
            ],
            'cofins' => [
                'label' => 'COFINS',
                'aliquot_key' => 'cofins_aliquot_entry',
                'base_key' => 'cofins_base_entry',
            ],
            'ipi' => [
                'label' => 'IPI',
                'aliquot_key' => 'ipi_aliquot_entry',
                'base_key' => 'ipi_base_entry',
            ],
            'icms' => [
                'label' => 'ICMS',
                'aliquot_key' => 'icms_aliquot_entry',
                'base_key' => 'icms_base_entry',
            ],
        ];

        $entries = [];
        foreach ($taxes as $code => $meta) {
            $aliquot = $data[$meta['aliquot_key']] ?? 0.0;
            $base = $data[$meta['base_key']] ?? 0.0;
            $credit = $this->calculateValue($base, $aliquot);

            $entries[] = [
                'code' => $code,
                'label' => $meta['label'],
                'aliquot' => $aliquot,
                'base' => $base,
                'credit' => $credit,
            ];
        }

        return $entries;
    }

    private function buildBeforeExits(array $data, array $credits): array
    {
        $issAliquotFallback = $data['cofins_aliquot_exit']
            ?? ($data['pis_pasep_aliquot_exit'] ?? 0.0);
        $issBaseFallback = $data['cofins_base_exit']
            ?? ($data['pis_pasep_base_exit'] ?? 0.0);

        $taxes = [
            'pis_pasep' => [
                'label' => 'PIS/PASEP',
                'aliquot_key' => 'pis_pasep_aliquot_exit',
                'base_key' => 'pis_pasep_base_exit',
            ],
            'cofins' => [
                'label' => 'COFINS',
                'aliquot_key' => 'cofins_aliquot_exit',
                'base_key' => 'cofins_base_exit',
            ],
            'iss' => [
                'label' => 'ISS',
                'aliquot_key' => 'iss_aliquot_exit',
                'base_key' => 'iss_base_exit',
                'fallback_aliquot' => $issAliquotFallback,
                'fallback_base' => $issBaseFallback,
            ],
            'icms' => [
                'label' => 'ICMS',
                'aliquot_key' => 'icms_aliquot_exit',
                'base_key' => 'icms_base_exit',
            ],
            'ipi' => [
                'label' => 'IPI',
                'aliquot_key' => 'ipi_aliquot_exit',
                'base_key' => 'ipi_base_exit',
            ],
        ];

        $exits = [];
        foreach ($taxes as $code => $meta) {
            $aliquot = $meta['aliquot_key'] ? ($data[$meta['aliquot_key']] ?? 0.0) : ($meta['aliquot_value'] ?? 0.0);
            if ($aliquot === 0.0 && isset($meta['fallback_aliquot'])) {
                $aliquot = $meta['fallback_aliquot'];
            }

            $base = $data[$meta['base_key']] ?? 0.0;
            if ($base === 0.0 && isset($meta['fallback_base'])) {
                $base = $meta['fallback_base'];
            }

            $debit = $this->calculateValue($base, $aliquot);
            $credit = $credits[$code] ?? 0.0;
            $due = max(0.0, $debit - $credit);

            $exits[] = [
                'code' => $code,
                'label' => $meta['label'],
                'aliquot' => $aliquot,
                'base' => $base,
                'debit' => $debit,
                'credit' => $credit,
                'due' => $due,
            ];
        }

        return $exits;
    }

    private function buildAfterEntries(array $data, array $projected): array
    {
        $entries = [];

        $entries[] = [
            'code' => 'cbs',
            'label' => 'CBS',
            'aliquot' => $projected['cbs_aliquot_entry'],
            'base' => $data['cbs_base_entry'] ?? 0.0,
            'credit' => $this->calculateValue($data['cbs_base_entry'] ?? 0.0, $projected['cbs_aliquot_entry']),
        ];

        $entries[] = [
            'code' => 'ibs',
            'label' => 'IBS',
            'aliquot' => $projected['ibs_aliquot_entry'],
            'base' => $data['ibs_base_entry'] ?? 0.0,
            'credit' => $this->calculateValue($data['ibs_base_entry'] ?? 0.0, $projected['ibs_aliquot_entry']),
        ];

        return $entries;
    }

    private function buildAfterExits(array $data, array $credits, array $projected): array
    {
        $exits = [];

        $cbsDebit = $this->calculateValue($data['cbs_base_exit'] ?? 0.0, $projected['cbs_aliquot_exit']);
        $exits[] = [
            'code' => 'cbs',
            'label' => 'CBS',
            'aliquot' => $projected['cbs_aliquot_exit'],
            'base' => $data['cbs_base_exit'] ?? 0.0,
            'debit' => $cbsDebit,
            'credit' => $credits['cbs'] ?? 0.0,
            'due' => max(0.0, $cbsDebit - ($credits['cbs'] ?? 0.0)),
        ];

        $ibsDebit = $this->calculateValue($data['ibs_base_exit'] ?? 0.0, $projected['ibs_aliquot_exit']);
        $exits[] = [
            'code' => 'ibs',
            'label' => 'IBS',
            'aliquot' => $projected['ibs_aliquot_exit'],
            'base' => $data['ibs_base_exit'] ?? 0.0,
            'debit' => $ibsDebit,
            'credit' => $credits['ibs'] ?? 0.0,
            'due' => max(0.0, $ibsDebit - ($credits['ibs'] ?? 0.0)),
        ];

        $isDebit = $this->calculateValue($data['ipi_base_exit'] ?? 0.0, $projected['is_aliquot']);
        $exits[] = [
            'code' => 'is',
            'label' => 'IS',
            'aliquot' => $projected['is_aliquot'],
            'base' => $data['ipi_base_exit'] ?? 0.0,
            'debit' => $isDebit,
            'credit' => 0.0,
            'due' => $isDebit,
        ];

        return $exits;
    }

    private function indexCreditsByCode(array $entries): array
    {
        $credits = [];
        foreach ($entries as $entry) {
            $credits[$entry['code']] = $entry['credit'] ?? 0.0;
        }

        return $credits;
    }

    private function sumDue(array $exits): float
    {
        return array_reduce(
            $exits,
            static fn(float $carry, array $item): float => $carry + ($item['due'] ?? 0.0),
            0.0
        );
    }

    private function calculateValue(float $base, float $aliquot): float
    {
        return $base * $aliquot / 100;
    }

    private function resolveProjectedRates(array $data): array
    {
        $reduction = 0.0;

        $cbsAliquotEntry = $data['cbs_aliquot_entry'] ?: 0.0;
        $cbsAliquotExit = $data['cbs_aliquot_exit'] ?: 0.0;
        $ibsAliquotEntry = $data['ibs_aliquot_entry'] ?: 0.0;
        $ibsAliquotExit = $data['ibs_aliquot_exit'] ?: 0.0;

        $cbsAliquotEntry = $cbsAliquotEntry !== 0.0 ? $cbsAliquotEntry : 8.8;
        $cbsAliquotExit = $cbsAliquotExit !== 0.0 ? $cbsAliquotExit : $cbsAliquotEntry;
        $ibsAliquotEntry = $ibsAliquotEntry !== 0.0 ? $ibsAliquotEntry : 17.7;
        $ibsAliquotExit = $ibsAliquotExit !== 0.0 ? $ibsAliquotExit : $ibsAliquotEntry;

        return [
            'cbs_aliquot_entry' => $cbsAliquotEntry,
            'cbs_aliquot_exit' => $cbsAliquotExit,
            'ibs_aliquot_entry' => $ibsAliquotEntry,
            'ibs_aliquot_exit' => $ibsAliquotExit,
            'cbs_aliquot' => $cbsAliquotExit,
            'ibs_aliquot' => $ibsAliquotExit,
            'is_aliquot' => 1.0,
            'reduction_percent' => $reduction,
        ];
    }

    private function classifyDifference(float $difference): string
    {
        return match (true) {
            $difference > 0 => 'aumento de carga',
            $difference < 0 => 'redução de carga',
            default => 'neutro',
        };
    }

    private function calculateDifferencePercentPoints(float $before, float $after): float
    {
        if ($before <= 0.0) {
            return $after > 0 ? 100.0 : 0.0;
        }

        return ($after - $before) / $before * 100;
    }
}
