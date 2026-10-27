<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body>
    <h1>{{ $heading }}</h1>
    <p>{{ $bodyText }}</p>

    @if ($details !== [])
        <dl>
            @foreach ($details as $label => $value)
                <dt>{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    @endif

    @if ($hasInvoice)
        <p>Invoice terlampir pada email ini.</p>
    @endif

    <p>Salam,<br>{{ config('app.name') }}</p>
</body>
</html>
