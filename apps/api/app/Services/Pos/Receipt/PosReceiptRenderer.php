<?php

namespace App\Services\Pos\Receipt;

use App\Enums\PosReceiptLayout;
use App\Models\PosReceipt;

/**
 * Renders receipt HTML (thermal 80mm / A4) and a lightweight text PDF.
 * Presentation layer only — reads the immutable snapshot.
 */
class PosReceiptRenderer
{
    public function html(PosReceipt $receipt, PosReceiptLayout $layout = PosReceiptLayout::Thermal80): string
    {
        $snap = $receipt->snapshot ?? [];
        $branding = $snap['branding'] ?? $snap['store'] ?? [];
        $order = $snap['order'] ?? [];
        $payment = $snap['payment'] ?? [];
        $customer = $snap['customer'] ?? [];
        $cashier = $snap['cashier'] ?? [];
        $session = $snap['session'] ?? [];
        $messages = $snap['messages'] ?? [];
        $lines = $snap['lines'] ?? [];
        $theme = $branding['theme_color'] ?? ($snap['store']['theme_color'] ?? '#1f4b3a');
        $width = $layout === PosReceiptLayout::A4 ? '720px' : '302px';
        $isThermal = $layout === PosReceiptLayout::Thermal80;

        $esc = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $money = fn ($v) => number_format((float) ($v ?? 0), 0).' TZS';

        $rows = '';
        foreach ($lines as $line) {
            $rows .= '<tr>'
                .'<td>'.$esc($line['name'] ?? '').($line['variant'] ?? null ? '<br><small>'.$esc($line['variant']).'</small>' : '')
                .'<br><small>'.$esc($line['sku'] ?? '').'</small></td>'
                .'<td style="text-align:center">'.$esc($line['qty'] ?? 0).'</td>'
                .'<td style="text-align:right">'.$money($line['unit_price'] ?? 0).'</td>'
                .'<td style="text-align:right">'.$money($line['line_total'] ?? 0).'</td>'
                .'</tr>';
        }

        $paymentBlock = $this->paymentHtml($payment, $esc, $money);
        $customerLabel = ($customer['is_walk_in'] ?? true)
            ? 'Walk-in Customer'
            : ($customer['name'] ?? 'Customer');

        $logo = ! empty($branding['logo_path'] ?? ($snap['store']['logo_path'] ?? null))
            ? '<div class="logo">'.$esc($branding['store_name'] ?? $snap['store']['name'] ?? '').'</div>'
            : '';

        $policies = '';
        if (! empty($messages['return_policy'])) {
            $policies .= '<p class="muted">Return: '.$esc($messages['return_policy']).'</p>';
        }
        if (! empty($messages['exchange_policy'])) {
            $policies .= '<p class="muted">Exchange: '.$esc($messages['exchange_policy']).'</p>';
        }

        $qr = $receipt->qr_payload['payload'] ?? ('pos-receipt:'.$receipt->id);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt {$esc($receipt->receipt_number)}</title>
<style>
  body { font-family: "Segoe UI", Arial, sans-serif; color: #111; margin: 0; background: #f4f4f4; }
  .sheet { width: {$width}; margin: 16px auto; background: #fff; padding: {$this->pad($isThermal)}; border-top: 4px solid {$esc($theme)}; }
  h1 { font-size: {$this->fs($isThermal, 16, 22)}px; margin: 0 0 4px; color: {$esc($theme)}; }
  .meta, .muted, small { color: #555; font-size: {$this->fs($isThermal, 11, 12)}px; }
  table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: {$this->fs($isThermal, 11, 13)}px; }
  th, td { padding: 4px 0; vertical-align: top; border-bottom: 1px dashed #ddd; }
  th { text-align: left; color: #666; font-weight: 600; }
  .totals { margin-top: 8px; font-size: {$this->fs($isThermal, 12, 14)}px; }
  .totals div { display: flex; justify-content: space-between; margin: 2px 0; }
  .grand { font-weight: 700; font-size: {$this->fs($isThermal, 14, 18)}px; margin-top: 6px; }
  .center { text-align: center; }
  .logo { font-weight: 700; letter-spacing: 0.04em; }
  @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; } }
</style>
</head>
<body>
  <div class="sheet">
    <div class="center">
      {$logo}
      <h1>{$esc($branding['store_name'] ?? $snap['store']['name'] ?? 'Store')}</h1>
      <div class="meta">
        {$esc($branding['address'] ?? '')}<br>
        {$esc($branding['phone'] ?? '')}
        {$this->taxLine($branding, $esc)}
      </div>
    </div>
    <hr>
    <div class="meta">
      <div>Receipt: <strong>{$esc($receipt->receipt_number)}</strong></div>
      <div>Order: {$esc($order['order_number'] ?? '')}</div>
      <div>Cashier: {$esc($cashier['name'] ?? '')}</div>
      <div>Terminal: {$esc($session['terminal_code'] ?? $session['terminal_name'] ?? '')}</div>
      <div>Session: {$esc($session['id'] ?? '')}</div>
      <div>Date: {$esc($this->formatIssued($snap['issued_at'] ?? $receipt->issued_at))}</div>
      <div>Customer: {$esc($customerLabel)}</div>
    </div>
    <table>
      <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
      <tbody>{$rows}</tbody>
    </table>
    <div class="totals">
      <div><span>Subtotal</span><span>{$money($order['subtotal'] ?? 0)}</span></div>
      <div><span>Discount</span><span>{$money($order['discount_total'] ?? 0)}</span></div>
      <div><span>Tax</span><span>{$money($order['tax_total'] ?? 0)}</span></div>
      <div class="grand"><span>TOTAL</span><span>{$money($order['grand_total'] ?? 0)}</span></div>
    </div>
    {$paymentBlock}
    <div class="center" style="margin-top:16px">
      <p>{$esc($messages['thank_you'] ?? 'Thank you for shopping with us!')}</p>
      <p class="muted">{$esc($messages['footer'] ?? '')}</p>
      {$policies}
      <p class="muted">{$esc($messages['website'] ?? '')} {$esc($messages['social_media'] ?? '')}</p>
      <p class="muted">QR: {$esc($qr)}</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    public function pdf(PosReceipt $receipt): string
    {
        $lines = $this->textLines($receipt);

        return (new SimpleTextPdf)->render($lines, 'Receipt '.$receipt->receipt_number);
    }

    /**
     * @return list<string>
     */
    public function textLines(PosReceipt $receipt): array
    {
        $snap = $receipt->snapshot ?? [];
        $branding = $snap['branding'] ?? $snap['store'] ?? [];
        $order = $snap['order'] ?? [];
        $payment = $snap['payment'] ?? [];
        $customer = $snap['customer'] ?? [];
        $cashier = $snap['cashier'] ?? [];
        $session = $snap['session'] ?? [];
        $messages = $snap['messages'] ?? [];

        $out = [
            (string) ($branding['store_name'] ?? $snap['store']['name'] ?? 'Store'),
            (string) ($branding['address'] ?? ''),
            (string) ($branding['phone'] ?? ''),
            str_repeat('-', 32),
            'Receipt: '.$receipt->receipt_number,
            'Order: '.($order['order_number'] ?? ''),
            'Cashier: '.($cashier['name'] ?? ''),
            'Terminal: '.($session['terminal_code'] ?? ''),
            'Date: '.$this->formatIssued($snap['issued_at'] ?? null),
            'Customer: '.(($customer['is_walk_in'] ?? true) ? 'Walk-in Customer' : ($customer['name'] ?? '')),
            str_repeat('-', 32),
        ];

        foreach ($snap['lines'] ?? [] as $line) {
            $out[] = ($line['name'] ?? 'Item').' x'.($line['qty'] ?? 0);
            $out[] = '  '.number_format((float) ($line['unit_price'] ?? 0), 2).' = '.number_format((float) ($line['line_total'] ?? 0), 2);
        }

        $out[] = str_repeat('-', 32);
        $out[] = 'Subtotal: '.number_format((float) ($order['subtotal'] ?? 0), 2);
        $out[] = 'Discount: '.number_format((float) ($order['discount_total'] ?? 0), 2);
        $out[] = 'TOTAL: '.number_format((float) ($order['grand_total'] ?? 0), 2);
        $out[] = 'Pay: '.($payment['method_code'] ?? '');
        if (($payment['amount_received'] ?? null) !== null) {
            $out[] = 'Received: '.number_format((float) $payment['amount_received'], 2);
        }
        if (($payment['change'] ?? null) !== null) {
            $out[] = 'Change: '.number_format((float) $payment['change'], 2);
        }
        $out[] = str_repeat('-', 32);
        $out[] = (string) ($messages['thank_you'] ?? 'Thank you!');
        if (! empty($messages['footer'])) {
            $out[] = (string) $messages['footer'];
        }
        $out[] = 'QR: '.($receipt->qr_payload['payload'] ?? ('pos-receipt:'.$receipt->id));

        return array_values(array_filter($out, fn ($l) => $l !== ''));
    }

    private function paymentHtml(array $payment, callable $esc, callable $money): string
    {
        $code = strtoupper((string) ($payment['method_code'] ?? ''));
        $html = '<div class="totals"><div><span>Payment</span><span>'.$esc($payment['method_label'] ?? $code).'</span></div>';

        if (($payment['amount_received'] ?? null) !== null) {
            $html .= '<div><span>Amount received</span><span>'.$money($payment['amount_received']).'</span></div>';
        }
        if (($payment['change'] ?? null) !== null) {
            $html .= '<div><span>Change</span><span>'.$money($payment['change']).'</span></div>';
        }
        if (! empty($payment['reference']) && ! in_array($code, ['CASH'], true)) {
            $html .= '<div><span>Reference</span><span>'.$esc($payment['reference']).'</span></div>';
        }

        return $html.'</div>';
    }

    private function taxLine(array $branding, callable $esc): string
    {
        if (empty($branding['tax_number'])) {
            return '';
        }

        return '<br>TIN: '.$esc($branding['tax_number']);
    }

    private function formatIssued(mixed $issued): string
    {
        if ($issued === null) {
            return now()->format('Y-m-d H:i');
        }
        try {
            return \Illuminate\Support\Carbon::parse($issued)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $issued;
        }
    }

    private function pad(bool $thermal): string
    {
        return $thermal ? '12px' : '28px';
    }

    private function fs(bool $thermal, int $small, int $large): int
    {
        return $thermal ? $small : $large;
    }
}
