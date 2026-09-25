<?php
/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/verleih/class/verleihlabelpdf.class.php
 * \ingroup     verleih
 * \brief       Standalone inventory label (sticker) PDF generator. Uses only core
 *              TCPDF (via Dolibarr's pdf_getInstance()) - no dependency on any other
 *              custom label/printing module.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
 * Generates a sheet of inventory labels (school name/address/logo + inventory number
 * + barcode) for a set of VerleihItem records, laid out in a configurable grid.
 */
class VerleihLabelPdf
{
	/**
	 * @var DoliDB
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Build the PDF and stream it to the browser as a download.
	 *
	 * @param array<int,VerleihItem> $items Items to print labels for
	 * @param array<int,VerleihItemType> $itemtypesById Map itemtype id => VerleihItemType (for label text)
	 * @param string $filename Suggested download filename
	 * @return void
	 */
	public function stream(array $items, array $itemtypesById, $filename = 'verleih_etiketten.pdf')
	{
		global $conf, $mysoc, $langs;

		$cols = max(1, getDolGlobalInt('VERLEIH_LABEL_COLS') ?: 3);
		$rows = max(1, getDolGlobalInt('VERLEIH_LABEL_ROWS') ?: 8);

		$pdf = pdf_getInstance('A4', 'mm', 'P');
		$pdf->SetMargins(0, 0, 0);
		$pdf->SetAutoPageBreak(false, 0);
		$pdf->SetFont(pdf_getPDFFont($langs), '', 8);
		$pdf->setPrintHeader(false);
		$pdf->setPrintFooter(false);
		$pdf->SetCreator('Verleih');
		$pdf->SetTitle($langs->trans('VerleihLabels'));

		$pagewidth = 210;
		$pageheight = 297;
		$outermargin = 5;

		$usablewidth = $pagewidth - (2 * $outermargin);
		$usableheight = $pageheight - (2 * $outermargin);
		$cellwidth = $usablewidth / $cols;
		$cellheight = $usableheight / $rows;

		$logo = '';
		if (!empty($mysoc->logo)) {
			$logopath = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
			if (is_readable($logopath)) {
				$logo = $logopath;
			}
		}

		$perpage = $cols * $rows;
		$i = 0;
		foreach ($items as $item) {
			$posinpage = $i % $perpage;
			if ($posinpage == 0) {
				$pdf->AddPage();
			}
			$col = $posinpage % $cols;
			$row = intdiv($posinpage, $cols);

			$x = $outermargin + ($col * $cellwidth);
			$y = $outermargin + ($row * $cellheight);

			$this->drawLabel($pdf, $x, $y, $cellwidth, $cellheight, $item, $itemtypesById, $logo, $mysoc, $langs);

			$i++;
		}

		if ($i == 0) {
			$pdf->AddPage();
			$pdf->SetXY($outermargin, $outermargin);
			$pdf->Cell($usablewidth, 10, $langs->trans('VerleihNoItemsSelected'), 0, 1, 'C');
		}

		$pdf->Output($filename, 'D');
	}

	/**
	 * Draw a single label cell.
	 *
	 * @param TCPDF $pdf PDF instance
	 * @param float $x Cell X position (mm)
	 * @param float $y Cell Y position (mm)
	 * @param float $w Cell width (mm)
	 * @param float $h Cell height (mm)
	 * @param VerleihItem $item Item to print
	 * @param array<int,VerleihItemType> $itemtypesById Map itemtype id => VerleihItemType
	 * @param string $logo Path to logo file, or empty
	 * @param Societe $mysoc Own company (used as the "school")
	 * @param Translate $langs Language object
	 * @return void
	 */
	protected function drawLabel($pdf, $x, $y, $w, $h, $item, $itemtypesById, $logo, $mysoc, $langs)
	{
		$pad = 1.5;

		// Border to help cut/align the label sheet
		$pdf->Rect($x, $y, $w, $h, 'D', array('all' => array('width' => 0.1, 'color' => array(200, 200, 200))));

		$innerx = $x + $pad;
		$innerw = $w - (2 * $pad);
		$cury = $y + $pad;

		if ($logo) {
			$logoheight = 6;
			$pdf->Image($logo, $innerx, $cury, 0, $logoheight);
			$pdf->SetXY($innerx + 12, $cury);
			$pdf->SetFont('', 'B', 7);
			$pdf->MultiCell($innerw - 12, 3, $mysoc->name, 0, 'L', false, 1, $innerx + 12, $cury);
			$cury += $logoheight + 0.5;
		} else {
			$pdf->SetFont('', 'B', 7);
			$pdf->SetXY($innerx, $cury);
			$pdf->MultiCell($innerw, 3, $mysoc->name, 0, 'L', false, 1, $innerx, $cury);
			$cury += 3.2;
		}

		$pdf->SetFont('', '', 6);
		$address = trim($mysoc->address."\n".$mysoc->zip.' '.$mysoc->town);
		$pdf->SetXY($innerx, $cury);
		$pdf->MultiCell($innerw, 2.6, $address, 0, 'L', false, 1, $innerx, $cury);
		$cury += 5.5;

		if (!empty($item->fk_itemtype) && isset($itemtypesById[$item->fk_itemtype])) {
			$pdf->SetFont('', '', 6.5);
			$pdf->SetXY($innerx, $cury);
			$pdf->MultiCell($innerw, 2.8, $itemtypesById[$item->fk_itemtype]->label, 0, 'L', false, 1, $innerx, $cury);
			$cury += 3;
		}

		$pdf->SetFont('', 'B', 9);
		$pdf->SetXY($innerx, $cury);
		$pdf->MultiCell($innerw, 4, $item->inventorynumber, 0, 'L', false, 1, $innerx, $cury);
		$cury += 4.2;

		$barcodeheight = max(3, $y + $h - $cury - $pad - 1);
		if ($barcodeheight > 3) {
			$style = array('position' => '', 'align' => 'L', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '', 'border' => false, 'hpadding' => 0, 'vpadding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false, 'text' => false);
			$pdf->write1DBarcode($item->inventorynumber, 'C128', $innerx, $cury, $innerw, $barcodeheight, 0.3, $style, 'N');
		}
	}
}
