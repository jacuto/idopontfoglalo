<!doctype html>
<html lang="hu">
  <head>
    <meta charset="UTF-8" />
    <meta name="referrer" content="same-origin" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Ráérő — időpontok egyszerűen</title>
    <link rel="stylesheet" href="/style.css?v={{ filemtime(public_path('style.css')) }}" />
    <link rel="stylesheet" href="/showcase.css?v={{ filemtime(public_path('showcase.css')) }}" />
  <link rel="stylesheet" href="/workspace-polish.css?v={{ filemtime(public_path('workspace-polish.css')) }}">
  <link rel="stylesheet" href="/launch-tools.css?v={{ filemtime(public_path('launch-tools.css')) }}">
<link rel="stylesheet" href="/interface-updates.css?v={{ filemtime(public_path('interface-updates.css')) }}">
<link rel="stylesheet" href="/record-tools.css?v={{ filemtime(public_path('record-tools.css')) }}">
<link rel="stylesheet" href="/november19.css?v={{ filemtime(public_path('november19.css')) }}">
<link rel="stylesheet" href="/booking-polish.css?v={{ filemtime(public_path('booking-polish.css')) }}">
<link rel="stylesheet" href="/mobile-followup.css?v={{ filemtime(public_path('mobile-followup.css')) }}">
<link rel="stylesheet" href="/contact-import.css?v={{ filemtime(public_path('contact-import.css')) }}">
@include('partials.consent')
</head>
  <body>
    <header class="visitor-header"><a class="visitor-brand" href="/" aria-label="Ráérő főoldal"><span>◷</span> ráérő.</a><details class="visitor-menu"><summary>Menü <span aria-hidden="true">☰</span></summary><nav aria-label="Felső menü"><a href="/szolgaltato">Szolgáltatóknak</a><a href="/arak">Árak</a><a href="/kapcsolat">Kapcsolat</a><a class="booking-nav" href="/szolgaltatok">Időpontfoglalás</a><a class="visitor-account" href="/fiokom">Saját fiókom</a></nav></details></header>
    <div id="app"></div>
    @include('partials.footer')
    <dialog id="modal"></dialog>
    <div id="toast" role="status"></div>
    <script src="/i18n.js?v={{ filemtime(public_path('i18n.js')) }}" defer></script>
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
    <script src="/growth.js?v={{ filemtime(public_path('growth.js')) }}" defer></script>
    <script src="/followup.js?v={{ filemtime(public_path('followup.js')) }}" defer></script>
    <script src="/pricing-features.js?v={{ filemtime(public_path('pricing-features.js')) }}" defer></script>
    <script src="/showcase.js?v={{ filemtime(public_path('showcase.js')) }}" defer></script>
    <script src="/team.js?v={{ filemtime(public_path('team.js')) }}" defer></script>
    <script src="/premium.js?v={{ filemtime(public_path('premium.js')) }}" defer></script>
<script src="/usability.js?v={{ filemtime(public_path('usability.js')) }}" defer></script>
<script src="/presentation.js?v={{ filemtime(public_path('presentation.js')) }}" defer></script>
<script src="/gallery.js?v={{ filemtime(public_path('gallery.js')) }}" defer></script>
<script src="/newsletter-templates.js?v={{ filemtime(public_path('newsletter-templates.js')) }}" defer></script>
<script src="/commerce.js?v={{ filemtime(public_path('commerce.js')) }}" defer></script>
<script src="/launch-tools.js?v={{ filemtime(public_path('launch-tools.js')) }}" defer></script>
<script src="/booking-payments.js?v={{ filemtime(public_path('booking-payments.js')) }}" defer></script>
<script src="/workspace-lists.js?v={{ filemtime(public_path('workspace-lists.js')) }}" defer></script>
<script src="/workspace-polish.js?v={{ filemtime(public_path('workspace-polish.js')) }}" defer></script>
<script src="/interface-updates.js?v={{ filemtime(public_path('interface-updates.js')) }}" defer></script>
<script src="/record-tools.js?v={{ filemtime(public_path('record-tools.js')) }}" defer></script>
<script src="/november19.js?v={{ filemtime(public_path('november19.js')) }}" defer></script>
<script src="/booking-polish.js?v={{ filemtime(public_path('booking-polish.js')) }}" defer></script>
<script src="/reminder-preferences.js?v={{ filemtime(public_path('reminder-preferences.js')) }}" defer></script>
<script src="/loyalty.js?v={{ filemtime(public_path('loyalty.js')) }}" defer></script>
<script src="/history-pagination.js?v={{ filemtime(public_path('history-pagination.js')) }}" defer></script>
<script src="/mobile-followup.js?v={{ filemtime(public_path('mobile-followup.js')) }}" defer></script>
<script src="/contact-import.js?v={{ filemtime(public_path('contact-import.js')) }}" defer></script>
<script src="/boot.js?v={{ filemtime(public_path('boot.js')) }}" defer></script>
  </body>
</html>
