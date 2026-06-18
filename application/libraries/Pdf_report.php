<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once FCPATH . 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf_report {
    protected $ci;
    protected $dompdf;

    public function __construct() {
        $this->ci =& get_instance();
        
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $this->dompdf = new Dompdf($options);
    }

    public function generate($view, $data = [], $filename = 'rapport.pdf') {
        $html = $this->ci->load->view($view, $data, TRUE);
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
        $this->dompdf->stream($filename, ['Attachment' => 1]);
    }
}