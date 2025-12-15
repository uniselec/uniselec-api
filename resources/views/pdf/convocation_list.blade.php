{{-- resources/views/pdf/convocation_list.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 12px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
        }

        .header p {
            margin: 2px 0;
            font-size: 13px;
            color: #555;
        }

        .block {
            margin-top: 14px;
        }

        .block h2 {
            margin: 0 0 4px;
            font-size: 14px;
        }

        .block .subtitle {
            margin: 0 0 8px;
            font-size: 12px;
            color: #444;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 4px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .small {
            font-size: 11px;
            color: #444;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ $selectionName ?? 'Lista de Convocação' }}</h1>
        <p class="muted">
            {{ $selection?->description ?? '' }}
        </p>
        @if (!empty($publishedAt))
            <p class="small">Publicada em: {{ $publishedAt }}</p>
        @endif
    </div>

    @foreach ($groups as $group)
        <div class="block">
            <h2>{{ $group['course_name'] }}</h2>
            <p class="subtitle">Categoria: {{ $group['category_name'] }}</p>

            <table>
                <thead>
                    <tr>
                        <th style="width: 70px;">Ordem</th>
                        <th>Nome</th>
                        <th style="width: 140px;">CPF</th>
                        <th style="width: 220px;">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($group['items'] as $item)
                        <tr>
                            <td>{{ $item['category_ranking'] }}</td>
                            <td>{{ mb_strtoupper($item['name']) }}</td>
                            <td>{{ $item['cpf_masked'] }}</td>
                            <td>{{ $item['status_label'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Nenhum convocado encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach
</body>

</html>
