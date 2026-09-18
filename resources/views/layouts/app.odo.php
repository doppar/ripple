<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>#yield('title') · Ripple</title>
    [[! \Doppar\Ripple\Facade\Ripple::scripts() !]]
    #yield('head')
</head>
<body>
    <main>
        #yield('content')
    </main>
</body>
</html>
