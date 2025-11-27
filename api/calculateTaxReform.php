<?php

declare(strict_types=1);

class CalculateTaxReform
{
    public function calculate(?array $payload): array
    {
        $data = $this->sanitizeInput($payload ?? []);

        $params = $this->resolveParameters($data['segment'], $data['activity']);
        $faturamento = $data['invoicing'];
        $custosPercent = $this->mapCostsBand($data['costs']);

        $baseAtual = $faturamento;
        $impostoAtual = $baseAtual * $params['t_atual'] / 100;
        $aliqEfetivaAtual = $impostoAtual / $faturamento * 100;

        $basePos = $faturamento;
        $tTotalBruta = $params['t_IBS'] + $params['t_CBS'];
        $tTotalEfetivaBruta = $tTotalBruta * (1 - $params['red_setorial']);
        $impostoPosBruto = $basePos * $tTotalEfetivaBruta / 100;

        $custos = $faturamento * $custosPercent / 100;
        $custosCreditaveis = $custos * $params['k_creditavel'];
        $tCredito = $params['t_credito'] ?? $tTotalEfetivaBruta;
        $creditoInsumos = $custosCreditaveis * $tCredito / 100;

        $impostoPosLiquido = max(0, $impostoPosBruto - $creditoInsumos);
        $aliqEfetivaPos = $impostoPosLiquido / $faturamento * 100;

        $difReais = $impostoPosLiquido - $impostoAtual;
        $difPp = $aliqEfetivaPos - $aliqEfetivaAtual;
        $classificacao = $this->classificar($difReais);

        return [
            'inputs' => $data,
            'parameters' => $params + [
                't_total_bruta' => $tTotalBruta,
                't_total_efetiva_bruta' => $tTotalEfetivaBruta,
                'custos_percent' => $custosPercent,
            ],
            'antes' => [
                'base' => $baseAtual,
                'imposto' => $impostoAtual,
                'aliquota_efetiva' => $aliqEfetivaAtual,
            ],
            'depois' => [
                'base' => $basePos,
                'imposto_bruto' => $impostoPosBruto,
                'credito_insumos' => $creditoInsumos,
                'imposto_liquido' => $impostoPosLiquido,
                'aliquota_efetiva' => $aliqEfetivaPos,
            ],
            'comparacao' => [
                'dif_reais' => $difReais,
                'dif_pp' => $difPp,
                'classificacao' => $classificacao,
            ],
        ];
    }

    private function sanitizeInput(array $payload): array
    {
        $segment = (int)($payload['segment'] ?? 0);
        $invoicing = (float)($payload['invoicing'] ?? 0);
        $costs = (int)($payload['costs'] ?? 0);
        $activity = (int)($payload['activity'] ?? 0);

        return [
            'segment' => $segment,
            'invoicing' => max(0, $invoicing),
            'costs' => $costs,
            'activity' => $activity,
        ];
    }

    private function resolveParameters(int $segment, int $activity): array
    {
        // illustrative reference tables; tune to your actual rates.
        $tAtualTable = [
            // activity: 1 Simples, 2 Presumido, 3 Real
            1 => [1 => 8.0, 2 => 10.0, 3 => 12.0], // Indústria
            2 => [1 => 6.0, 2 => 8.0, 3 => 10.0],  // Comércio
            3 => [1 => 8.0, 2 => 14.0, 3 => 16.0], // Serviços
            4 => [1 => 4.0, 2 => 6.0, 3 => 8.0],   // Agro
            5 => [1 => 7.0, 2 => 9.0, 3 => 11.0],  // Outros
        ];

        $ibsTable = [
            1 => 12.0,
            2 => 12.0,
            3 => 13.5,
            4 => 10.0,
            5 => 12.0,
        ];

        $cbsTable = [
            1 => 12.0,
            2 => 12.0,
            3 => 11.5,
            4 => 8.0,
            5 => 12.0,
        ];

        $reducoes = [
            4 => 0.40, // agro com redução setorial de 40%
        ];

        $segmentKey = $tAtualTable[$segment] ?? $tAtualTable[5];
        $tAtual = $segmentKey[$activity] ?? $segmentKey[1];
        $tIBS = $ibsTable[$segment] ?? 12.0;
        $tCBS = $cbsTable[$segment] ?? 12.0;
        $redSetorial = $reducoes[$segment] ?? 0.0;

        return [
            't_atual' => $tAtual,
            't_IBS' => $tIBS,
            't_CBS' => $tCBS,
            'red_setorial' => $redSetorial,
            'k_creditavel' => 1.0,
            't_credito' => $tIBS + $tCBS, // can be overridden
        ];
    }

    private function mapCostsBand(int $costs): float
    {
        return match ($costs) {
            1 => 30.0, // 0–30%
            2 => 45.0, // 30–60%
            3 => 75.0, // 60–90%
            default => 45.0,
        };
    }

    private function classificar(float $difReais): string
    {
        return match (true) {
            $difReais > 0 => 'aumento de carga',
            $difReais < 0 => 'redução de carga',
            default => 'neutro',
        };
    }
}
