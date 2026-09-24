{{-- Satu label: teks + QR di atas, Code128 dan isinya di bawah (A-121). --}}
<div class="label">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top; padding: 0;">
                <div class="judul">{{ $label['title'] }}</div>
                <div class="sub">{{ $label['subtitle'] }}</div>
                <div class="detail">{{ $label['detail'] }}</div>
            </td>
            <td style="width: 1%; vertical-align: top; padding: 0 0 0 1mm;">
                <img class="qr" src="{{ $label['qr_image'] }}">
            </td>
        </tr>
    </table>
    <img class="batang" src="{{ $label['barcode'] }}">
    <div class="kode">{{ $label['code128'] }}</div>
</div>
