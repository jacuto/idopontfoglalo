<!doctype html>
<html lang="hu">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Ráérő — időpontok egyszerűen</title>
    <link rel="stylesheet" href="/style.css?v={{ filemtime(public_path('style.css')) }}" />
  </head>
  <body>
    <header class="visitor-header"><a class="visitor-brand" href="/" aria-label="Ráérő főoldal"><span>◷</span> ráérő.</a><details class="visitor-menu"><summary>Menü <span aria-hidden="true">☰</span></summary><nav aria-label="Felső menü"><a href="/szolgaltato">Csatlakozom</a><a href="/arak">Árak</a><a href="/kapcsolat">Kapcsolat</a><a class="booking-nav" href="/szolgaltatok">Időpontfoglalás</a><a class="visitor-account" href="/fiokom">Saját fiókom</a></nav></details></header>
    <div id="app"></div>
    <footer class="site-footer">Copyright 2026 <a href="https://digitmarketing.hu/">digitmarketing.hu</a></footer>
    <dialog id="modal"></dialog>
    <div id="toast" role="status"></div>
    <script src="/app.js?v={{ filemtime(public_path('app.js')) }}" defer></script>
    <script src="/workspace.js?v={{ filemtime(public_path('workspace.js')) }}" defer></script>
    <script src="/features.js?v={{ filemtime(public_path('features.js')) }}" defer></script>
    <script src="/availability.js?v={{ filemtime(public_path('availability.js')) }}" defer></script>
    <script src="/polish.js?v={{ filemtime(public_path('polish.js')) }}" defer></script>
    <script src="/refinements.js?v={{ filemtime(public_path('refinements.js')) }}" defer></script>
    <script src="/operations.js?v={{ filemtime(public_path('operations.js')) }}" defer></script>
    <script src="/workflows.js?v={{ filemtime(public_path('workflows.js')) }}" defer></script>
    <script src="/comfort.js?v={{ filemtime(public_path('comfort.js')) }}" defer></script>
    <script src="/vendor/qrcode.js?v={{ filemtime(public_path('vendor/qrcode.js')) }}" defer></script>
    <script src="/personalization.js?v={{ filemtime(public_path('personalization.js')) }}" defer></script>
    <script src="/navigation.js?v={{ filemtime(public_path('navigation.js')) }}" defer></script>
    <script src="/discovery.js?v={{ filemtime(public_path('discovery.js')) }}" defer></script>
    <script src="/boot.js?v={{ filemtime(public_path('boot.js')) }}" defer></script>
  </body>
</html>
