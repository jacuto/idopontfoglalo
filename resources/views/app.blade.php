<!doctype html>
<html lang="hu">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Ráérő — időpontok egyszerűen</title>
    <link rel="stylesheet" href="/style.css?v={{ filemtime(public_path('style.css')) }}" />
  </head>
  <body>
    <div id="app"></div>
    <dialog id="modal"></dialog>
    <div id="toast" role="status"></div>
    <script src="/app.js?v={{ filemtime(public_path('app.js')) }}" defer></script>
    <script src="/workspace.js?v={{ filemtime(public_path('workspace.js')) }}" defer></script>
  </body>
</html>
