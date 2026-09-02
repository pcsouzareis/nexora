<?php

declare(strict_types=1);

namespace App\Services;

use Spipu\Html2Pdf\Html2Pdf;

/** Gera a cópia imutável do contrato aceita pelo supervisor. */
final class LicenseContractPdfGenerator
{
    /**
     * @param array{name: string, company: string, version: string, accepted_at: string, ip: string} $acceptance
     */
    public function generate(string $contractHtml, string $filename, array $acceptance): string
    {
        if (!preg_match('~<main\\b[^>]*>(.*)</main>~si', $contractHtml, $matches)) {
            throw new \RuntimeException('Não foi possível preparar o contrato para geração do PDF.');
        }

        $content = preg_replace('~<p\\s+class="actions">.*?</p>~si', '', $matches[1]) ?? $matches[1];
        $content .= $this->acceptanceCertificate($acceptance);

        $directory = dirname(__DIR__, 2) . '/uploads/aceite';

        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Não foi possível criar o diretório dos contratos aceitos.');
        }

        $path = $directory . '/' . $filename;
        $pdf = new Html2Pdf('P', 'A4', 'pt', true, 'UTF-8', [12, 12, 12, 12]);
        $pdf->setDefaultFont('dejavusans');
        $pdf->writeHTML(<<<HTML
            <style>
                body { font-family: dejavusans; font-size: 10pt; color: #222222; line-height: 1.45; }
                h1 { font-size: 16pt; text-align: center; margin: 0 0 14pt; }
                h2 { font-size: 12pt; margin: 18pt 0 7pt; padding-bottom: 3pt; border-bottom: 0.5pt solid #cccccc; }
                p { text-align: justify; margin: 0 0 8pt; }
                .box, .acceptance { border: 0.5pt solid #cccccc; background-color: #f7f7f7; padding: 10pt; margin: 10pt 0; }
                .meta, .notice { font-size: 8pt; color: #555555; }
                .signatures { margin-top: 26pt; }
                .signature { border-top: 0.5pt solid #222222; padding-top: 5pt; text-align: center; margin-top: 18pt; }
                .pdf-acceptance { page-break-inside: avoid; }
            </style>
            {$content}
        HTML);
        $pdf->output($path, 'F');

        if (!is_file($path) || filesize($path) === 0) {
            throw new \RuntimeException('O PDF do contrato não foi gerado corretamente.');
        }

        return 'uploads/aceite/' . $filename;
    }

    /** @param array{name: string, company: string, version: string, accepted_at: string, ip: string} $acceptance */
    private function acceptanceCertificate(array $acceptance): string
    {
        $value = static fn (string $key): string => htmlspecialchars(
            $acceptance[$key],
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return <<<HTML
            <div class="acceptance pdf-acceptance">
                <h2 style="margin-top: 0">Registro de aceite eletrônico</h2>
                <p>Este documento foi aceito eletronicamente em nome da empresa <strong>{$value('company')}</strong>.</p>
                <p><strong>Supervisor responsável:</strong> {$value('name')}<br>
                <strong>Data e hora:</strong> {$value('accepted_at')}<br>
                <strong>Versão do contrato:</strong> {$value('version')}<br>
                <strong>Endereço IP:</strong> {$value('ip')}</p>
            </div>
        HTML;
    }
}
