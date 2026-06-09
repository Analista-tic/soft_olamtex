{{-- Partial: encabezado compartido para todos los informes PDF --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
    <tr>
        <td style="width:120px; vertical-align:middle;">
            @if(file_exists(public_path('images/olamtex.png')))
                <img src="{{ public_path('images/olamtex.png') }}" alt="Logo" style="max-height:36px; max-width:110px;" />
            @else
                <span style="font-size:13pt; font-weight:800; letter-spacing:2px; color:#1e3d70;">OLAMTEX</span>
            @endif
        </td>
        <td style="text-align:center; vertical-align:middle;">
            <div style="font-size:14pt; font-weight:700; color:#1e3d70;">{{ $title }}</div>
            <div style="font-size:10pt; color:#555; margin-top:2px;">{{ $subtitle }}</div>
        </td>
        <td style="width:140px; text-align:right; vertical-align:middle; font-size:8pt; color:#666;">
            <div><strong>Generado:</strong></div>
            <div>{{ $printDate }}</div>
        </td>
    </tr>
</table>
<hr style="border:none; border-top:2px solid #1e3d70; margin-bottom:14px;" />
