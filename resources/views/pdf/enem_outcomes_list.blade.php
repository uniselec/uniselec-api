{{-- resources/views/pdf/enem_outcomes_list.blade.php --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0 10px; font-size: 14px; color: #555; }
        .subtitle { text-align: center; margin-bottom: 20px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ strtoupper($selection->name) }}</h1>
        <p>{{ $selection->description }}</p>
    </div>

    <div class="subtitle">
        Lista de Deferidos/Indeferidos
    </div>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>Situação</th>
                <th>Motivo da Decisão</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lista as $item)
                <tr>
                    <td>{{ strtoupper($item['nome']) }}</td>
                    <td>{{ $item['cpf'] }}</td>
                    <td>{{ $item['status'] }}</td>
                    <td>{{ $item['motivo'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
