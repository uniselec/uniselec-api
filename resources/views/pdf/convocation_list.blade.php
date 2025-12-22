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

        /* Legenda */
        .legend {
            margin-top: 18px;
            padding: 10px 12px;
            border: 1px solid #333;
            background: #f7f7f7;
            font-size: 11px;
            line-height: 1.35;
        }

        .legend h3 {
            margin: 0 0 8px;
            font-size: 12px;
        }

        .legend p {
            margin: 6px 0;
        }

        .legend ul {
            margin: 6px 0 8px 18px;
            padding: 0;
        }

        .legend li {
            margin: 3px 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>{{ 'Lista de Convocação' }}</h1>
        <p class="muted">
            {{ $selectionName }}
        </p>
        UNILAB - Universidade da Integração Internacional da Lusofonia Afro-Brasileira
    </div>

    {{-- Legenda (agora logo abaixo do título) --}}
    <div class="legend">
        <h3>Legenda</h3>

        <ul>
            <li><strong>Convocado</strong>: candidato que tem direito subjetivo à ocupar a vaga;</li>
            <li><strong>Convocado Fora de Vaga</strong>: candidato que tem apenas expectativa de ocupar a vaga, podendo ou não ocupá-la no caso de não preenchimento.</li>
        </ul>

        <p>
            Para estarem aptos a ocupar as vagas, todos os candidatos convocados nesta chamada (incluindo aqueles convocados fora do número de vagas)
            devem, obrigatoriamente, enviar a documentação de pré-matrícula e participar da banca de heteroidentificação ou verificação PCD,
            se aplicável, seguindo o cronograma estabelecido.
        </p>

        <p>
            Para mais detalhes, é indispensável a leitura do edital completo, com atenção especial ao item <strong>1.5</strong>.
        </p>
    </div>

    @foreach ($groups as $group)
        <div class="block">
            <h2>Curso: {{ $group['course_name'] }}</h2>
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
