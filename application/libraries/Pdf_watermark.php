<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'third_party/fpdf.php';
require_once APPPATH . 'third_party/fpdi/src/autoload.php';

use setasign\Fpdi\Fpdi;

class Pdf_watermark {
    
    public function add_watermark($source_file, $output_file, $username, $datetime) {
        if (!file_exists($source_file)) {
            throw new Exception("Fichier source introuvable : " . $source_file);
        }
        
        $pdf = new Fpdi();
        
        try {
            $pageCount = $pdf->setSourceFile($source_file);
        } catch (Exception $e) {
            throw new Exception("Impossible de lire le PDF source : " . $e->getMessage());
        }
        
        $text = "Consulté par : " . $username . " - " . $datetime . " - Document confidentiel";
        
        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            
            // Ajout du filigrane en bas de page
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(200, 200, 200);
            $pdf->SetAlpha(0.3);
            $pdf->SetXY(10, $size['height'] - 15);
            $pdf->Cell(0, 8, $text, 0, 0, 'C');
            $pdf->SetAlpha(1);
        }
        
        $pdf->Output($output_file, 'F');
        return $output_file;
    }
}