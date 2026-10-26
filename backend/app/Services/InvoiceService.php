<?php

namespace App\Services;

use App\Models\Order;
use FPDF;
use Illuminate\Support\Str;

class InvoiceService
{
    public function generate(Order $order): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 10, 'INVOICE', 0, 1);

        $pdf->SetFont('Arial', '', 10);
        $this->line($pdf, 'Nomor invoice', 'INV-'.$order->order_number);
        $this->line($pdf, 'Tanggal', $order->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, 'Ditagihkan kepada', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, $this->text($order->user->name));
        $pdf->MultiCell(0, 6, $this->text($order->user->email));
        $pdf->Ln(5);

        if ($order->voucher) {
            $pdf->MultiCell(0, 6, $this->text('Kode voucher: '.$order->voucher->code));
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(120, 8, 'Deskripsi', 1);
        $pdf->Cell(60, 8, 'Jumlah (IDR)', 1, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(120, 8, $this->text($order->plan->name), 1);
        $pdf->Cell(60, 8, $this->money($order->subtotal), 1, 1, 'R');

        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, $this->text('Jenis pesanan: '.($order->upgrade_from_subscription_id !== null ? 'upgrade' : 'langganan')));
        if ($order->subscription) {
            $period = $order->subscription->start_date->timezone('Asia/Jakarta')->format('d/m/Y')
                .' - '.$order->subscription->end_date->timezone('Asia/Jakarta')->format('d/m/Y');
            $pdf->MultiCell(0, 6, $this->text('Periode langganan: '.$period));
        } else {
            $pdf->MultiCell(0, 6, 'Periode langganan: belum aktif');
        }

        if ($order->discount > 0) {
            $this->amountRow($pdf, 'Diskon voucher', -$order->discount);
        }

        if ($order->upgrade_credit > 0) {
            $this->amountRow($pdf, 'Kredit prorata yang diterapkan', -min($order->upgrade_credit, $order->subtotal));
        }

        $this->amountRow($pdf, 'Total dibayar', $order->total, bold: true);
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, $this->text('Status pesanan: '.$order->status->value));
        $payment = $order->payments->first();
        $pdf->MultiCell(0, 6, $this->text('Status pembayaran: '.($payment?->status->value ?? 'belum ada')));

        if ($payment) {
            $pdf->MultiCell(0, 6, $this->text('Metode pembayaran: '.($payment->method ?? '-')));

            if ($payment->paid_at) {
                $pdf->MultiCell(
                    0,
                    6,
                    $this->text('Dibayar pada: '.$payment->paid_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB')
                );
            }
        }

        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->MultiCell(0, 5, 'Dokumen ini merangkum transaksi berdasarkan data yang tercatat pada layanan.');

        return $pdf->Output('S');
    }

    private function line(FPDF $pdf, string $label, string $value): void
    {
        $pdf->Cell(35, 6, $this->text($label), 0);
        $pdf->Cell(0, 6, $this->text($value), 0, 1);
    }

    private function amountRow(FPDF $pdf, string $label, int $amount, bool $bold = false): void
    {
        $pdf->SetFont('Arial', $bold ? 'B' : '', 10);
        $pdf->Cell(120, 8, $this->text($label), 1);
        $pdf->Cell(60, 8, $this->money($amount), 1, 1, 'R');
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private function text(string $value): string
    {
        return Str::ascii($value);
    }
}
