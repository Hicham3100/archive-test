<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'third_party/fpdf.php';
require_once APPPATH . 'third_party/fpdi/autoload.php';

use setasign\Fpdi\Fpdi;

class Pdf_watermark {
    
    public function add_watermark($source_file, $output_file, $username, $datetime) {
        $pdf = new Fpdi();
        
        $pageCount = $pdf->setSourceFile($source_file);
        $text = "Consulté par : " . $username . " - " . $datetime . " - Document confidentiel";
        
        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], array($size['width'], $size['height']));
            $pdf->useTemplate($template);
            
            // Ajouter le filigrane en transparence
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->SetAlpha(0.3);
            $pdf->SetXY(10, $size['height'] - 20);
            $pdf->Cell(0, 10, $text, 0, 0, 'C');
            $pdf->SetAlpha(1);
        }
        
        $pdf->Output($output_file, 'F');
        return $output_file;
    }
}