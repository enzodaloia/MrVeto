<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGeneratorService
{
    private Environment $twig;
    private string $uploadDir;

    public function __construct(Environment $twig, string $projectDir)
    {
        $this->twig = $twig;
        // In Symfony, projectDir is %kernel.project_dir%
        $this->uploadDir = $projectDir . '/public/uploads/comptes_rendus';
    }

    public function generatePdfFromHtml(string $html): string
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        $pdfOptions->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
