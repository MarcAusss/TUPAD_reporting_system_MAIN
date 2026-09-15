{{-- Single NGA Target Accomplishment row. --}}
<tr class="odd:bg-white even:bg-slate-50 hover:bg-slate-100/70">
    <td class="border border-slate-200 px-3 py-2 font-semibold text-slate-900">{{ $row['agency'] }}</td>
    <td class="border border-slate-200 px-3 py-2">{{ $row['program'] }}</td>
    <td class="border border-slate-200 px-3 py-2">{{ $row['municipality'] }}</td>
    <td class="border border-slate-200 px-3 py-2">{{ $row['province'] }}</td>
    <td class="border border-slate-200 px-3 py-2 text-center tabular-nums">{{ $number($row['number_of_days']) }}</td>

    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $number($row['target']['beneficiaries']) }}</td>
    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $money($row['target']['amount']) }}</td>

    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $number($row['accomplished']['beneficiaries']) }}</td>
    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $money($row['accomplished']['amount']) }}</td>

    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $number($row['balance']['beneficiaries']) }}</td>
    <td class="border border-slate-200 px-3 py-2 text-right tabular-nums">{{ $money($row['balance']['amount']) }}</td>
</tr>
