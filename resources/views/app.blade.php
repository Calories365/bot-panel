<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="secure-cookie" content="{{ config('session.secure_cookie') ? 'true' : 'false' }}">
    <title>Bot-panel</title>
    @vite('resources/css/app.css')
    @vite('resources/css/admin.css')
    @vite('resources/js/app.js')


    <link rel="icon" type="image/png" href="/images/botik.png">
</head>
<body>
<div id="app"></div>

</body>
</html>
